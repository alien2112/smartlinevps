/**
 * Resilient Redis Client with In-Memory Fallback
 *
 * Automatically switches to in-memory mode when Redis fails
 * and recovers when Redis comes back online.
 *
 * FEATURES:
 * - Automatic failover to in-memory on Redis connection loss
 * - Periodic reconnection attempts
 * - Transparent API (same interface as ioredis)
 * - Health monitoring and metrics
 * - Graceful degradation
 */

const EventEmitter = require('events');
const logger = require('../utils/logger');

class ResilientRedisClient extends EventEmitter {
  constructor(redisClient, MockRedisClient) {
    super();

    this.redisClient = redisClient;
    this.MockRedisClient = MockRedisClient;
    this.mockClient = null;
    this.isUsingFallback = false;
    this.reconnectInterval = null;
    this.reconnectAttempts = 0;
    this.maxReconnectAttempts = Infinity;
    this.reconnectIntervalMs = 5000; // Try every 5 seconds
    this.healthCheckIntervalMs = 10000; // Check health every 10 seconds
    this.healthCheckInterval = null;

    // Track Redis health
    this.redisHealthy = true;
    this.lastSuccessfulPing = Date.now();

    // Setup Redis event listeners
    this.setupRedisMonitoring();

    // Start health monitoring
    this.startHealthMonitoring();
  }

  /**
   * Setup Redis connection monitoring
   */
  setupRedisMonitoring() {
    this.redisClient.on('error', (err) => {
      logger.error('Redis error detected', { error: err.message });
      this.handleRedisFailure();
    });

    this.redisClient.on('close', () => {
      logger.warn('Redis connection closed');
      this.handleRedisFailure();
    });

    this.redisClient.on('end', () => {
      logger.warn('Redis connection ended');
      this.handleRedisFailure();
    });

    this.redisClient.on('reconnecting', () => {
      logger.info('Redis reconnecting...');
    });

    this.redisClient.on('ready', () => {
      logger.info('Redis connection ready');
      if (this.isUsingFallback) {
        this.handleRedisRecovery();
      }
      this.redisHealthy = true;
      this.lastSuccessfulPing = Date.now();
    });

    this.redisClient.on('connect', () => {
      logger.info('Redis connected');
      this.redisHealthy = true;
    });
  }

  /**
   * Start periodic health checks
   */
  startHealthMonitoring() {
    this.healthCheckInterval = setInterval(async () => {
      try {
        await this.redisClient.ping();
        this.lastSuccessfulPing = Date.now();
        this.redisHealthy = true;

        // If we were using fallback and Redis is healthy, try to recover
        if (this.isUsingFallback) {
          this.handleRedisRecovery();
        }
      } catch (err) {
        logger.error('Redis health check failed', { error: err.message });
        this.redisHealthy = false;
        this.handleRedisFailure();
      }
    }, this.healthCheckIntervalMs);
  }

  /**
   * Handle Redis failure - switch to in-memory fallback
   */
  handleRedisFailure() {
    if (this.isUsingFallback) {
      return; // Already using fallback
    }

    logger.warn('⚠️  Redis connection failed - switching to IN-MEMORY FALLBACK mode');
    logger.warn('⚠️  This is DEGRADED MODE - data will not persist and clustering is disabled');

    this.isUsingFallback = true;
    this.mockClient = new this.MockRedisClient();
    this.redisHealthy = false;

    // Emit fallback event
    this.emit('fallback', {
      mode: 'in-memory',
      timestamp: Date.now(),
      reason: 'Redis connection failed'
    });

    // Start attempting reconnection
    this.startReconnectionAttempts();
  }

  /**
   * Handle Redis recovery - switch back to Redis
   */
  handleRedisRecovery() {
    if (!this.isUsingFallback) {
      return; // Already using Redis
    }

    logger.info('✅ Redis connection recovered - switching back to Redis mode');

    this.isUsingFallback = false;
    this.redisHealthy = true;
    this.reconnectAttempts = 0;

    // Stop reconnection attempts
    if (this.reconnectInterval) {
      clearInterval(this.reconnectInterval);
      this.reconnectInterval = null;
    }

    // Clean up mock client
    if (this.mockClient) {
      this.mockClient.quit();
      this.mockClient = null;
    }

    // Emit recovery event
    this.emit('recovery', {
      mode: 'redis',
      timestamp: Date.now(),
      downtime: Date.now() - this.lastSuccessfulPing
    });
  }

  /**
   * Start periodic reconnection attempts
   */
  startReconnectionAttempts() {
    if (this.reconnectInterval) {
      return; // Already attempting
    }

    this.reconnectInterval = setInterval(async () => {
      this.reconnectAttempts++;

      if (this.reconnectAttempts > this.maxReconnectAttempts) {
        logger.error('Max reconnection attempts reached - staying in fallback mode');
        clearInterval(this.reconnectInterval);
        this.reconnectInterval = null;
        return;
      }

      logger.info(`Attempting Redis reconnection (attempt ${this.reconnectAttempts})...`);

      try {
        await this.redisClient.ping();
        logger.info('Redis reconnection successful!');
        this.handleRedisRecovery();
      } catch (err) {
        logger.warn(`Redis reconnection failed (attempt ${this.reconnectAttempts})`, { error: err.message });
      }
    }, this.reconnectIntervalMs);
  }

