/**
 * Redis Client Configuration
 * Handles connection to Redis for pub/sub and caching
 * SUPPORTS OPTIONAL REDIS - Falls back to in-memory if disabled
 */

const Redis = require('ioredis');
const logger = require('../utils/logger');
const EventEmitter = require('events');

// Check if Redis is enabled (default: false for easier testing)
const REDIS_ENABLED = process.env.REDIS_ENABLED === 'true';

if (!REDIS_ENABLED) {
  logger.warn('Redis is DISABLED - using in-memory fallback (not suitable for production clustering)');
}

/**
 * In-Memory Mock Redis Client
 * Provides same interface as ioredis for testing without Redis
 */
class MockRedisClient extends EventEmitter {
  constructor() {
    super();
    this.data = new Map();
    this.geoData = new Map();
    this.hashes = new Map();
    this.sets = new Map();
    this.subscriptions = new Map();
    this.connected = true;

    // Emit ready event
    setImmediate(() => {
      this.emit('ready');
      this.emit('connect');
    });
  }

  // String operations
  async get(key) {
    return this.data.get(key) || null;
  }

  async set(key, value, ...args) {
    this.data.set(key, value);
    // Handle EX (expiration in seconds)
    if (args.length >= 2 && args[0] === 'EX') {
      const seconds = parseInt(args[1]);
      setTimeout(() => this.data.delete(key), seconds * 1000);
    }
    return 'OK';
  }

  // setex(key, seconds, value) - Set key with expiration
  async setex(key, seconds, value) {
    this.data.set(key, value);
    setTimeout(() => this.data.delete(key), seconds * 1000);
    return 'OK';
  }

  async del(...keys) {
    let count = 0;
    keys.forEach(key => {
      if (this.data.delete(key) || this.hashes.delete(key) || this.sets.delete(key) || this.geoData.delete(key)) count++;
    });
    return count;
  }

  async exists(...keys) {
    return keys.filter(key => this.data.has(key) || this.hashes.has(key) || this.sets.has(key) || this.geoData.has(key)).length;
  }

  // Hash operations
  async hset(key, ...args) {
    if (!this.hashes.has(key)) {
      this.hashes.set(key, new Map());
    }
    const hash = this.hashes.get(key);

    // Handle both hset(key, field, value) and hset(key, {field1: value1, field2: value2})
    if (args.length === 2 && typeof args[0] === 'object') {
      const obj = args[0];
      Object.entries(obj).forEach(([field, value]) => {
        hash.set(field, String(value));
      });
      return Object.keys(obj).length;
    } else {
      for (let i = 0; i < args.length; i += 2) {
        hash.set(args[i], String(args[i + 1]));
      }
      return Math.floor(args.length / 2);
    }
  }

  async hget(key, field) {
    const hash = this.hashes.get(key);
    return hash ? hash.get(field) || null : null;
  }

  async hgetall(key) {
    const hash = this.hashes.get(key);
    if (!hash) return {};
    const result = {};
    hash.forEach((value, field) => {
      result[field] = value;
    });
    return result;
  }

  async hdel(key, ...fields) {
    const hash = this.hashes.get(key);
    if (!hash) return 0;
    let count = 0;
    fields.forEach(field => {
      if (hash.delete(field)) count++;
    });
    return count;
  }

  // Set operations
  async sadd(key, ...members) {
    if (!this.sets.has(key)) {
      this.sets.set(key, new Set());
    }
    const set = this.sets.get(key);
    let count = 0;
    members.forEach(member => {
      if (!set.has(member)) {
        set.add(member);
        count++;
      }
    });
    return count;
  }

  async srem(key, ...members) {
    const set = this.sets.get(key);
    if (!set) return 0;
    let count = 0;
    members.forEach(member => {
      if (set.delete(member)) count++;
    });
    return count;
  }

  async scard(key) {
    const set = this.sets.get(key);
    return set ? set.size : 0;
  }

  async smembers(key) {
    const set = this.sets.get(key);
    return set ? Array.from(set) : [];
  }

  async sismember(key, member) {
    const set = this.sets.get(key);
    return set ? (set.has(member) ? 1 : 0) : 0;
  }

  // GEO operations (simplified - not production-grade)
  async geoadd(key, ...args) {
    if (!this.geoData.has(key)) {
      this.geoData.set(key, new Map());
    }
    const geo = this.geoData.get(key);

    // Parse args: [lng, lat, member, lng, lat, member, ...]
    for (let i = 0; i < args.length; i += 3) {
      const lng = parseFloat(args[i]);
      const lat = parseFloat(args[i + 1]);
      const member = args[i + 2];
      geo.set(member, { lng, lat });
    }
    return Math.floor(args.length / 3);
  }

