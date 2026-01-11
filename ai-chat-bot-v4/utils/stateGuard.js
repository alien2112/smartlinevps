// ============================================
// 🛡️ STATE GUARD (V3.2 Enhanced)
// State Versioning, Migration & Recovery System
// ============================================

/**
 * Current state schema version
 * Increment this when making breaking changes to state structure
 */
const CURRENT_STATE_VERSION = 3;

/**
 * State version history for documentation
 */
const VERSION_HISTORY = {
    1: { date: '2024-01-01', changes: 'Initial state structure' },
    2: { date: '2024-06-01', changes: 'Added vehicle_categories to flow_data' },
    3: { date: '2025-01-01', changes: 'Added pickup/destination predictions, autocomplete support' }
};

/**
 * State check result
 * @typedef {Object} StateCheckResult
 * @property {boolean} valid - Whether state is valid
 * @property {string} action - Action to take ('NONE', 'MIGRATE', 'RESET', 'REPAIR')
 * @property {string} [reason] - Reason for action
 * @property {number} [from] - Source version (for migration)
 * @property {number} [to] - Target version (for migration)
 * @property {Object} [repairs] - Specific repairs needed
 */

/**
 * Migration function type
 * @typedef {function(Object): Object} MigrationFunction
 */

/**
 * State schema definition
 */
const STATE_SCHEMA = {
    version: { type: 'number', required: true },
    state: { type: 'string', required: true },
    data: { type: 'object', required: false },
    createdAt: { type: 'number', required: false },
    updatedAt: { type: 'number', required: false }
};

/**
 * Valid state values
 */
const VALID_STATES = [
    'START',
    'AWAITING_PICKUP',
    'AWAITING_PICKUP_SELECTION',
    'AWAITING_DESTINATION',
    'AWAITING_DESTINATION_SELECTION',
    'AWAITING_RIDE_TYPE',
    'AWAITING_CONFIRMATION',
    'TRIP_ACTIVE',
    'AWAITING_CANCEL_CONFIRM',
    'COMPLAINT_FLOW',
    'RESOLVED'
];

/**
 * Maximum state age before forced reset (24 hours)
 */
const MAX_STATE_AGE = 24 * 60 * 60 * 1000;

/**
 * Maximum time in a single state before warning (30 minutes)
 */
const STATE_STALE_THRESHOLD = 30 * 60 * 1000;

class StateGuard {
    constructor() {
        /**
         * Migration functions registry
         * @type {Map<string, MigrationFunction>}
         */
        this.migrations = new Map();

        /**
         * Statistics
         */
        this.stats = {
            checksPerformed: 0,
            migrationsRun: 0,
            resetsPerformed: 0,
            repairsPerformed: 0,
            staleStatesDetected: 0
        };

        // Register built-in migrations
        this._registerMigrations();
    }

    /**
     * Register all migration functions
     */
    _registerMigrations() {
        // Migration from v1 to v2
        this.registerMigration(1, 2, (state) => {
            return {
                ...state,
                version: 2,
                data: {
                    ...state.data,
                    vehicle_categories: state.data?.vehicle_categories || null,
                    migrated_at: Date.now()
                }
            };
        });

        // Migration from v2 to v3
        this.registerMigration(2, 3, (state) => {
            return {
                ...state,
                version: 3,
                data: {
                    ...state.data,
                    pickup_predictions: state.data?.pickup_predictions || null,
                    destination_predictions: state.data?.destination_predictions || null,
                    pickup_place_id: state.data?.pickup_place_id || null,
                    destination_place_id: state.data?.destination_place_id || null,
                    user_lat: state.data?.user_lat || null,
                    user_lng: state.data?.user_lng || null,
                    zone_id: state.data?.zone_id || null,
                    migrated_at: Date.now(),
                    migration_path: [...(state.data?.migration_path || []), '2->3']
                }
            };
        });
    }

