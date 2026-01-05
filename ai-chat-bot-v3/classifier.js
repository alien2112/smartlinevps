// ============================================
// 🧠 HYBRID INTENT CLASSIFIER (V3.1 Enhanced)
// ============================================

const natural = require('natural');
const Groq = require('groq-sdk');

// Intent definitions with patterns and examples
const INTENT_DEFINITIONS = {
  // Booking Intents
  BOOK_TRIP: {
    patterns: [
      /\b(book|ride|trip|go|take me|need a car|pickup|drop|travel)\b/i,
      /\b(ahjez|awsal|awza|roh|wadini|khodni|3ayez)\b/i,
      /\b(احجز|رحلة|عايز|وصلني|خدني|روح)\b/i
    ],
    examples: ['book a ride', 'i need a car', 'take me to work', 'عايز اروح', 'وصلني'],
    priority: 1
  },

  CANCEL_TRIP: {
    patterns: [
      /\b(cancel|stop|abort|dont want|nevermind)\b/i,
      /\b(elghy|msh 3ayez|khalas|la2)\b/i,
      /\b(الغي|إلغاء|مش عايز|خلاص)\b/i
    ],
    examples: ['cancel', 'cancel my trip', 'stop', 'الغي الرحلة'],
    priority: 2
  },

  TRACK_TRIP: {
    patterns: [
      /\b(where|track|eta|how long|driver|captain)\b/i,
      /\b(fen|feen|wa9al|kam|sa3a)\b/i,
      /\b(فين|وصل|كام|وقت)\b/i
    ],
    examples: ['where is my driver', 'track my ride', 'فين السواق'],
    priority: 3
  },

  SUPPORT: {
    patterns: [
      /\b(help|support|human|agent|talk to|speak|problem|issue)\b/i,
      /\b(mosa3da|moshkela|shakwa|agent)\b/i,
      /\b(مساعدة|مشكلة|شكوى|اتكلم)\b/i
    ],
    examples: ['help', 'i need support', 'talk to human', 'عايز مساعدة'],
    priority: 4
  },

  EMERGENCY: {
    patterns: [
      /\b(emergency|sos|danger|help me|accident|police)\b/i,
      /\b(taware2|khatar|7adsa|nehaya)\b/i,
      /\b(طوارئ|خطر|حادثة|النجدة)\b/i
    ],
    examples: ['emergency', 'help me now', 'accident', 'طوارئ'],
    priority: 0 // Highest priority
  },

  PAYMENT: {
    patterns: [
      /\b(pay|payment|price|cost|fare|money|cash|card)\b/i,
      /\b(floos|flos|kam|daf3|visa|credit)\b/i,
      /\b(فلوس|دفع|كام|السعر|كريدت)\b/i
    ],
    examples: ['how much', 'payment options', 'كام الرحلة'],
    priority: 5
  },

  PROMO_CODE: {
    patterns: [
      /\b(promo|code|discount|offer|coupon)\b/i,
      /\b(kod|khasm|3ard)\b/i,
      /\b(كود|خصم|عرض|كوبون)\b/i
    ],
    examples: ['apply promo', 'i have a code', 'عندي كود خصم'],
    priority: 6
  },

  RATING: {
    patterns: [
      /\b(rate|rating|star|review|feedback)\b/i,
      /\b(t2yim|ra2y|nagma)\b/i,
      /\b(تقييم|رأي|نجمة)\b/i
    ],
    examples: ['rate driver', 'give feedback', 'اديله تقييم'],
    priority: 7
  },

  GREETING: {
    patterns: [
      /^(hi|hello|hey|good morning|good evening|howdy)\b/i,
      /^(ahlan|marhaba|sabah|masa)\b/i,
      /^(اهلا|مرحبا|صباح|مساء|السلام)\b/i
    ],
    examples: ['hi', 'hello', 'مرحبا', 'اهلا'],
    priority: 10
  },

  FAREWELL: {
    patterns: [
      /\b(bye|goodbye|thanks|thank you|see you)\b/i,
      /\b(shokran|ma3 elsalama|yalla bye)\b/i,
      /\b(شكرا|مع السلامة|باي)\b/i
    ],
    examples: ['bye', 'thanks', 'شكرا'],
    priority: 10
  },

  // Captain-specific intents
  CAPTAIN_EARNINGS: {
    patterns: [
      /\b(earning|income|today|made|profit)\b/i,
      /\b(kasabt|arba7|floos|naharda)\b/i,
      /\b(كسبت|أرباح|النهارده|مكسب)\b/i
    ],
    examples: ['my earnings', 'how much did I make', 'كسبت كام'],
    priority: 5,
    userType: 'captain'
  },

  CAPTAIN_NEXT_PICKUP: {
    patterns: [
      /\b(next|pickup|where|order|request)\b/i,
      /\b(gayly|talab|feen|next)\b/i,
      /\b(جايلي|طلب|فين|التالي)\b/i
    ],
    examples: ['next pickup', 'any orders', 'فين الطلب الجاي'],
    priority: 4,
    userType: 'captain'
  }
};

