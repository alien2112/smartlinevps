// ============================================
// 🔍 EVIDENCE VALIDATION SERVICE V3.4
// AI-powered evidence validation with OCR & STT
// ============================================

const fs = require('fs');
const path = require('path');

// Minimum confidence threshold for evidence
const MIN_CONFIDENCE_THRESHOLD = 70;

class EvidenceValidationService {
    constructor(dbQuery, dbExecute, groqApiKey) {
        this.dbQuery = dbQuery;
        this.dbExecute = dbExecute;
        this.groqApiKey = groqApiKey;
        this.groq = null;
        
        // Initialize Groq if API key is available
        if (groqApiKey) {
            try {
                const Groq = require('groq-sdk');
                this.groq = new Groq({ apiKey: groqApiKey });
            } catch (error) {
                console.warn('[EvidenceValidation] Groq SDK not available:', error.message);
            }
        }
    }
    
    /**
     * Validate uploaded evidence
     * @param {Object} evidenceFile - Evidence file info
     * @param {Object} tripData - Trip data for context
     * @param {string} userId - User ID
     * @returns {Object} - Validation result
     */
    async validateEvidence(evidenceFile, tripData, userId) {
        const { type, path: filePath, uploadedAt, size } = evidenceFile;
        
        // Basic validation
        const basicValidation = this.performBasicValidation(evidenceFile, tripData);
        if (!basicValidation.valid) {
            return basicValidation;
        }
        
        // Type-specific validation
        try {
            if (type === 'image' || evidenceFile.mimetype?.startsWith('image')) {
                return await this.validateImage(filePath, tripData);
            } else if (type === 'audio' || evidenceFile.mimetype?.startsWith('audio')) {
                return await this.validateAudio(filePath, tripData);
            }
        } catch (error) {
            console.error('[EvidenceValidation] Validation error:', error);
            return { 
                valid: false, 
                error: 'VALIDATION_FAILED', 
                message: error.message 
            };
        }
        
        return { valid: false, error: 'UNSUPPORTED_TYPE', message: 'Unsupported evidence type' };
    }
    
    /**
     * Basic validation checks
     * @param {Object} evidenceFile - Evidence file info
     * @param {Object} tripData - Trip data
     * @returns {Object} - Validation result
     */
    performBasicValidation(evidenceFile, tripData) {
        const { uploadedAt, size } = evidenceFile;
        
        // Check file size (max 10MB)
        if (size && size > 10 * 1024 * 1024) {
            return { 
                valid: false, 
                error: 'FILE_TOO_LARGE', 
                message: 'File exceeds 10MB limit' 
            };
        }
        
        // Check timestamp - evidence should be from after trip started
        if (tripData?.started_at && uploadedAt) {
            const tripStartTime = new Date(tripData.started_at);
            const uploadTime = new Date(uploadedAt);
            
            if (uploadTime < tripStartTime) {
                return { 
                    valid: false, 
                    error: 'TIMESTAMP_INVALID', 
                    message: 'Evidence timestamp is before trip start' 
                };
            }
            
            // Check if evidence is too old (more than 24 hours after trip)
            const hoursSinceTrip = (uploadTime - tripStartTime) / (1000 * 60 * 60);
            if (hoursSinceTrip > 24) {
                return { 
                    valid: false, 
                    error: 'EVIDENCE_TOO_OLD', 
                    message: 'Evidence must be submitted within 24 hours' 
                };
            }
        }
        
        return { valid: true };
    }
    
    /**
     * Validate image evidence using OCR + AI
     * @param {string} filePath - Path to image file
     * @param {Object} tripData - Trip data
     * @returns {Object} - Validation result
     */
    async validateImage(filePath, tripData) {
        try {
            // Step 1: Run OCR
            const ocrResult = await this.performOCR(filePath);
            
            if (!ocrResult.text || ocrResult.text.trim().length < 5) {
                return {
                    valid: true,
                    confidence: 30,
                    ocr_text: ocrResult.text,
                    analysis: { summary: 'No readable text found in image' },
                    needs_manual_review: true
                };
            }
            
            // Step 2: Analyze text with AI
            const analysis = await this.analyzeImageContent(ocrResult.text, tripData);
            
            return {
                valid: true,
                confidence: analysis.confidence || 50,
                keywords_detected: analysis.keywords || [],
                ocr_text: ocrResult.text,
                analysis: analysis,
                captain_name_match: analysis.captainNameMatch || false,
                contains_manipulation: analysis.containsCancelRequest || analysis.containsCashRequest
            };
        } catch (error) {
            console.error('[EvidenceValidation] Image validation error:', error);
            return { 
                valid: true, 
                confidence: 20,
                error: 'VALIDATION_PARTIAL', 
                message: error.message,
                needs_manual_review: true
            };
        }
    }
    
