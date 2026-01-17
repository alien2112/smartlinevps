// ============================================
// 📢 BACK-OFFICE NOTIFIER V3.4.1
// Central notification service for all alerts
// Supports webhook, socket, and logging
// ============================================

const NOTIFICATION_TYPES = {
    // Critical - Immediate action
    EMERGENCY: 'emergency',
    
    // High priority - Review soon
    MANIPULATION_ALERT: 'manipulation_alert',
    STRIKE_RECOMMENDATION: 'strike_recommendation',
    ISSUE_REPORT: 'issue_report',
    
    // Medium priority - Regular review
    EVIDENCE_UPLOADED: 'evidence_uploaded',
    FRAUD_CHECK_WARNING: 'fraud_check_warning',
    CUSTOMER_COMPLAINT: 'customer_complaint',
    
    // Low priority - Log only
    FEEDBACK: 'feedback',
    ESCALATION: 'escalation',
    TECHNICAL_ISSUE: 'technical_issue'
};

const PRIORITY_LEVELS = {
    CRITICAL: 1,  // Immediate attention required
    HIGH: 2,      // Review within 15 minutes
    MEDIUM: 3,    // Review within 1 hour
    LOW: 4        // Review when available
};

class BackofficeNotifier {
    constructor(config = {}) {
        this.webhookUrl = config.webhookUrl || process.env.BACKOFFICE_WEBHOOK_URL;
        this.socketIo = config.socketIo || null;
        this.enabled = config.enabled !== false;
        this.logOnly = config.logOnly || !this.webhookUrl;
        this.notificationQueue = [];
        this.isProcessing = false;
    }
    
    /**
     * Main notification method
     * @param {Object} notification - Notification data
     * @returns {Object} - Result
     */
    async notify(notification) {
        const enrichedNotification = {
            id: `NOTIF-${Date.now()}-${Math.random().toString(36).substr(2, 6).toUpperCase()}`,
            timestamp: new Date().toISOString(),
            source: 'chatbot',
            version: '3.4.1',
            ...notification
        };
        
        // Always log
        this.logNotification(enrichedNotification);
        
        if (!this.enabled) {
            return { sent: false, reason: 'disabled', notification_id: enrichedNotification.id };
        }
        
        // Send via available channels
        const results = await Promise.allSettled([
            this.sendViaSocket(enrichedNotification),
            this.sendViaWebhook(enrichedNotification)
        ]);
        
        return {
            sent: true,
            notification_id: enrichedNotification.id,
            channels: {
                socket: results[0].status === 'fulfilled' ? results[0].value : false,
                webhook: results[1].status === 'fulfilled' ? results[1].value : false
            }
        };
    }
    
    /**
     * Log notification (always happens)
     */
    logNotification(notification) {
        const priorityLabel = ['', 'CRITICAL', 'HIGH', 'MEDIUM', 'LOW'][notification.priority] || 'UNKNOWN';
        console.log(`[BackOffice] [${priorityLabel}] ${notification.type}:`, JSON.stringify({
            id: notification.id,
            type: notification.type,
            title: notification.title,
            data: notification.data,
            action_required: notification.action_required
        }, null, 2));
    }
    
    /**
     * Send via Socket.IO (real-time)
     */
    async sendViaSocket(notification) {
        if (!this.socketIo) return false;
        
        try {
            // Emit to back-office room
            this.socketIo.to('backoffice').emit('notification', notification);
            
            // Emit to specific channel based on priority
            if (notification.priority === PRIORITY_LEVELS.CRITICAL) {
                this.socketIo.to('backoffice').emit('critical_alert', notification);
            }
            
            return true;
        } catch (error) {
            console.error('[BackOffice] Socket error:', error.message);
            return false;
        }
    }
    