    /**
     * Register a custom migration function
     * @param {number} fromVersion 
     * @param {number} toVersion 
     * @param {MigrationFunction} migrationFn 
     */
    registerMigration(fromVersion, toVersion, migrationFn) {
        const key = `${fromVersion}->${toVersion}`;
        this.migrations.set(key, migrationFn);
    }

    /**
     * Check state version and determine required action
     * @param {string} userId 
     * @param {Object} currentState 
     * @returns {Promise<StateCheckResult>}
     */
    async checkStateVersion(userId, currentState) {
        this.stats.checksPerformed++;

        // Handle null/undefined state
        if (!currentState) {
            return {
                valid: false,
                action: 'RESET',
                reason: 'STATE_NULL'
            };
        }

        // Handle missing version
        if (currentState.version === undefined || currentState.version === null) {
            // Try to infer version from structure
            const inferredVersion = this._inferVersion(currentState);
            
            if (inferredVersion > 0) {
                return {
                    valid: true,
                    action: 'REPAIR',
                    reason: 'VERSION_MISSING_INFERRED',
                    repairs: { version: inferredVersion }
                };
            }

            return {
                valid: false,
                action: 'RESET',
                reason: 'VERSION_MISSING'
            };
        }

        // Handle future versions (from newer code)
        if (currentState.version > CURRENT_STATE_VERSION) {
            return {
                valid: false,
                action: 'RESET',
                reason: 'VERSION_FUTURE',
                from: currentState.version,
                to: CURRENT_STATE_VERSION
            };
        }

        // Handle outdated versions
        if (currentState.version < CURRENT_STATE_VERSION) {
            // Check if migration path exists
            const migrationPath = this._findMigrationPath(currentState.version, CURRENT_STATE_VERSION);
            
            if (migrationPath.length > 0) {
                return {
                    valid: true,
                    action: 'MIGRATE',
                    reason: 'VERSION_OUTDATED',
                    from: currentState.version,
                    to: CURRENT_STATE_VERSION,
                    migrationPath
                };
            }

            // No migration path available
            return {
                valid: false,
                action: 'RESET',
                reason: 'VERSION_OBSOLETE',
                from: currentState.version,
                to: CURRENT_STATE_VERSION
            };
        }

        // Version is current - perform additional validation
        const validationResult = this._validateStateStructure(currentState);
        if (!validationResult.valid) {
            return {
                valid: false,
                action: validationResult.repairable ? 'REPAIR' : 'RESET',
                reason: validationResult.reason,
                repairs: validationResult.repairs
            };
        }

        // Check for stale state
        const staleCheck = this._checkStaleState(currentState);
        if (staleCheck.stale) {
            this.stats.staleStatesDetected++;
            return {
                valid: true,
                action: 'NONE',
                reason: 'VALID_BUT_STALE',
                warning: staleCheck.warning
            };
        }

        return {
            valid: true,
            action: 'NONE',
            reason: 'VALID'
        };
    }

    /**
     * Infer version from state structure
     * @param {Object} state 
     * @returns {number}
     */
    _inferVersion(state) {
        // V3 indicators
        if (state.data?.pickup_predictions || state.data?.destination_predictions) {
            return 3;
        }

        // V2 indicators
        if (state.data?.vehicle_categories) {
            return 2;
        }

        // V1 indicators (basic structure)
        if (state.state && typeof state.data === 'object') {
            return 1;
        }

        return 0;
    }

    /**
     * Find migration path between versions
     * @param {number} from 
     * @param {number} to 
     * @returns {Array<{from: number, to: number}>}
     */
    _findMigrationPath(from, to) {
        const path = [];
        let current = from;

        while (current < to) {
            const nextVersion = current + 1;
            const key = `${current}->${nextVersion}`;

            if (this.migrations.has(key)) {
                path.push({ from: current, to: nextVersion });
                current = nextVersion;
            } else {
                // No direct migration, try to find jump
                let found = false;
                for (let jump = to; jump > current; jump--) {
                    const jumpKey = `${current}->${jump}`;
                    if (this.migrations.has(jumpKey)) {
                        path.push({ from: current, to: jump });
                        current = jump;
                        found = true;
                        break;
                    }
                }

                if (!found) {
                    // No path available
                    return [];
                }
            }
        }

        return path;
    }

