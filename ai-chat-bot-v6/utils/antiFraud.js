// ============================================
// 🛡️ ANTI-FRAUD CONTROLS V3.4.1
// WARNING ONLY - Does NOT block customers
// Flags suspicious activity for human review
// ============================================

/**
 * Anti-Fraud Service - WARNING ONLY
 * Does NOT block customers from reporting
 * Only flags suspicious patterns for back-office review
 */
class AntiFraudService {
    constructor(dbQuery, dbExecute, backofficeNotifier) {
        this.dbQuery = dbQuery;
        this.dbExecute = dbExecute;
        this.notifier = backofficeNotifier;
    }
    
    /**
     * Check report for suspicious patterns - WARNING ONLY
     * Returns warnings but NEVER blocks the customer
     * @param {string} userId - User ID
     * @param {string} captainId - Captain ID
     * @param {string} tripId - Trip ID
     * @returns {Object} - Check result with warnings (never blocks)
     */
    async checkReportPatterns(userId, captainId, tripId) {
        const warnings = [];
        const flags = [];
        
        try {
            // Run all checks in parallel
            const [
                duplicateCheck,
                frequencyCheck,
                tripCheck,
                captainCheck,
                behaviorScore
            ] = await Promise.all([
                this.checkDuplicateReport(userId, captainId),
                this.checkReportFrequency(userId),
                this.checkTripValidity(userId, tripId),
                this.checkCaptainHistory(captainId),
                this.getUserBehaviorScore(userId)
            ]);
            
            // Collect warnings (not blockers)
            if (duplicateCheck.isDuplicate) {
                warnings.push({
                    type: 'DUPLICATE',
                    message: 'User reported same captain in last 24h',
                    data: duplicateCheck
                });
                flags.push('DUPLICATE_REPORT');
            }
            
            if (frequencyCheck.isHighFrequency) {
                warnings.push({
                    type: 'HIGH_FREQUENCY',
                    message: `User submitted ${frequencyCheck.count} reports in last 7 days`,
                    data: frequencyCheck
                });
                flags.push('HIGH_FREQUENCY');
            }
            
            if (!tripCheck.valid) {
                warnings.push({
                    type: 'TRIP_ISSUE',
                    message: tripCheck.reason,
                    data: tripCheck
                });
                flags.push('TRIP_VALIDATION_FAILED');
            }
            
            if (behaviorScore.level === 'FLAGGED') {
                warnings.push({
                    type: 'LOW_TRUST_SCORE',
                    message: `User trust score: ${behaviorScore.score}`,
                    data: behaviorScore
                });
                flags.push('LOW_TRUST_USER');
            }
            
            // If captain has many reports, add context (not a warning against user)
            if (captainCheck.hasMultipleReports) {
                warnings.push({
                    type: 'CAPTAIN_HISTORY',
                    message: `Captain has ${captainCheck.reportCount} reports in last 30 days`,
                    data: captainCheck,
                    isPositive: true // This supports the report, not against it
                });
            }
            
            // Determine overall risk level
            const riskLevel = this.calculateRiskLevel(flags);
            
            // If any warnings, notify back-office (but don't block customer)
            if (warnings.length > 0 && this.notifier) {
                await this.notifier.notify({
                    type: 'FRAUD_CHECK_WARNING',
                    priority: riskLevel === 'HIGH' ? 2 : 3,
                    title: `🛡️ Anti-Fraud Warning (${riskLevel} Risk)`,
                    data: {
                        user_id: userId,
                        captain_id: captainId,
                        trip_id: tripId,
                        risk_level: riskLevel,
                        flags,
                        warnings,
                        behavior_score: behaviorScore.score
                    },
                    action_required: 'REVIEW_IF_NEEDED',
                    suggested_action: 'Review report with extra scrutiny. Do not auto-reject.',
                    auto_reject: false // NEVER auto-reject
                });
            }
            
            return {
                allowed: true, // ALWAYS allow - never block
                risk_level: riskLevel,
                flags,
                warnings,
                behavior_score: behaviorScore.score,
                requires_extra_review: flags.length > 0
            };
            
        } catch (error) {
            console.error('[AntiFraud] Check error:', error);
            // On error, allow the report (better UX)
            return {
                allowed: true,
                risk_level: 'UNKNOWN',
                flags: [],
                warnings: [],
                error: error.message
            };
        }
    }
    
    /**
     * Check if user reported same captain recently
     */
    async checkDuplicateReport(userId, captainId) {
        try {
            const reports = await this.dbQuery(`
                SELECT COUNT(*) as count FROM issue_reports
                WHERE user_id = ? AND captain_id = ?
                AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            `, [userId, captainId]);
            
            const count = reports?.[0]?.count || 0;
            return { isDuplicate: count > 0, count };
        } catch (error) {
            return { isDuplicate: false, error: error.message };
        }
    }
    