  async georadius(key, lng, lat, radius, unit, ...options) {
    const geo = this.geoData.get(key);
    if (!geo) return [];

    const R = 6371; // Earth radius in km
    const radiusInKm = unit === 'km' ? radius : radius / 1000;

    const results = [];
    geo.forEach((pos, member) => {
      // Haversine formula
      const dLat = (pos.lat - lat) * Math.PI / 180;
      const dLng = (pos.lng - lng) * Math.PI / 180;
      const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
        Math.cos(lat * Math.PI / 180) * Math.cos(pos.lat * Math.PI / 180) *
        Math.sin(dLng / 2) * Math.sin(dLng / 2);
      const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
      const distance = R * c;

      if (distance <= radiusInKm) {
        results.push({ member, distance });
      }
    });

    // Sort by distance
    results.sort((a, b) => a.distance - b.distance);

    // Handle LIMIT option
    const limitIndex = options.indexOf('LIMIT');
    if (limitIndex !== -1 && options[limitIndex + 2]) {
      const limit = parseInt(options[limitIndex + 2]);
      return results.slice(0, limit).map(r => r.member);
    }

    return results.map(r => r.member);
  }

  async zrem(key, ...members) {
    const geo = this.geoData.get(key);
    if (!geo) return 0;
    let count = 0;
    members.forEach(member => {
      if (geo.delete(member)) count++;
    });
    return count;
  }

  async zcard(key) {
    const geo = this.geoData.get(key);
    return geo ? geo.size : 0;
  }

  async zadd(key, ...args) {
    // This is for sorted sets (score-based), using geoData as fallback
    if (!this.geoData.has(key)) {
      this.geoData.set(key, new Map());
    }
    const set = this.geoData.get(key);

    // Parse args: [score, member, score, member, ...]
    for (let i = 0; i < args.length; i += 2) {
      const score = parseFloat(args[i]);
      const member = args[i + 1];
      set.set(member, { score });
    }
    return Math.floor(args.length / 2);
  }

  // Pub/Sub operations
  async publish(channel, message) {
    const subs = this.subscriptions.get(channel);
    if (subs) {
      subs.forEach(callback => callback(channel, message));
    }
    return subs ? subs.length : 0;
  }

  async subscribe(...channels) {
    channels.forEach(channel => {
      if (!this.subscriptions.has(channel)) {
        this.subscriptions.set(channel, []);
      }
    });
    return 'OK';
  }

  on(event, callback) {
    if (event === 'message') {
      // Store message callback for all channels
      this.messageCallback = callback;
      this.subscriptions.forEach((subs, channel) => {
        subs.push((ch, msg) => callback(ch, msg));
      });
    }
    return super.on(event, callback);
  }

  async quit() {
    this.connected = false;
    this.emit('close');
    return 'OK';
  }

  duplicate() {
    return new MockRedisClient();
  }

  async expire(key, seconds) {
    if (this.data.has(key) || this.hashes.has(key) || this.sets.has(key) || this.geoData.has(key)) {
      setTimeout(() => {
        this.data.delete(key);
        this.hashes.delete(key);
        this.sets.delete(key);
        this.geoData.delete(key);
      }, seconds * 1000);
      return 1;
    }
    return 0;
  }

  // SCAN operation for iterating through keys
  async scan(cursor, ...args) {
    // Parse MATCH pattern from args
    let pattern = '*';
    const matchIndex = args.indexOf('MATCH');
    if (matchIndex !== -1 && args[matchIndex + 1]) {
      pattern = args[matchIndex + 1];
    }

    // Convert glob pattern to regex
    const regexPattern = pattern
      .replace(/\*/g, '.*')
      .replace(/\?/g, '.');
    const regex = new RegExp(`^${regexPattern}$`);

    // Collect all matching keys
    const matchingKeys = [];

    // Search in data map
    for (const key of this.data.keys()) {
      if (regex.test(key)) {
        matchingKeys.push(key);
      }
    }

    // Search in hashes map
    for (const key of this.hashes.keys()) {
      if (regex.test(key) && !matchingKeys.includes(key)) {
        matchingKeys.push(key);
      }
    }

    // Return all matches with cursor '0' (mock: single iteration)
    return ['0', matchingKeys];
  }