class IntentClassifier {
  constructor() {
    this.classifier = new natural.BayesClassifier();
    this.tokenizer = new natural.WordTokenizer();
    this.stemmer = natural.PorterStemmer;
    this.isTrained = false;
    this.groq = null;

    // Performance metrics
    this.metrics = {
      l1Hits: 0,
      l2Hits: 0,
      l3Hits: 0,
      totalClassifications: 0
    };

    this.initialize();
  }

  async initialize() {
    // Initialize Groq client
    if (process.env.GROQ_API_KEY) {
      this.groq = new Groq({ apiKey: process.env.GROQ_API_KEY });
    }

    // Train the Bayes classifier
    this.trainBayesClassifier();
  }

  trainBayesClassifier() {
    for (const [intent, config] of Object.entries(INTENT_DEFINITIONS)) {
      config.examples.forEach(example => {
        // Add original
        this.classifier.addDocument(example.toLowerCase(), intent);

        // Add stemmed version
        const tokens = this.tokenizer.tokenize(example.toLowerCase());
        const stemmed = tokens.map(t => this.stemmer.stem(t)).join(' ');
        this.classifier.addDocument(stemmed, intent);
      });
    }

    this.classifier.train();
    this.isTrained = true;
    console.log('✅ Intent classifier trained with', Object.keys(INTENT_DEFINITIONS).length, 'intents');
  }

  /**
   * L1: Fast Regex-based classification
   */
  classifyL1(message, userType = 'customer') {
    const normalizedMessage = message.toLowerCase().trim();

    // Sort by priority (lower = higher priority)
    const sortedIntents = Object.entries(INTENT_DEFINITIONS)
      .sort((a, b) => a[1].priority - b[1].priority);

    for (const [intent, config] of sortedIntents) {
      // Skip captain-only intents for customers
      if (config.userType === 'captain' && userType !== 'captain') continue;

      for (const pattern of config.patterns) {
        if (pattern.test(normalizedMessage)) {
          return {
            intent,
            confidence: 0.95,
            source: 'L1_REGEX',
            matchedPattern: pattern.toString()
          };
        }
      }
    }

    return null;
  }

  /**
   * L2: NLP-based classification using Naive Bayes
   */
  classifyL2(message) {
    if (!this.isTrained) return null;

    const normalizedMessage = message.toLowerCase().trim();
    const classifications = this.classifier.getClassifications(normalizedMessage);

    if (classifications.length === 0) return null;

    const top = classifications[0];
    const second = classifications[1];

    // Calculate confidence based on margin between top two
    let confidence = top.value;
    if (second) {
      const margin = top.value - second.value;
      confidence = Math.min(0.9, top.value + (margin * 0.5));
    }

    // Threshold check
    if (confidence < 0.6) return null;

    return {
      intent: top.label,
      confidence: Math.round(confidence * 100) / 100,
      source: 'L2_NLP',
      alternatives: classifications.slice(1, 3).map(c => ({
        intent: c.label,
        confidence: Math.round(c.value * 100) / 100
      }))
    };
  }