    /**
     * Check user's report frequency
     */
    async checkReportFrequency(userId) {
        try {
            const reports = await this.dbQuery(`
                SELECT COUNT(*) as count FROM issue_reports
                WHERE user_id = ?
                AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
            `, [userId]);
            
            const count = reports?.[0]?.count || 0;
            return { 
                isHighFrequency: count >= 5, 
                count,
                threshold: 5
            };
        } catch (error) {
            return { isHighFrequency: false, error: error.message };
        }
    }
    
    /**
     * Check if trip is valid for reporting
     */
    async checkTripValidity(userId, tripId) {
        if (!tripId) {
            return { valid: true, reason: 'No trip ID (general report)' };
        }
        
        try {
            const trips = await this.dbQuery(`
                SELECT id, customer_id, current_status, created_at 
                FROM trip_requests
                WHERE id = ?
            `, [tripId]);
            
            if (trips.length === 0) {
                return { valid: false, reason: 'Trip not found' };
            }
            
            const trip = trips[0];
            
            // Check ownership
            if (trip.customer_id !== userId) {
                return { valid: false, reason: 'User is not trip owner' };
            }
            
            // Check age (allow up to 7 days)
            const daysSinceTrip = (Date.now() - new Date(trip.created_at).getTime()) / (1000 * 60 * 60 * 24);
            if (daysSinceTrip > 7) {
                return { valid: false, reason: 'Trip is older than 7 days' };
            }
            
            return { valid: true };
        } catch (error) {
            return { valid: true, error: error.message }; // Allow on error
        }
    }
    
    /**
     * Check captain's report history
     */
    async checkCaptainHistory(captainId) {
        if (!captainId) {
            return { hasMultipleReports: false };
        }
        
        try {
            const reports = await this.dbQuery(`
                SELECT COUNT(*) as count FROM issue_reports
                WHERE captain_id = ?
                AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
            `, [captainId]);
            
            const count = reports?.[0]?.count || 0;
            return {
                hasMultipleReports: count >= 3,
                reportCount: count
            };
        } catch (error) {
            return { hasMultipleReports: false, error: error.message };
        }
    }
    
    /**
     * Get user's behavior score based on report history
     */
    async getUserBehaviorScore(userId) {
        try {
            const reports = await this.dbQuery(`
                SELECT status FROM issue_reports
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT 20
            `, [userId]);
            
            if (reports.length === 0) {
                return { score: 100, level: 'NEW_USER', total_reports: 0 };
            }
            
            // Calculate score based on outcomes
            const validated = reports.filter(r => 
                ['VALIDATED', 'RESOLVED', 'STRIKE_APPLIED'].includes(r.status)
            ).length;
            
            const rejected = reports.filter(r => 
                r.status === 'REJECTED'
            ).length;
            
            let score = 50; // Base score
            score += (validated / reports.length) * 40; // Up to +40
            score -= (rejected / reports.length) * 30; // Up to -30
            score = Math.max(0, Math.min(100, Math.round(score)));
            
            return {
                score,
                level: score >= 70 ? 'TRUSTED' : score >= 40 ? 'NORMAL' : 'FLAGGED',
                total_reports: reports.length,
                validated,
                rejected
            };
        } catch (error) {
            return { score: 50, level: 'UNKNOWN', error: error.message };
        }
    }
    
    /**
     * Calculate overall risk level
     */
    calculateRiskLevel(flags) {
        if (flags.length === 0) return 'LOW';
        if (flags.length === 1) return 'MEDIUM';
        if (flags.includes('LOW_TRUST_USER') && flags.length > 1) return 'HIGH';
        if (flags.length >= 3) return 'HIGH';
        return 'MEDIUM';
    }
    
    /**
     * Log suspicious activity (for monitoring, not blocking)
     */
    async logActivity(userId, activityType, details) {
        try {
            await this.dbExecute(`
                INSERT INTO suspicious_activity_log
                (user_id, activity_type, details, created_at)
                VALUES (?, ?, ?, NOW())
            `, [userId, activityType, JSON.stringify(details)]);
        } catch (error) {
            console.error('[AntiFraud] Log activity error:', error);
        }
    }
    
    /**
     * Check for coordinated reporting (multiple users, same captain, short time)
     */
    async checkCoordinatedReporting(captainId) {
        try {
            const reports = await this.dbQuery(`
                SELECT DISTINCT user_id FROM issue_reports
                WHERE captain_id = ?
                AND created_at > DATE_SUB(NOW(), INTERVAL 2 HOUR)
            `, [captainId]);
            
            const uniqueReporters = reports.length;
            
            return {
                isCoordinated: uniqueReporters >= 3,
                reporterCount: uniqueReporters,
                // Note: Could be legitimate if captain really has issues
                note: uniqueReporters >= 3 
                    ? 'Multiple users reported - could be legitimate pattern' 
                    : null
            };
        } catch (error) {
            return { isCoordinated: false, error: error.message };
        }
    }
}

module.exports = { AntiFraudService };

