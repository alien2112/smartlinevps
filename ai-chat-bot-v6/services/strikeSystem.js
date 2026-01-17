// ============================================
// ⚠️ STRIKE SYSTEM V3.4.1 - ADVISORY ONLY
// NO AUTO-APPLY - Human decides, human applies
// Chatbot only notifies back-office
// ============================================

const { v4: uuidv4 } = require('uuid');

// Strike Rules Configuration (for back-office reference)
const STRIKE_RULES = {
    WARNING: {
        level: 0,
        action: 'WARNING',
        duration: null,
        message_ar: '⚠️ تحذير: تم رصد مخالفة لسياسات سمارت لاين.',
        message_en: '⚠️ Warning: A policy violation has been detected.'
    },
    STRIKE_1: {
        level: 1,
        action: 'EARNINGS_HOLD',
        duration_hours: 24,
        message_ar: '⛔ إنذار أول: تجميد الأرباح لمدة 24 ساعة.',
        message_en: '⛔ Strike 1: Earnings on hold for 24 hours.'
    },
    STRIKE_2: {
        level: 2,
        action: 'SUSPENSION',
        duration_hours: 72,
        message_ar: '🚫 إنذار ثاني: إيقاف الحساب لمدة 3 أيام.',
        message_en: '🚫 Strike 2: Account suspended for 3 days.'
    },
    STRIKE_3: {
        level: 3,
        action: 'PERMANENT_BAN',
        duration: null,
        requires_review: true,
        message_ar: '❌ إنذار نهائي: إيقاف الحساب نهائياً.',
        message_en: '❌ Final Strike: Account permanently banned.'
    }
};

// Strike expiry period (strikes expire after this many days)
const STRIKE_EXPIRY_DAYS = 90;

/**
 * Strike Service - ADVISORY ONLY
 * Does NOT auto-apply strikes
 * Only provides recommendations and notifies back-office
 */
class StrikeService {
    constructor(dbQuery, dbExecute, backofficeNotifier) {
        this.dbQuery = dbQuery;
        this.dbExecute = dbExecute;
        this.notifier = backofficeNotifier;
    }
    
    /**
     * Analyze report and provide RECOMMENDATION (not auto-apply)
     * @param {string} reportId - Report ID
     * @param {string} captainId - Captain ID
     * @param {Object} reportData - Report data
     * @returns {Object} - Recommendation for back-office
     */
    async analyzeAndRecommend(reportId, captainId, reportData) {
        try {
            // Get captain's current strike history
            const strikeHistory = await this.getCaptainStrikes(captainId);
            const activeStrikes = this.getActiveStrikes(strikeHistory);
            
            // Determine recommended action
            const recommendation = this.getRecommendation(activeStrikes.length, reportData);
            
            // Notify back-office with recommendation (HUMAN DECIDES)
            if (this.notifier) {
                await this.notifier.notify({
                    type: 'STRIKE_RECOMMENDATION',
                    priority: recommendation.severity === 'HIGH' ? 2 : 3,
                    title: '⚠️ Strike Recommendation - Human Review Required',
                    data: {
                        report_id: reportId,
                        captain_id: captainId,
                        current_strikes: activeStrikes.length,
                        total_strikes: strikeHistory.length,
                        recommended_action: recommendation.action,
                        recommended_level: recommendation.level,
                        severity: recommendation.severity,
                        report_type: reportData.type,
                        report_category: reportData.category
                    },
                    action_required: 'HUMAN_REVIEW',
                    suggested_action: recommendation.suggested_action,
                    auto_apply: false // NEVER auto-apply
                });
            }
            
            console.log('[StrikeService] Recommendation sent to back-office:', reportId);
            
            return {
                success: true,
                recommendation,
                current_strikes: activeStrikes.length,
                message: 'Recommendation sent to back-office for human review'
            };
            
        } catch (error) {
            console.error('[StrikeService] Analyze error:', error);
            return {
                success: false,
                error: error.message
            };
        }
    }
    