    /**
     * Perform OCR on image
     * @param {string} imagePath - Path to image
     * @returns {Object} - OCR result
     */
    async performOCR(imagePath) {
        try {
            // Try Tesseract.js for local OCR
            const Tesseract = require('tesseract.js');
            
            const result = await Tesseract.recognize(imagePath, 'ara+eng', {
                logger: m => {
                    if (m.status === 'recognizing text') {
                        console.log('[OCR] Progress:', Math.round(m.progress * 100) + '%');
                    }
                }
            });
            
            return {
                text: result.data.text,
                confidence: result.data.confidence
            };
        } catch (error) {
            console.warn('[EvidenceValidation] OCR failed, using fallback:', error.message);
            // Return empty result - will need manual review
            return {
                text: '',
                confidence: 0,
                error: error.message
            };
        }
    }
    
    /**
     * Analyze OCR text with AI
     * @param {string} ocrText - Extracted text
     * @param {Object} tripData - Trip data
     * @returns {Object} - Analysis result
     */
    async analyzeImageContent(ocrText, tripData) {
        if (!this.groq) {
            // Fallback to keyword-based analysis
            return this.keywordBasedAnalysis(ocrText, tripData);
        }
        
        try {
            const prompt = `
أنت محلل أدلة لشركة توصيل. حلل النص التالي المستخرج من صورة:

النص: "${ocrText}"

معلومات الرحلة:
- اسم الكابتن: ${tripData?.captain_name || 'غير متوفر'}
- رقم السيارة: ${tripData?.vehicle_plate || 'غير متوفر'}

المطلوب:
1. هل يحتوي على طلب إلغاء الرحلة؟
2. هل يحتوي على طلب دفع نقدي/خارج التطبيق؟
3. هل يذكر اسم الكابتن؟
4. ما مستوى الثقة (0-100)؟

أجب بصيغة JSON فقط:
{
  "containsCancelRequest": true/false,
  "containsCashRequest": true/false,
  "captainNameMatch": true/false,
  "confidence": number,
  "keywords": ["keyword1", "keyword2"],
  "summary": "ملخص قصير"
}
`;
            
            const response = await this.groq.chat.completions.create({
                model: 'llama-3.3-70b-versatile',
                messages: [{ role: 'user', content: prompt }],
                temperature: 0.1,
                max_tokens: 500
            });
            
            const content = response.choices[0].message.content;
            
            // Extract JSON from response
            const jsonMatch = content.match(/\{[\s\S]*\}/);
            if (jsonMatch) {
                return JSON.parse(jsonMatch[0]);
            }
            
            return this.keywordBasedAnalysis(ocrText, tripData);
        } catch (error) {
            console.error('[EvidenceValidation] AI analysis failed:', error);
            return this.keywordBasedAnalysis(ocrText, tripData);
        }
    }
    
    /**
     * Keyword-based analysis fallback
     * @param {string} text - Text to analyze
     * @param {Object} tripData - Trip data
     * @returns {Object} - Analysis result
     */
    keywordBasedAnalysis(text, tripData) {
        const lowerText = text.toLowerCase();
        
        const cancelKeywords = ['الغي', 'كنسل', 'cancel', 'الغاء', 'الغى'];
        const cashKeywords = ['كاش', 'نقدي', 'cash', 'فلوس', 'ادفع'];
        
        const containsCancelRequest = cancelKeywords.some(kw => lowerText.includes(kw));
        const containsCashRequest = cashKeywords.some(kw => lowerText.includes(kw));
        
        const detectedKeywords = [];
        [...cancelKeywords, ...cashKeywords].forEach(kw => {
            if (lowerText.includes(kw)) {
                detectedKeywords.push(kw);
            }
        });
        
        let confidence = 30; // Base confidence
        if (containsCancelRequest) confidence += 30;
        if (containsCashRequest) confidence += 30;
        if (tripData?.captain_name && lowerText.includes(tripData.captain_name.toLowerCase())) {
            confidence += 10;
        }
        
        return {
            containsCancelRequest,
            containsCashRequest,
            captainNameMatch: tripData?.captain_name ? lowerText.includes(tripData.captain_name.toLowerCase()) : false,
            confidence: Math.min(confidence, 100),
            keywords: detectedKeywords,
            summary: `Keyword analysis: ${detectedKeywords.length} manipulation keywords found`
        };
    }
    
