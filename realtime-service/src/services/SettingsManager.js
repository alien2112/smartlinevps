/**
 * Settings Manager
 * Manages application settings from Laravel/Redis with caching and live updates
 */

const logger = require('../utils/logger');

class SettingsManager {
  constructor(redisClient) {
    this.redis = redisClient;
    this.settings = {};
    this.lastVersion = 0;
    this.refreshIntervalMs = 30000; // 30 seconds
    this.refreshTimer = null;
    this.initialized = false;

    // Default settings (safe fallback)
    this.defaults = {
      // Tracking settings
      'tracking.update_interval_seconds': 3,
      'tracking.min_distance_meters': 10,
      'tracking.stale_timeout_seconds': 300,
      'tracking.heartbeat_interval_seconds': 30,
      'tracking.batch_size': 10,
      'tracking.batch_flush_interval_ms': 1000,

      // Dispatch settings
      'dispatch.search_radius_km': 5,
      'dispatch.max_search_radius_km': 15,
      'dispatch.max_drivers_to_notify': 10,
      'dispatch.match_timeout_seconds': 60,
      'dispatch.category_fallback_enabled': true,
      'dispatch.prioritize_same_category': true,

      // Travel settings
      'travel.search_radius_km': 30,
      'travel.timeout_minutes': 5,
      'travel.vip_only': true,
      'travel.surge_disabled': true,

      // VIP abuse prevention
      'vip.low_category_trip_limit': 5,
      'vip.deprioritization_enabled': true,

      // Map settings
      'map.tile_provider_url': 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
      'map.default_center_lat': 30.0444,
      'map.default_center_lng': 31.2357,
      'map.default_zoom': 12,
      'map.enable_clustering': true,
      'map.routing_provider': 'osrm',
      'map.osrm_server_url': 'https://router.project-osrm.org',
    };
  }

  /**
   * Initialize settings manager
   */
  async initialize() {
    try {
      await this.loadSettings();
      this.startRefreshTimer();
      this.subscribeToUpdates();
      this.initialized = true;
      logger.info('SettingsManager initialized', { settingsCount: Object.keys(this.settings).length });
    } catch (error) {
      logger.error('Failed to initialize SettingsManager, using defaults', { error: error.message });
      this.settings = { ...this.defaults };
      this.initialized = true;
    }
  }

  /**
   * Load settings from Redis
   */
  async loadSettings() {
    try {
      // Check version first
      const version = await this.redis.get('app:settings:version');
      const currentVersion = parseInt(version || '0', 10);

      // Skip if version hasn't changed
      if (currentVersion === this.lastVersion && Object.keys(this.settings).length > 0) {
        return;
      }

      // Load all settings
      const settingsJson = await this.redis.get('app:settings');

      if (settingsJson) {
        const parsed = JSON.parse(settingsJson);

        // Extract typed values
        this.settings = {};
        for (const [key, setting] of Object.entries(parsed)) {
          this.settings[key] = setting.typed_value !== undefined ? setting.typed_value : setting.value;
        }

        this.lastVersion = currentVersion;
        logger.info('Settings loaded from Redis', {
          version: currentVersion,
          count: Object.keys(this.settings).length,
        });
      } else {
        // Try loading from hash
        const values = await this.redis.hgetall('app:settings:values');

        if (values && Object.keys(values).length > 0) {
          this.settings = {};
          for (const [key, value] of Object.entries(values)) {
            try {
              this.settings[key] = JSON.parse(value);
            } catch {
              this.settings[key] = value;
            }
          }
          this.lastVersion = currentVersion;
        } else {
          // Use defaults
          logger.warn('No settings in Redis, using defaults');
          this.settings = { ...this.defaults };
        }
      }
    } catch (error) {
      logger.error('Failed to load settings from Redis', { error: error.message });
      // Keep existing settings or use defaults
      if (Object.keys(this.settings).length === 0) {
        this.settings = { ...this.defaults };
      }
    }
  }

  /**
   * Start periodic refresh timer
   */
  startRefreshTimer() {
    if (this.refreshTimer) {
      clearInterval(this.refreshTimer);
    }

    this.refreshTimer = setInterval(async () => {
      try {
        await this.loadSettings();
      } catch (error) {
        logger.error('Failed to refresh settings', { error: error.message });
      }
    }, this.refreshIntervalMs);
  }

  /**
   * Subscribe to Redis pub/sub for live updates
   */
  subscribeToUpdates() {
    // Create a separate Redis client for subscription
    const subscriber = this.redis.duplicate();

    subscriber.subscribe('settings:invalidated', 'settings:updated', (err, count) => {
      if (err) {
        logger.error('Failed to subscribe to settings channel', { error: err.message });
        return;
      }
      logger.info('Subscribed to settings channels', { count });
    });

    subscriber.on('message', async (channel, message) => {
      try {
        const data = JSON.parse(message);

        if (channel === 'settings:invalidated') {
          logger.info('Settings cache invalidated, reloading', { version: data.version });
          await this.loadSettings();
        } else if (channel === 'settings:updated') {
          // Single setting updated
          logger.info('Single setting updated', { key: data.key });
          this.settings[data.key] = data.value;
        }
      } catch (error) {
        logger.error('Failed to process settings update', { error: error.message });
      }
    });
  }

  /**
   * Get a setting value
   */
  get(key, defaultValue = null) {
    if (this.settings.hasOwnProperty(key)) {
      return this.settings[key];
    }

    if (this.defaults.hasOwnProperty(key)) {
      return this.defaults[key];
    }

    return defaultValue;
  }

  /**
   * Get all settings
   */
  getAll() {
    return { ...this.defaults, ...this.settings };
  }

  /**
   * Get settings for a specific group
   */
  getGroup(group) {
    const prefix = `${group}.`;
    const result = {};

    for (const [key, value] of Object.entries(this.getAll())) {
      if (key.startsWith(prefix)) {
        const shortKey = key.substring(prefix.length);
        result[shortKey] = value;
      }
    }

    return result;
  }

  /**
   * Check if initialized
   */
  isInitialized() {
    return this.initialized;
  }

  /**
   * Shutdown
   */
  shutdown() {
    if (this.refreshTimer) {
      clearInterval(this.refreshTimer);
      this.refreshTimer = null;
    }
  }

  // Convenience getters for common settings

  getTrackingUpdateInterval() {
    return this.get('tracking.update_interval_seconds', 3) * 1000; // Return in ms
  }

  getMinDistanceChange() {
    return this.get('tracking.min_distance_meters', 10);
  }

  getStaleTimeout() {
    return this.get('tracking.stale_timeout_seconds', 300);
  }

  getSearchRadius() {
    return this.get('dispatch.search_radius_km', 5);
  }

  getMaxSearchRadius() {
    return this.get('dispatch.max_search_radius_km', 15);
  }

  getMaxDriversToNotify() {
    return this.get('dispatch.max_drivers_to_notify', 10);
  }

  getMatchTimeout() {
    return this.get('dispatch.match_timeout_seconds', 60) * 1000; // Return in ms
  }

  getTravelSearchRadius() {
    return this.get('travel.search_radius_km', 30);
  }

  getTravelTimeout() {
    return this.get('travel.timeout_minutes', 5) * 60 * 1000; // Return in ms
  }

  isCategoryFallbackEnabled() {
    return this.get('dispatch.category_fallback_enabled', true);
  }

  isVipDeprioritizationEnabled() {
    return this.get('vip.deprioritization_enabled', true);
  }

  getVipLowCategoryLimit() {
    return this.get('vip.low_category_trip_limit', 5);
  }
}

module.exports = SettingsManager;
