// ============================================
// 📎 EVIDENCE HANDLER V3.4.1
// Simple file upload handler - NO AI auto-decisions
// Just saves files and notifies back-office
// ============================================

const fs = require('fs');
const path = require('path');
const { v4: uuidv4 } = require('uuid');

// Supported file types
const SUPPORTED_TYPES = {
    image: ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'],
    audio: ['audio/mp3', 'audio/mpeg', 'audio/m4a', 'audio/wav', 'audio/ogg']
};

// Max file size (10MB)
const MAX_FILE_SIZE = 10 * 1024 * 1024;

// Upload directory
const UPLOAD_DIR = 'uploads/evidence';

class EvidenceHandler {
    constructor(dbQuery, dbExecute, backofficeNotifier) {
        this.dbQuery = dbQuery;
        this.dbExecute = dbExecute;
        this.notifier = backofficeNotifier;
        
        // Ensure upload directory exists
        this.ensureUploadDir();
    }
    
    /**
     * Ensure upload directory exists
     */
    ensureUploadDir() {
        const fullPath = path.resolve(UPLOAD_DIR);
        if (!fs.existsSync(fullPath)) {
            fs.mkdirSync(fullPath, { recursive: true });
            console.log('[EvidenceHandler] Created upload directory:', fullPath);
        }
    }
    
    /**
     * Validate file before upload
     * @param {Object} file - File object from multer
     * @returns {Object} - Validation result
     */
    validateFile(file) {
        if (!file) {
            return { valid: false, error: 'NO_FILE', message_ar: 'لم يتم رفع ملف', message_en: 'No file uploaded' };
        }
        
        // Check file size
        if (file.size > MAX_FILE_SIZE) {
            return { 
                valid: false, 
                error: 'FILE_TOO_LARGE', 
                message_ar: '❌ الملف كبير جداً. الحد الأقصى 10 ميجا.',
                message_en: '❌ File too large. Maximum size is 10MB.'
            };
        }
        
        // Check file type
        const isImage = SUPPORTED_TYPES.image.includes(file.mimetype);
        const isAudio = SUPPORTED_TYPES.audio.includes(file.mimetype);
        
        if (!isImage && !isAudio) {
            return { 
                valid: false, 
                error: 'INVALID_TYPE', 
                message_ar: '❌ نوع الملف غير مدعوم. ارفع صورة أو تسجيل صوتي.',
                message_en: '❌ Unsupported file type. Please upload an image or audio file.'
            };
        }
        
        return { 
            valid: true, 
            type: isImage ? 'image' : 'audio' 
        };
    }
    
    /**
     * Handle evidence upload - SAVES ONLY, NO AI DECISIONS
     * @param {Object} file - File object from multer
     * @param {Object} context - Upload context (userId, tripId, reportId, etc.)
     * @returns {Object} - Upload result
     */
    async handleUpload(file, context) {
        const { userId, tripId, reportId, reportType } = context;
        
        // Validate file
        const validation = this.validateFile(file);
        if (!validation.valid) {
            return validation;
        }
        
        // Generate evidence ID
        const evidenceId = `EVD-${Date.now()}-${uuidv4().slice(0, 8).toUpperCase()}`;
        
        try {
            // Save to database
            await this.dbExecute(`
                INSERT INTO evidence_files 
                (id, user_id, trip_id, report_id, file_path, file_type, file_size, 
                 original_name, status, uploaded_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'PENDING_REVIEW', NOW())
            `, [
                evidenceId,
                userId,
                tripId,
                reportId,
                file.path,
                validation.type,
                file.size,
                file.originalname
            ]);
            
            // Notify back-office (human will review)
            if (this.notifier) {
                await this.notifier.notify({
                    type: 'EVIDENCE_UPLOADED',
                    priority: reportType === 'manipulation' ? 2 : 3,
                    title: '📎 New Evidence Uploaded',
                    data: {
                        evidence_id: evidenceId,
                        user_id: userId,
                        trip_id: tripId,
                        report_id: reportId,
                        file_type: validation.type,
                        file_size: file.size,
                        report_type: reportType
                    },
                    action_required: 'REVIEW_EVIDENCE',
                    suggested_action: 'Review uploaded evidence and take appropriate action'
                });
            }
            
            console.log('[EvidenceHandler] Evidence uploaded:', evidenceId);
            
            return {
                success: true,
                evidence_id: evidenceId,
                file_type: validation.type,
                message_ar: '✅ تم رفع الملف بنجاح!\n\nفريقنا سيراجع البلاغ ويتواصل معك.',
                message_en: '✅ File uploaded successfully!\n\nOur team will review and contact you.'
            };
            
        } catch (error) {
            console.error('[EvidenceHandler] Upload error:', error);
            
            // Try to delete the file if DB save failed
            try {
                if (file.path && fs.existsSync(file.path)) {
                    fs.unlinkSync(file.path);
                }
            } catch (deleteError) {
                console.error('[EvidenceHandler] Failed to delete file:', deleteError);
            }
            
            return {
                success: false,
                error: 'UPLOAD_FAILED',
                message_ar: '❌ حدث خطأ أثناء رفع الملف. حاول مرة أخرى.',
                message_en: '❌ Error uploading file. Please try again.'
            };
        }
    }
    