    /**
     * Send via webhook
     */
    async sendViaWebhook(notification) {
        if (!this.webhookUrl || this.logOnly) return false;
        
        try {
            const response = await fetch(this.webhookUrl, {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-Notification-Type': notification.type,
                    'X-Priority': String(notification.priority)
                },
                body: JSON.stringify(notification),
                timeout: 5000
            });
            
            return response.ok;
        } catch (error) {
            console.error('[BackOffice] Webhook error:', error.message);
            return false;
        }
    }
    
    // ============================================
    // CONVENIENCE METHODS
    // ============================================
    
    /**
     * 🚨 Emergency Alert - CRITICAL
     */
    async sendEmergencyAlert(data) {
        return this.notify({
            type: NOTIFICATION_TYPES.EMERGENCY,
            priority: PRIORITY_LEVELS.CRITICAL,
            title: '🚨 EMERGENCY ALERT',
            data: {
                alert_id: data.alertId,
                user_id: data.userId,
                trip_id: data.tripId,
                emergency_type: data.emergencyType,
                user_phone: data.phone,
                user_location: data.location
            },
            action_required: 'IMMEDIATE_CONTACT',
            auto_escalate: true,
            suggested_action: 'Contact customer immediately. Check their safety.'
        });
    }
    
    /**
     * ⚠️ Manipulation Alert - HIGH
     */
    async sendManipulationAlert(data) {
        return this.notify({
            type: NOTIFICATION_TYPES.MANIPULATION_ALERT,
            priority: PRIORITY_LEVELS.HIGH,
            title: '⚠️ Manipulation Detected',
            data: {
                user_id: data.userId,
                trip_id: data.tripId,
                captain_id: data.captainId,
                captain_name: data.captainName,
                manipulation_type: data.type,
                keywords_detected: data.keywords,
                has_evidence: data.hasEvidence
            },
            action_required: 'REVIEW_AND_DECIDE',
            auto_apply: false,
            suggested_action: 'Review report. Contact customer if needed. Decide on strike via admin panel.'
        });
    }
    
    /**
     * 📋 Issue Report - MEDIUM/HIGH
     */
    async sendIssueReport(data) {
        const priority = data.category === 'emergency' ? PRIORITY_LEVELS.CRITICAL :
                        data.category === 'captain' ? PRIORITY_LEVELS.HIGH :
                        PRIORITY_LEVELS.MEDIUM;
        
        return this.notify({
            type: NOTIFICATION_TYPES.ISSUE_REPORT,
            priority,
            title: `📋 Issue Report: ${data.category}`,
            data: {
                report_id: data.reportId,
                user_id: data.userId,
                trip_id: data.tripId,
                captain_id: data.captainId,
                category: data.category,
                subcategory: data.subcategory,
                description: data.description,
                has_evidence: data.hasEvidence
            },
            action_required: priority <= 2 ? 'URGENT_REVIEW' : 'REVIEW',
            suggested_action: data.suggestedAction || 'Review report and take appropriate action.'
        });
    }
    
    /**
     * 📎 Evidence Uploaded - MEDIUM
     */
    async sendEvidenceUploaded(data) {
        return this.notify({
            type: NOTIFICATION_TYPES.EVIDENCE_UPLOADED,
            priority: data.reportType === 'manipulation' ? PRIORITY_LEVELS.HIGH : PRIORITY_LEVELS.MEDIUM,
            title: '📎 New Evidence Uploaded',
            data: {
                evidence_id: data.evidenceId,
                report_id: data.reportId,
                user_id: data.userId,
                file_type: data.fileType,
                report_type: data.reportType
            },
            action_required: 'REVIEW_EVIDENCE',
            suggested_action: 'Review uploaded evidence and update report status.'
        });
    }
    
    /**
     * ⚠️ Strike Recommendation - HIGH
     */
    async sendStrikeRecommendation(data) {
        return this.notify({
            type: NOTIFICATION_TYPES.STRIKE_RECOMMENDATION,
            priority: PRIORITY_LEVELS.HIGH,
            title: '⚠️ Strike Recommendation',
            data: {
                report_id: data.reportId,
                captain_id: data.captainId,
                current_strikes: data.currentStrikes,
                recommended_action: data.recommendedAction,
                recommended_level: data.recommendedLevel,
                severity: data.severity
            },
            action_required: 'HUMAN_DECISION',
            auto_apply: false,
            suggested_action: `Review report. Recommended: ${data.recommendedAction}. Apply via admin panel if confirmed.`
        });
    }
    
    /**
     * 🛡️ Fraud Warning - MEDIUM
     */
    async sendFraudWarning(data) {
        return this.notify({
            type: NOTIFICATION_TYPES.FRAUD_CHECK_WARNING,
            priority: data.riskLevel === 'HIGH' ? PRIORITY_LEVELS.HIGH : PRIORITY_LEVELS.MEDIUM,
            title: `🛡️ Fraud Warning (${data.riskLevel} Risk)`,
            data: {
                user_id: data.userId,
                captain_id: data.captainId,
                risk_level: data.riskLevel,
                flags: data.flags,
                behavior_score: data.behaviorScore
            },
            action_required: 'REVIEW_IF_NEEDED',
            auto_reject: false,
            suggested_action: 'Review report with extra scrutiny. Do not auto-reject.'
        });
    }
    
    /**
     * ⭐ Feedback/Rating - LOW
     */
    async sendFeedback(data) {
        const priority = data.rating <= 2 ? PRIORITY_LEVELS.MEDIUM : PRIORITY_LEVELS.LOW;
        
        return this.notify({
            type: NOTIFICATION_TYPES.FEEDBACK,
            priority,
            title: data.rating <= 2 ? '⭐ Low Rating Alert' : '⭐ Customer Feedback',
            data: {
                user_id: data.userId,
                trip_id: data.tripId,
                captain_id: data.captainId,
                rating: data.rating,
                feedback: data.feedback
            },
            action_required: data.rating <= 2 ? 'REVIEW' : 'LOG_ONLY',
            suggested_action: data.rating <= 2 ? 'Review trip and contact customer if needed.' : null
        });
    }
    
    /**
     * 🔺 Escalation Request - HIGH
     */
    async sendEscalation(data) {
        return this.notify({
            type: NOTIFICATION_TYPES.ESCALATION,
            priority: PRIORITY_LEVELS.HIGH,
            title: '🔺 Escalation Request',
            data: {
                user_id: data.userId,
                trip_id: data.tripId,
                reason: data.reason,
                conversation_summary: data.summary,
                user_phone: data.phone
            },
            action_required: 'CALLBACK_REQUIRED',
            suggested_action: 'Call customer within 15 minutes.'
        });
    }
    
    /**
     * ⚙️ Technical Issue - LOW
     */
    async sendTechnicalIssue(data) {
        return this.notify({
            type: NOTIFICATION_TYPES.TECHNICAL_ISSUE,
            priority: PRIORITY_LEVELS.LOW,
            title: '⚙️ Technical Issue',
            data: {
                user_id: data.userId,
                issue_type: data.issueType,
                description: data.description,
                device_info: data.deviceInfo
            },
            action_required: 'LOG_AND_MONITOR',
            suggested_action: 'Forward to technical team if pattern emerges.'
        });
    }
    
    /**
     * Set Socket.IO instance
     */
    setSocketIo(io) {
        this.socketIo = io;
    }
    
    /**
     * Set webhook URL
     */
    setWebhookUrl(url) {
        this.webhookUrl = url;
        this.logOnly = !url;
    }
}

// Singleton instance
let notifierInstance = null;

/**
 * Get or create notifier instance
 */
function getNotifier(config = {}) {
    if (!notifierInstance) {
        notifierInstance = new BackofficeNotifier(config);
    }
    return notifierInstance;
}

/**
 * Initialize notifier with config
 */
function initNotifier(config) {
    notifierInstance = new BackofficeNotifier(config);
    return notifierInstance;
}

module.exports = {
    BackofficeNotifier,
    getNotifier,
    initNotifier,
    NOTIFICATION_TYPES,
    PRIORITY_LEVELS
};