    /**
     * Get recommendation based on strike history and report
     * @param {number} activeStrikeCount - Number of active strikes
     * @param {Object} reportData - Report data
     * @returns {Object} - Recommendation
     */
    getRecommendation(activeStrikeCount, reportData) {
        // Determine severity based on report type
        const highSeverityTypes = ['manipulation', 'harassment', 'threat', 'unsafe_driving'];
        const isHighSeverity = highSeverityTypes.includes(reportData.type) || 
                               highSeverityTypes.includes(reportData.category);
        
        // Get recommended strike level
        let recommendedLevel;
        if (activeStrikeCount === 0) {
            recommendedLevel = isHighSeverity ? STRIKE_RULES.STRIKE_1 : STRIKE_RULES.WARNING;
        } else if (activeStrikeCount === 1) {
            recommendedLevel = isHighSeverity ? STRIKE_RULES.STRIKE_2 : STRIKE_RULES.STRIKE_1;
        } else if (activeStrikeCount === 2) {
            recommendedLevel = STRIKE_RULES.STRIKE_2;
        } else {
            recommendedLevel = STRIKE_RULES.STRIKE_3;
        }
        
        return {
            action: recommendedLevel.action,
            level: recommendedLevel.level,
            severity: isHighSeverity ? 'HIGH' : 'MEDIUM',
            duration_hours: recommendedLevel.duration_hours || null,
            requires_review: recommendedLevel.requires_review || false,
            message_ar: recommendedLevel.message_ar,
            message_en: recommendedLevel.message_en,
            suggested_action: this.getSuggestedAction(recommendedLevel.action, reportData)
        };
    }
    
    /**
     * Get suggested action text for back-office
     */
    getSuggestedAction(action, reportData) {
        const actions = {
            'WARNING': 'Review report details. If valid, send warning to captain via admin panel.',
            'EARNINGS_HOLD': 'Review evidence carefully. If confirmed, apply 24-hour earnings hold via admin panel.',
            'SUSPENSION': 'Serious violation. Review all evidence, contact customer if needed. Apply 3-day suspension via admin panel.',
            'PERMANENT_BAN': 'CRITICAL: Multiple violations. Full review required. Contact captain for their side. Apply permanent ban only after thorough review.'
        };
        
        return actions[action] || 'Review report and take appropriate action via admin panel.';
    }
    
    /**
     * Get captain's strike history
     * @param {string} captainId - Captain ID
     * @returns {Array} - Strike history
     */
    async getCaptainStrikes(captainId) {
        try {
            const rows = await this.dbQuery(`
                SELECT * FROM captain_strikes 
                WHERE captain_id = ? 
                ORDER BY created_at DESC
            `, [captainId]);
            return rows || [];
        } catch (error) {
            console.error('[StrikeService] Get strikes failed:', error);
            return [];
        }
    }
    
    /**
     * Filter active (non-expired) strikes
     * @param {Array} strikes - All strikes
     * @returns {Array} - Active strikes only
     */
    getActiveStrikes(strikes) {
        const expiryDate = new Date();
        expiryDate.setDate(expiryDate.getDate() - STRIKE_EXPIRY_DAYS);
        
        return strikes.filter(strike => {
            const strikeDate = new Date(strike.created_at);
            return strikeDate > expiryDate && strike.status === 'ACTIVE';
        });
    }
    
    /**
     * Get strike summary for display
     * @param {string} captainId - Captain ID
     * @returns {Object} - Strike summary
     */
    async getStrikeSummary(captainId) {
        const allStrikes = await this.getCaptainStrikes(captainId);
        const activeStrikes = this.getActiveStrikes(allStrikes);
        
        return {
            total_strikes: allStrikes.length,
            active_strikes: activeStrikes.length,
            strikes_until_ban: Math.max(0, 3 - activeStrikes.length),
            current_status: this.getCurrentStatus(activeStrikes.length),
            history: allStrikes.map(s => ({
                id: s.id,
                level: s.level,
                action: s.action,
                status: s.status,
                created_at: s.created_at
            }))
        };
    }
    
    /**
     * Get current account status based on strike count
     */
    getCurrentStatus(activeStrikeCount) {
        if (activeStrikeCount === 0) return 'GOOD_STANDING';
        if (activeStrikeCount === 1) return 'WARNING';
        if (activeStrikeCount === 2) return 'AT_RISK';
        return 'CRITICAL';
    }
    
    // ============================================
    // BACK-OFFICE ONLY METHODS
    // These should only be called from admin panel
    // ============================================
    