  /**
   * Get the active client (Redis or fallback)
   */
  getActiveClient() {
    return this.isUsingFallback ? this.mockClient : this.redisClient;
  }

  /**
   * Execute command with automatic fallback
   */
  async executeCommand(commandName, ...args) {
    const client = this.getActiveClient();

    try {
      const result = await client[commandName](...args);
      return result;
    } catch (err) {
      // If Redis command fails, trigger fallback and retry with mock
      if (!this.isUsingFallback && commandName !== 'ping') {
        logger.error(`Redis command ${commandName} failed, using fallback`, { error: err.message });
        this.handleRedisFailure();

        // Retry with fallback
        const fallbackClient = this.getActiveClient();
        return await fallbackClient[commandName](...args);
      }
      throw err;
    }
  }

  /**
   * Get health status
   */
  getHealthStatus() {
    return {
      mode: this.isUsingFallback ? 'in-memory' : 'redis',
      redisHealthy: this.redisHealthy,
      isUsingFallback: this.isUsingFallback,
      reconnectAttempts: this.reconnectAttempts,
      lastSuccessfulPing: new Date(this.lastSuccessfulPing).toISOString(),
      uptimeMs: Date.now() - this.lastSuccessfulPing
    };
  }

  /**
   * Proxy all Redis methods to the active client
   */

  // String operations
  async get(key) { return this.executeCommand('get', key); }
  async set(key, value, ...args) { return this.executeCommand('set', key, value, ...args); }
  async setex(key, seconds, value) { return this.executeCommand('setex', key, seconds, value); }
  async del(...keys) { return this.executeCommand('del', ...keys); }
  async exists(...keys) { return this.executeCommand('exists', ...keys); }
  async expire(key, seconds) { return this.executeCommand('expire', key, seconds); }
  async scan(cursor, ...args) { return this.executeCommand('scan', cursor, ...args); }

  // Hash operations
  async hset(key, ...args) { return this.executeCommand('hset', key, ...args); }
  async hget(key, field) { return this.executeCommand('hget', key, field); }
  async hgetall(key) { return this.executeCommand('hgetall', key); }
  async hdel(key, ...fields) { return this.executeCommand('hdel', key, ...fields); }

  // Set operations
  async sadd(key, ...members) { return this.executeCommand('sadd', key, ...members); }
  async srem(key, ...members) { return this.executeCommand('srem', key, ...members); }
  async scard(key) { return this.executeCommand('scard', key); }
  async smembers(key) { return this.executeCommand('smembers', key); }
  async sismember(key, member) { return this.executeCommand('sismember', key, member); }

  // Geo operations
  async geoadd(key, ...args) { return this.executeCommand('geoadd', key, ...args); }
  async georadius(key, lng, lat, radius, unit, ...options) {
    return this.executeCommand('georadius', key, lng, lat, radius, unit, ...options);
  }

  // Sorted set operations
  async zadd(key, ...args) { return this.executeCommand('zadd', key, ...args); }
  async zrem(key, ...members) { return this.executeCommand('zrem', key, ...members); }
  async zcard(key) { return this.executeCommand('zcard', key); }

  // Pub/Sub operations
  async publish(channel, message) { return this.executeCommand('publish', channel, message); }
  async subscribe(...channels) { return this.getActiveClient().subscribe(...channels); }
  async unsubscribe(...channels) { return this.getActiveClient().unsubscribe(...channels); }

  // Transaction support
  multi() { return this.getActiveClient().multi(); }
  pipeline() { return this.getActiveClient().pipeline(); }

  // Connection management
  async ping() { return this.executeCommand('ping'); }
  async quit() {
    if (this.healthCheckInterval) {
      clearInterval(this.healthCheckInterval);
    }
    if (this.reconnectInterval) {
      clearInterval(this.reconnectInterval);
    }
    await this.redisClient.quit();
    if (this.mockClient) {
      await this.mockClient.quit();
    }
    return 'OK';
  }

  // Duplicate client (for pub/sub)
  duplicate() {
    // For pub/sub, create a new resilient wrapper with duplicated Redis client
    const duplicatedRedis = this.redisClient.duplicate();
    return new ResilientRedisClient(duplicatedRedis, this.MockRedisClient);
  }

  // Event handling - proxy to active client and ResilientRedisClient itself
  on(event, callback) {
    if (event === 'message' || event === 'subscribe' || event === 'unsubscribe') {
      // Proxy to active client for pub/sub events
      this.redisClient.on(event, callback);
      if (this.mockClient) {
        this.mockClient.on(event, callback);
      }
    }
    // Also allow listening to resilience events (fallback, recovery)
    return super.on(event, callback);
  }
}

module.exports = ResilientRedisClient;
