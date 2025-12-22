# Real-time Service (Node.js) Performance Review
**Scope:** `realtime-service` (Socket.IO + Redis bridge)  
**Note:** Observations only; no code changes applied.

## Findings
1. **Redis disabled by default (scaling blocker):** `REDIS_ENABLED` defaults to false, falling back to the in-memory mock in `src/config/redis.js`. In production/PM2 cluster this breaks shared state (rooms, GEO, locks) and drops events across instances.
2. **No Socket.IO cluster adapter:** `src/server.js` uses PM2 `cluster` (2 instances) but does not attach a Redis adapter or enforce sticky sessions. Rooms/broadcasts to `user:*` and `ride:*` only reach the instance that owns the socket, so cross-instance broadcasts are lost and horizontal scaling is ineffective.
3. **Rate limits not distributed:** `src/utils/rateLimiter.js` stores counters per-socket in memory; with multiple instances these limits do not aggregate, so burst control is weaker under load-balanced connections.
4. **High-frequency location updates un-pipelined:** `LocationService.updateDriverLocation` issues sequential `GEOADD`, `HSET`, `EXPIRE`, and optional GET per update. Under thousands of drivers at 1s frequency this adds round trips. Pipelining these commands would cut per-update latency and Redis CPU.
5. **Timeout timers per ride:** `DriverMatchingService.dispatchRide` spawns a `setTimeout` for every pending ride. At high concurrency (many simultaneous pending rides) this can create a large timer heap in Node; consider a Redis-backed scheduler or polling for expired `ride:pending:*` keys to bound in-process timers.
6. **Health/metrics endpoints unauthenticated:** `/health` and `/metrics` are open; heavy scraping without rate limits could add load. Not critical if shielded by firewall/LB.

## Recommended Actions
- Enable Redis in production (`REDIS_ENABLED=true`) and ensure proper host/port/password; disable the in-memory mock.
- Add a Redis adapter for Socket.IO and enforce sticky sessions at the load balancer (or disable PM2 cluster if running single-instance).
- Consider a Redis-backed rate limiter (or edge rate limits at the gateway) for cross-instance enforcement.
- Pipeline Redis commands in high-frequency paths (`updateDriverLocation`) and set a TTL/cleanup for GEO members to keep the index lean.
- Replace per-ride `setTimeout` with a Redis TTL/scan-based timeout handler to avoid timer bloat at high pending volumes.