    /**
     * Apply strike - BACK-OFFICE ONLY
     * This method should only be called from admin panel after human review
     * @param {string} captainId - Captain ID
     * @param {string} reportId - Report ID
     * @param {number} level - Strike level (0-3)
     * @param {string} adminId - Admin who applied the strike
     * @param {string} notes - Admin notes
     * @returns {Object} - Result
     */
    async applyStrike_ADMIN_ONLY(captainId, reportId, level, adminId, notes = '') {
        const strikeRule = this.getStrikeRuleByLevel(level);
        const strikeId = `STR-${Date.now()}-${uuidv4().slice(0, 8).toUpperCase()}`;
        
        const expiresAt = strikeRule.duration_hours 
            ? new Date(Date.now() + strikeRule.duration_hours * 60 * 60 * 1000)
            : null;
        
        try {
            // Record strike
            await this.dbExecute(`
                INSERT INTO captain_strikes 
                (id, captain_id, report_id, level, action, applied_by, admin_notes, 
                 expires_at, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVE', NOW())
            `, [
                strikeId,
                captainId,
                reportId,
                level,
                strikeRule.action,
                adminId,
                notes,
                expiresAt
            ]);
            
            // Execute the action
            await this.executeStrikeAction_ADMIN_ONLY(captainId, strikeRule);
            
            // Send notification to captain
            await this.notifyCaptain(captainId, strikeRule);
            
            console.log('[StrikeService] Strike applied by admin:', strikeId, 'Admin:', adminId);
            
            return {
                success: true,
                strike_id: strikeId,
                action: strikeRule.action,
                expires_at: expiresAt
            };
            
        } catch (error) {
            console.error('[StrikeService] Apply strike failed:', error);
            return { success: false, error: error.message };
        }
    }
    
    /**
     * Execute strike action - BACK-OFFICE ONLY
     */
    async executeStrikeAction_ADMIN_ONLY(captainId, strikeRule) {
        try {
            switch (strikeRule.action) {
                case 'WARNING':
                    // Just notification, no account action
                    break;
                    
                case 'EARNINGS_HOLD':
                    await this.dbExecute(`
                        UPDATE drivers 
                        SET earnings_hold = 1, 
                            hold_expires_at = DATE_ADD(NOW(), INTERVAL ? HOUR)
                        WHERE id = ? OR user_id = ?
                    `, [strikeRule.duration_hours, captainId, captainId]);
                    break;
                    
                case 'SUSPENSION':
                    await this.dbExecute(`
                        UPDATE drivers 
                        SET is_suspended = 1, 
                            suspension_expires_at = DATE_ADD(NOW(), INTERVAL ? HOUR)
                        WHERE id = ? OR user_id = ?
                    `, [strikeRule.duration_hours, captainId, captainId]);
                    break;
                    
                case 'PERMANENT_BAN':
                    await this.dbExecute(`
                        UPDATE drivers 
                        SET is_banned = 1, banned_at = NOW(), ban_reason = 'Multiple policy violations'
                        WHERE id = ? OR user_id = ?
                    `, [captainId, captainId]);
                    break;
            }
        } catch (error) {
            console.error('[StrikeService] Execute action failed:', error);
        }
    }
    
    /**
     * Get strike rule by level
     */
    getStrikeRuleByLevel(level) {
        switch (level) {
            case 0: return STRIKE_RULES.WARNING;
            case 1: return STRIKE_RULES.STRIKE_1;
            case 2: return STRIKE_RULES.STRIKE_2;
            default: return STRIKE_RULES.STRIKE_3;
        }
    }
    
    /**
     * Send notification to captain about strike
     */
    async notifyCaptain(captainId, strikeRule) {
        try {
            const captains = await this.dbQuery(`
                SELECT preferred_language FROM drivers WHERE id = ? OR user_id = ?
            `, [captainId, captainId]);
            
            const language = captains?.[0]?.preferred_language || 'ar';
            const message = language === 'en' ? strikeRule.message_en : strikeRule.message_ar;
            
            await this.dbExecute(`
                INSERT INTO notifications 
                (user_id, type, title, message, created_at)
                VALUES (?, 'STRIKE', ?, ?, NOW())
            `, [
                captainId,
                language === 'en' ? 'Policy Violation' : 'مخالفة السياسات',
                message
            ]);
        } catch (error) {
            console.error('[StrikeService] Notify captain failed:', error);
        }
    }
}

module.exports = { 
    StrikeService, 
    STRIKE_RULES, 
    STRIKE_EXPIRY_DAYS
};