    /**
     * Execute migrations on state
     * @param {Object} state 
     * @param {Array<{from: number, to: number}>} migrationPath 
     * @returns {Object}
     */
    async executeMigrations(state, migrationPath) {
        let currentState = { ...state };

        for (const step of migrationPath) {
            const key = `${step.from}->${step.to}`;
            const migrationFn = this.migrations.get(key);

            if (!migrationFn) {
                throw new Error(`Migration ${key} not found`);
            }

            try {
                currentState = migrationFn(currentState);
                this.stats.migrationsRun++;
                console.log(`[StateGuard] Migrated state from v${step.from} to v${step.to}`);
            } catch (error) {
                console.error(`[StateGuard] Migration ${key} failed:`, error.message);
                throw error;
            }
        }

        return currentState;
    }

    /**
     * Validate state structure
     * @param {Object} state 
     * @returns {{valid: boolean, reason?: string, repairable?: boolean, repairs?: Object}}
     */
    _validateStateStructure(state) {
        const repairs = {};
        let hasIssues = false;

        // Check required fields
        if (typeof state.version !== 'number') {
            repairs.version = CURRENT_STATE_VERSION;
            hasIssues = true;
        }

        // Validate state value
        if (!state.state || !VALID_STATES.includes(state.state)) {
            // Invalid state - check if repairable
            if (state.data?.trip_id) {
                repairs.state = 'TRIP_ACTIVE';
            } else if (state.data?.pickup && !state.data?.destination) {
                repairs.state = 'AWAITING_DESTINATION';
            } else if (state.data?.pickup && state.data?.destination && !state.data?.ride_type) {
                repairs.state = 'AWAITING_RIDE_TYPE';
            } else {
                repairs.state = 'START';
            }
            hasIssues = true;
        }

        // Validate data object
        if (state.data !== null && state.data !== undefined && typeof state.data !== 'object') {
            repairs.data = {};
            hasIssues = true;
        }

        if (hasIssues) {
            return {
                valid: false,
                reason: 'STRUCTURE_INVALID',
                repairable: true,
                repairs
            };
        }

        return { valid: true };
    }

    /**
     * Check if state is stale
     * @param {Object} state 
     * @returns {{stale: boolean, warning?: string}}
     */
    _checkStaleState(state) {
        const updatedAt = state.updatedAt || state.data?.updatedAt;
        
        if (!updatedAt) {
            return { stale: false };
        }

        const age = Date.now() - updatedAt;

        // Very old state
        if (age > MAX_STATE_AGE) {
            return {
                stale: true,
                warning: `State is ${Math.round(age / 3600000)} hours old. Consider resetting.`
            };
        }

        // Stuck in mid-flow state
        const midFlowStates = [
            'AWAITING_PICKUP',
            'AWAITING_PICKUP_SELECTION',
            'AWAITING_DESTINATION',
            'AWAITING_DESTINATION_SELECTION',
            'AWAITING_RIDE_TYPE',
            'AWAITING_CONFIRMATION',
            'AWAITING_CANCEL_CONFIRM'
        ];

        if (midFlowStates.includes(state.state) && age > STATE_STALE_THRESHOLD) {
            return {
                stale: true,
                warning: `User stuck in ${state.state} for ${Math.round(age / 60000)} minutes.`
            };
        }

        return { stale: false };
    }

    /**
     * Repair state with provided fixes
     * @param {Object} state 
     * @param {Object} repairs 
     * @returns {Object}
     */
    repairState(state, repairs) {
        this.stats.repairsPerformed++;

        const repairedState = {
            ...state,
            ...repairs,
            data: {
                ...state.data,
                ...(repairs.data || {}),
                repaired_at: Date.now(),
                repair_log: [
                    ...(state.data?.repair_log || []),
                    {
                        timestamp: Date.now(),
                        repairs: Object.keys(repairs)
                    }
                ]
            }
        };

        console.log(`[StateGuard] Repaired state:`, Object.keys(repairs));
        return repairedState;
    }