  /**
   * L3: LLM-based classification (slowest, most accurate)
   */
  async classifyL3(message, conversationContext = []) {
    if (!this.groq) {
      return {
        intent: 'UNKNOWN',
        confidence: 0,
        source: 'L3_LLM',
        error: 'LLM not configured'
      };
    }

    const intentList = Object.keys(INTENT_DEFINITIONS).join(', ');

    const systemPrompt = `You are an intent classifier for a ride-hailing chatbot.
Classify the user's message into ONE of these intents: ${intentList}, or UNKNOWN if none match.

Respond with ONLY a JSON object in this exact format:
{"intent": "INTENT_NAME", "confidence": 0.85, "reasoning": "brief explanation"}

Rules:
- confidence should be between 0.0 and 1.0
- Use UNKNOWN only if the message truly doesn't fit any intent
- Consider the conversation context provided`;

    try {
      const messages = [
        { role: 'system', content: systemPrompt }
      ];

      // Add conversation context (last 3 messages)
      const recentContext = conversationContext.slice(-3);
      for (const ctx of recentContext) {
        messages.push({
          role: ctx.role === 'user' ? 'user' : 'assistant',
          content: ctx.content
        });
      }

      messages.push({ role: 'user', content: message });

      const completion = await this.groq.chat.completions.create({
        model: 'llama-3.3-70b-versatile',
        messages,
        max_tokens: 150,
        temperature: 0.1 // Low temperature for consistent classification
      });

      const responseText = completion.choices[0]?.message?.content?.trim();

      // Parse JSON response
      const jsonMatch = responseText.match(/\{[\s\S]*\}/);
      if (jsonMatch) {
        const parsed = JSON.parse(jsonMatch[0]);
        return {
          intent: parsed.intent || 'UNKNOWN',
          confidence: parsed.confidence || 0.7,
          source: 'L3_LLM',
          reasoning: parsed.reasoning
        };
      }

      return {
        intent: 'UNKNOWN',
        confidence: 0.5,
        source: 'L3_LLM',
        raw: responseText
      };

    } catch (error) {
      console.error('[Classifier L3] LLM error:', error.message);
      return {
        intent: 'UNKNOWN',
        confidence: 0,
        source: 'L3_LLM',
        error: error.message
      };
    }
  }

  /**
   * Check for ambiguous intent (when top 2 intents have similar confidence)
   * @param {Object} result - Classification result
   * @param {Object} secondResult - Second best result (optional)
   * @param {string} language - User language
   * @returns {Object|null} Ambiguous intent result or null
   */
  _checkAmbiguousIntent(result, secondResult, language = 'en') {
    if (!result || !secondResult) return null;

    const confidenceDiff = Math.abs(result.confidence - secondResult.confidence);
    const AMBIGUOUS_THRESHOLD = 0.1;

    if (confidenceDiff < AMBIGUOUS_THRESHOLD && result.confidence > 0.6) {
      // Ambiguous - ask user to clarify
      const messages = {
        en: {
          book_vs_status: 'Do you mean book a trip or ask about a previous trip?',
          cancel_vs_support: 'Do you want to cancel a trip or get help?',
          general: 'I\'m not sure what you mean. Could you clarify?'
        },
        ar: {
          book_vs_status: 'هل تقصد حجز رحلة أم الاستفسار عن رحلة سابقة؟',
          cancel_vs_support: 'عايز تلغي رحلة ولا تحتاج مساعدة؟',
          general: 'مش فاهم. ممكن توضح أكتر؟'
        }
      };

      const lang = language === 'ar' ? 'ar' : 'en';
      let clarificationMessage = messages[lang].general;

      // Specific clarification based on intents
      const intentPair = [result.intent, secondResult.intent].sort().join('_');
      if (intentPair.includes('BOOK_TRIP') && intentPair.includes('TRIP_STATUS')) {
        clarificationMessage = messages[lang].book_vs_status;
      } else if (intentPair.includes('CANCEL_TRIP') && intentPair.includes('SUPPORT')) {
        clarificationMessage = messages[lang].cancel_vs_support;
      }

      return {
        intent: 'AMBIGUOUS',
        confidence: (result.confidence + secondResult.confidence) / 2,
        source: result.source,
        candidates: [
          { intent: result.intent, confidence: result.confidence },
          { intent: secondResult.intent, confidence: secondResult.confidence }
        ],
        action: 'clarify',
        message: clarificationMessage,
        quick_replies: [
          result.intent.replace(/_/g, ' '),
          secondResult.intent.replace(/_/g, ' ')
        ]
      };
    }

    return null;
  }