    /**
     * Validate audio evidence
     * @param {string} filePath - Path to audio file
     * @param {Object} tripData - Trip data
     * @returns {Object} - Validation result
     */
    async validateAudio(filePath, tripData) {
        try {
            // Step 1: Check audio duration
            const duration = await this.getAudioDuration(filePath);
            if (duration < 5) {
                return { 
                    valid: false, 
                    error: 'AUDIO_TOO_SHORT', 
                    message: 'Audio must be at least 5 seconds' 
                };
            }
            
            // Step 2: Speech to text
            const transcription = await this.speechToText(filePath);
            
            if (!transcription.text || transcription.text.trim().length < 5) {
                return {
                    valid: true,
                    confidence: 30,
                    transcription: transcription.text,
                    duration,
                    analysis: { summary: 'No clear speech detected' },
                    needs_manual_review: true
                };
            }
            
            // Step 3: Analyze transcription
            const analysis = await this.analyzeAudioContent(transcription.text, tripData);
            
            return {
                valid: true,
                confidence: analysis.confidence || 50,
                keywords_detected: analysis.keywords || [],
                transcription: transcription.text,
                duration,
                analysis,
                contains_manipulation: analysis.containsCancelRequest || analysis.containsCashRequest
            };
        } catch (error) {
            console.error('[EvidenceValidation] Audio validation error:', error);
            return { 
                valid: true, 
                confidence: 20,
                error: 'VALIDATION_PARTIAL', 
                message: error.message,
                needs_manual_review: true
            };
        }
    }
    
    /**
     * Get audio duration in seconds
     * @param {string} filePath - Path to audio file
     * @returns {number} - Duration in seconds
     */
    async getAudioDuration(filePath) {
        try {
            const { getAudioDurationInSeconds } = require('get-audio-duration');
            return await getAudioDurationInSeconds(filePath);
        } catch (error) {
            console.warn('[EvidenceValidation] Could not get audio duration:', error.message);
            return 10; // Assume valid duration
        }
    }
    
    /**
     * Speech to text using Groq Whisper
     * @param {string} filePath - Path to audio file
     * @returns {Object} - Transcription result
     */
    async speechToText(filePath) {
        if (!this.groq) {
            return { text: '', confidence: 0, error: 'Groq not available' };
        }
        
        try {
            const audioBuffer = fs.readFileSync(filePath);
            
            const transcription = await this.groq.audio.transcriptions.create({
                file: audioBuffer,
                model: 'whisper-large-v3',
                language: 'ar',
                response_format: 'json'
            });
            
            return {
                text: transcription.text,
                confidence: 0.8
            };
        } catch (error) {
            console.error('[EvidenceValidation] Speech-to-text failed:', error);
            return { text: '', confidence: 0, error: error.message };
        }
    }
    
    /**
     * Analyze audio transcription with AI
     * @param {string} transcription - Transcribed text
     * @param {Object} tripData - Trip data
     * @returns {Object} - Analysis result
     */
    async analyzeAudioContent(transcription, tripData) {
        // Use same analysis as image (keyword-based or AI)
        return this.analyzeImageContent(transcription, tripData);
    }
    
    /**
     * Save evidence validation result to database
     * @param {string} evidenceId - Evidence ID
     * @param {Object} validationResult - Validation result
     * @returns {Object} - Save result
     */
    async saveValidationResult(evidenceId, validationResult) {
        try {
            await this.dbExecute(`
                UPDATE evidence_files 
                SET ai_confidence = ?, 
                    validation_result = ?, 
                    validated_at = NOW()
                WHERE id = ?
            `, [
                validationResult.confidence,
                JSON.stringify(validationResult),
                evidenceId
            ]);
            
            return { success: true };
        } catch (error) {
            console.error('[EvidenceValidation] Save result failed:', error);
            return { success: false, error: error.message };
        }
    }
}

module.exports = { 
    EvidenceValidationService,
    MIN_CONFIDENCE_THRESHOLD
};