    /**
     * Get evidence by ID
     * @param {string} evidenceId - Evidence ID
     * @returns {Object} - Evidence data
     */
    async getEvidence(evidenceId) {
        try {
            const results = await this.dbQuery(`
                SELECT * FROM evidence_files WHERE id = ?
            `, [evidenceId]);
            
            if (results.length === 0) {
                return { found: false };
            }
            
            return { found: true, evidence: results[0] };
        } catch (error) {
            console.error('[EvidenceHandler] Get evidence error:', error);
            return { found: false, error: error.message };
        }
    }
    
    /**
     * Get all evidence for a report
     * @param {string} reportId - Report ID
     * @returns {Array} - Evidence files
     */
    async getEvidenceByReport(reportId) {
        try {
            const results = await this.dbQuery(`
                SELECT * FROM evidence_files 
                WHERE report_id = ?
                ORDER BY uploaded_at DESC
            `, [reportId]);
            
            return results || [];
        } catch (error) {
            console.error('[EvidenceHandler] Get evidence by report error:', error);
            return [];
        }
    }
    
    /**
     * Update evidence status (called by back-office)
     * @param {string} evidenceId - Evidence ID
     * @param {string} status - New status
     * @param {string} reviewNotes - Review notes
     * @returns {Object} - Update result
     */
    async updateEvidenceStatus(evidenceId, status, reviewNotes = null) {
        try {
            await this.dbExecute(`
                UPDATE evidence_files 
                SET status = ?, review_notes = ?, reviewed_at = NOW()
                WHERE id = ?
            `, [status, reviewNotes, evidenceId]);
            
            return { success: true };
        } catch (error) {
            console.error('[EvidenceHandler] Update status error:', error);
            return { success: false, error: error.message };
        }
    }
    
    /**
     * Delete evidence file
     * @param {string} evidenceId - Evidence ID
     * @returns {Object} - Delete result
     */
    async deleteEvidence(evidenceId) {
        try {
            // Get file path first
            const evidence = await this.getEvidence(evidenceId);
            if (!evidence.found) {
                return { success: false, error: 'NOT_FOUND' };
            }
            
            // Delete from filesystem
            if (evidence.evidence.file_path && fs.existsSync(evidence.evidence.file_path)) {
                fs.unlinkSync(evidence.evidence.file_path);
            }
            
            // Delete from database
            await this.dbExecute(`
                DELETE FROM evidence_files WHERE id = ?
            `, [evidenceId]);
            
            return { success: true };
        } catch (error) {
            console.error('[EvidenceHandler] Delete error:', error);
            return { success: false, error: error.message };
        }
    }
    
    /**
     * Get upload instructions for user
     * @param {string} type - Evidence type ('image' or 'audio')
     * @param {string} language - Language code
     * @returns {Object} - Instructions response
     */
    getUploadInstructions(type, language = 'ar') {
        const instructions = {
            image: {
                ar: {
                    message: '📸 ارفع صورة توضح المشكلة.\n\n✅ صورة واضحة\n✅ تظهر المحادثة أو المشكلة\n❌ بدون تعديل\n\n📎 اضغط لرفع الصورة:',
                    quick_replies: ['📎 رفع صورة', '❌ إلغاء']
                },
                en: {
                    message: '📸 Upload an image showing the issue.\n\n✅ Clear image\n✅ Shows conversation or issue\n❌ No editing\n\n📎 Click to upload:',
                    quick_replies: ['📎 Upload Image', '❌ Cancel']
                }
            },
            audio: {
                ar: {
                    message: '🎙️ ارفع تسجيل صوتي.\n\n✅ مدة لا تقل عن 5 ثواني\n✅ صوت واضح\n❌ بدون تعديل\n\n📎 اضغط لرفع التسجيل:',
                    quick_replies: ['📎 رفع تسجيل', '❌ إلغاء']
                },
                en: {
                    message: '🎙️ Upload an audio recording.\n\n✅ At least 5 seconds\n✅ Clear audio\n❌ No editing\n\n📎 Click to upload:',
                    quick_replies: ['📎 Upload Recording', '❌ Cancel']
                }
            }
        };
        
        const typeInstructions = instructions[type] || instructions.image;
        return typeInstructions[language] || typeInstructions.ar;
    }
    
    /**
     * Get success response after upload
     * @param {string} evidenceId - Evidence ID
     * @param {string} language - Language code
     * @returns {Object} - Success response
     */
    getUploadSuccessResponse(evidenceId, language = 'ar') {
        return {
            ar: {
                message: `✅ تم رفع الملف بنجاح!\n\n📋 رقم الدليل: ${evidenceId}\n\nفريقنا سيراجع البلاغ ويتواصل معك قريباً.`,
                quick_replies: ['🏠 القائمة الرئيسية', '🚗 احجز رحلة جديدة']
            },
            en: {
                message: `✅ File uploaded successfully!\n\n📋 Evidence ID: ${evidenceId}\n\nOur team will review and contact you soon.`,
                quick_replies: ['🏠 Main Menu', '🚗 Book New Ride']
            }
        }[language] || {
            message: `✅ تم رفع الملف بنجاح!\n\n📋 رقم الدليل: ${evidenceId}`,
            quick_replies: ['🏠 القائمة الرئيسية']
        };
    }
}

module.exports = { 
    EvidenceHandler,
    SUPPORTED_TYPES,
    MAX_FILE_SIZE,
    UPLOAD_DIR
};