  // Transaction support (simplified)
  multi() {
    const commands = [];
    const mockMulti = {
      set: (...args) => {
        commands.push({ cmd: 'set', args });
        return mockMulti;
      },
      hset: (...args) => {
        // Handle object syntax: hset(key, {field1: value1, field2: value2})
        if (args.length === 2 && typeof args[1] === 'object' && !Array.isArray(args[1])) {
          const [key, obj] = args;
          const flatArgs = [key];
          Object.entries(obj).forEach(([field, value]) => {
            flatArgs.push(field, value);
          });
          commands.push({ cmd: 'hset', args: flatArgs });
        } else {
          commands.push({ cmd: 'hset', args });
        }
        return mockMulti;
      },
      sadd: (...args) => {
        commands.push({ cmd: 'sadd', args });
        return mockMulti;
      },
      geoadd: (...args) => {
        commands.push({ cmd: 'geoadd', args });
        return mockMulti;
      },
      zadd: (...args) => {
        commands.push({ cmd: 'zadd', args });
        return mockMulti;
      },
      del: (...args) => {
        commands.push({ cmd: 'del', args });
        return mockMulti;
      },
      expire: (...args) => {
        commands.push({ cmd: 'expire', args });
        return mockMulti;
      },
      exec: async () => {
        const results = [];
        for (const { cmd, args } of commands) {
          try {
            const result = await this[cmd](...args);
            results.push([null, result]);
          } catch (err) {
            results.push([err, null]);
          }
        }
        return results;
      }
    };
    return mockMulti;
  }

  // Pipeline support (like multi but for batching commands)
  pipeline() {
    const commands = [];
    const self = this;
    const mockPipeline = {
      get: (...args) => {
        commands.push({ cmd: 'get', args });
        return mockPipeline;
      },
      set: (...args) => {
        commands.push({ cmd: 'set', args });
        return mockPipeline;
      },
      hset: (...args) => {
        // Handle object syntax: hset(key, {field1: value1, field2: value2})
        if (args.length === 2 && typeof args[1] === 'object' && !Array.isArray(args[1])) {
          const [key, obj] = args;
          const flatArgs = [key];
          Object.entries(obj).forEach(([field, value]) => {
            flatArgs.push(field, value);
          });
          commands.push({ cmd: 'hset', args: flatArgs });
        } else {
          commands.push({ cmd: 'hset', args });
        }
        return mockPipeline;
      },
      hgetall: (...args) => {
        commands.push({ cmd: 'hgetall', args });
        return mockPipeline;
      },
      sadd: (...args) => {
        commands.push({ cmd: 'sadd', args });
        return mockPipeline;
      },
      geoadd: (...args) => {
        commands.push({ cmd: 'geoadd', args });
        return mockPipeline;
      },
      zadd: (...args) => {
        commands.push({ cmd: 'zadd', args });
        return mockPipeline;
      },
      zrem: (...args) => {
        commands.push({ cmd: 'zrem', args });
        return mockPipeline;
      },
      del: (...args) => {
        commands.push({ cmd: 'del', args });
        return mockPipeline;
      },
      expire: (...args) => {
        commands.push({ cmd: 'expire', args });
        return mockPipeline;
      },
      exec: async () => {
        const results = [];
        for (const { cmd, args } of commands) {
          try {
            const result = await self[cmd](...args);
            results.push([null, result]);
          } catch (err) {
            results.push([err, null]);
          }
        }
        return results;
      }
    };
    return mockPipeline;
  }
}

// Import resilient wrapper
const ResilientRedisClient = require('./ResilientRedisClient');

// Create Redis client based on configuration
let redisClient;

if (REDIS_ENABLED) {
  const redisConfig = {
    host: process.env.REDIS_HOST || '127.0.0.1',
    port: parseInt(process.env.REDIS_PORT) || 6379,
    password: process.env.REDIS_PASSWORD || undefined,
    db: parseInt(process.env.REDIS_DB) || 0,
    retryStrategy: (times) => {
      const delay = Math.min(times * 50, 2000);
      return delay;
    },
    enableReadyCheck: true,
    maxRetriesPerRequest: 3,
    lazyConnect: false,
    reconnectOnError: (err) => {
      const targetError = 'READONLY';
      if (err.message.includes(targetError)) {
        return true;
      }
      return false;
    }
  };

  const rawRedisClient = new Redis(redisConfig);

  // Wrap with resilient client for automatic in-memory fallback
  redisClient = new ResilientRedisClient(rawRedisClient, MockRedisClient);

  logger.info('Redis client initialized with automatic in-memory fallback on connection failure');

  // Listen to resilience events
  redisClient.on('fallback', (data) => {
    logger.warn('🔄 Redis failover activated - switched to IN-MEMORY mode', data);
  });

  redisClient.on('recovery', (data) => {
    logger.info('✅ Redis recovery complete - switched back to Redis mode', data);
  });
} else {
  // Use in-memory mock
  redisClient = new MockRedisClient();
  logger.info('Using in-memory Redis mock (for testing only)');
}

module.exports = redisClient;