    /**
     * Create fresh state with current version
     * @param {string} initialState 
     * @param {Object} initialData 
     * @returns {Object}
     */
    createFreshState(initialState = 'START', initialData = {}) {
        this.stats.resetsPerformed++;

        return {
            version: CURRENT_STATE_VERSION,
            state: initialState,
            data: {
                ...initialData,
                createdAt: Date.now(),
                updatedAt: Date.now()
            },
            createdAt: Date.now(),
            updatedAt: Date.now()
        };
    }

    /**
     * Prepare state for saving (add metadata)
     * @param {Object} state 
     * @returns {Object}
     */
    prepareForSave(state) {
        return {
            ...state,
            version: state.version || CURRENT_STATE_VERSION,
            updatedAt: Date.now(),
            data: {
                ...state.data,
                updatedAt: Date.now()
            }
        };
    }

    /**
     * Get current state version
     * @returns {number}
     */
    getCurrentVersion() {
        return CURRENT_STATE_VERSION;
    }

    /**
     * Get version history
     * @returns {Object}
     */
    getVersionHistory() {
        return VERSION_HISTORY;
    }

    /**
     * Get valid states list
     * @returns {Array<string>}
     */
    getValidStates() {
        return [...VALID_STATES];
    }

    /**
     * Check if state value is valid
     * @param {string} state 
     * @returns {boolean}
     */
    isValidState(state) {
        return VALID_STATES.includes(state);
    }

    /**
     * Get statistics
     * @returns {Object}
     */
    getStats() {
        return {
            ...this.stats,
            currentVersion: CURRENT_STATE_VERSION,
            registeredMigrations: this.migrations.size
        };
    }

    /**
     * Process state with full check, migration, and repair
     * @param {string} userId 
     * @param {Object} currentState 
     * @param {Object} options 
     * @returns {Promise<{state: Object, wasModified: boolean, actions: Array<string>}>}
     */
    async processState(userId, currentState, options = {}) {
        const actions = [];
        let state = currentState;
        let wasModified = false;

        // 1. Check version
        const checkResult = await this.checkStateVersion(userId, state);

        // 2. Handle based on check result
        switch (checkResult.action) {
            case 'RESET':
                state = this.createFreshState('START', options.initialData || {});
                actions.push(`RESET: ${checkResult.reason}`);
                wasModified = true;
                break;

            case 'MIGRATE':
                try {
                    state = await this.executeMigrations(state, checkResult.migrationPath);
                    actions.push(`MIGRATE: v${checkResult.from} -> v${checkResult.to}`);
                    wasModified = true;
                } catch (error) {
                    // Migration failed, reset
                    state = this.createFreshState('START', options.initialData || {});
                    actions.push(`RESET: Migration failed - ${error.message}`);
                    wasModified = true;
                }
                break;

            case 'REPAIR':
                state = this.repairState(state, checkResult.repairs);
                actions.push(`REPAIR: ${Object.keys(checkResult.repairs).join(', ')}`);
                wasModified = true;
                break;

            case 'NONE':
                if (checkResult.warning) {
                    actions.push(`WARNING: ${checkResult.warning}`);
                }
                break;
        }

        // 3. Ensure version is set
        if (!state.version) {
            state.version = CURRENT_STATE_VERSION;
            wasModified = true;
        }

        // 4. Prepare for save if modified
        if (wasModified) {
            state = this.prepareForSave(state);
        }

        return { state, wasModified, actions };
    }
}

// Export singleton instance
module.exports = new StateGuard();
module.exports.StateGuard = StateGuard;
module.exports.CURRENT_STATE_VERSION = CURRENT_STATE_VERSION;
module.exports.VALID_STATES = VALID_STATES;
module.exports.VERSION_HISTORY = VERSION_HISTORY;