  /**
   * Main classification method - runs hybrid pipeline
   */
  async classify(message, options = {}) {
    const {
      userType = 'customer',
      language = 'en',
      conversationContext = [],
      skipL3 = false
    } = options;

    this.metrics.totalClassifications++;
    const startTime = Date.now();

    // L1: Regex (< 1ms)
    const l1Result = this.classifyL1(message, userType);
    if (l1Result && l1Result.confidence >= 0.9) {
      this.metrics.l1Hits++;
      return {
        ...l1Result,
        latency: Date.now() - startTime
      };
    }

    // L2: NLP (~5ms)
    const l2Result = this.classifyL2(message);
    if (l2Result && l2Result.confidence >= 0.75) {
      // Check for ambiguous intent
      if (l2Result.alternatives && l2Result.alternatives.length > 0) {
        const ambiguous = this._checkAmbiguousIntent(
          l2Result,
          { intent: l2Result.alternatives[0].intent, confidence: l2Result.alternatives[0].confidence },
          language
        );
        if (ambiguous) {
          return {
            ...ambiguous,
            latency: Date.now() - startTime
          };
        }
      }

      this.metrics.l2Hits++;
      return {
        ...l2Result,
        latency: Date.now() - startTime
      };
    }

    // L3: LLM (~500-2000ms)
    if (!skipL3) {
      const l3Result = await this.classifyL3(message, conversationContext);

      // Check for ambiguous intent (if we have alternatives from L2)
      if (l2Result && l2Result.alternatives && l2Result.alternatives.length > 0) {
        const ambiguous = this._checkAmbiguousIntent(
          l3Result,
          { intent: l2Result.intent, confidence: l2Result.confidence },
          language
        );
        if (ambiguous) {
          this.metrics.l3Hits++;
          return {
            ...ambiguous,
            latency: Date.now() - startTime
          };
        }
      }

      this.metrics.l3Hits++;
      return {
        ...l3Result,
        latency: Date.now() - startTime
      };
    }

    // Fallback if L3 skipped
    return {
      intent: l2Result?.intent || 'UNKNOWN',
      confidence: l2Result?.confidence || 0,
      source: 'FALLBACK',
      latency: Date.now() - startTime
    };
  }

  /**
   * Get classification metrics
   */
  getMetrics() {
    const total = this.metrics.totalClassifications || 1;
    return {
      ...this.metrics,
      l1Rate: Math.round((this.metrics.l1Hits / total) * 100) + '%',
      l2Rate: Math.round((this.metrics.l2Hits / total) * 100) + '%',
      l3Rate: Math.round((this.metrics.l3Hits / total) * 100) + '%'
    };
  }

  /**
   * Add new training example dynamically
   */
  addExample(intent, example) {
    if (!INTENT_DEFINITIONS[intent]) {
      console.warn(`[Classifier] Unknown intent: ${intent}`);
      return false;
    }

    this.classifier.addDocument(example.toLowerCase(), intent);
    this.classifier.train();
    return true;
  }

  /**
   * Get all available intents
   */
  getIntents() {
    return Object.keys(INTENT_DEFINITIONS);
  }
}

module.exports = new IntentClassifier();