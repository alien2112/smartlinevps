# ⚡ Quick Reference: Atomic Trip Acceptance

## 🚀 What It Does

**Before:** Driver accepts trip → 50 second wait → timeout → retry → race condition → chaos
**After:** Driver accepts trip → 150ms response → success → OTP in 2s → smooth UX

---

## 📋 Daily Operations

### Start Queue Workers
```bash
# Option 1: Supervisor (Production)
sudo supervisorctl start smartline-worker:*

# Option 2: Manual (Development)
php artisan queue:work redis --queue=high-priority,default,notifications --tries=3
```

### Monitor System
```bash
# Watch logs
tail -f storage/logs/laravel.log | grep -E "Trip lock|OTP|Background job"

# Check queue health
php artisan queue:monitor redis:high-priority,redis:default

# Check Redis
redis-cli ping
redis-cli INFO memory

# Check workers
sudo supervisorctl status smartline-worker:*
```

### Check Performance
```bash
# Response times
grep "total_time_ms" storage/logs/laravel.log | tail -20

# Lock times
grep "lock_time_ms" storage/logs/laravel.log | tail -20

# Failed jobs
php artisan queue:failed
```

---

## 🧪 Testing

### Quick Test
```bash
php test-atomic-lock.php
```

### Manual Test
1. Start queue worker: `php artisan queue:work`
2. Monitor logs: `tail -f storage/logs/laravel.log`
3. Create trip via customer app
4. Accept trip via driver app
5. Verify OTP arrives in < 5 seconds

---

## 🐛 Troubleshooting

### Queue Not Processing
```bash
# Restart workers
sudo supervisorctl restart smartline-worker:*

# Check queue depth
redis-cli LLEN queues:high-priority

# Retry failed jobs
php artisan queue:retry all
```

### Redis Down
```bash
sudo systemctl status redis
sudo systemctl start redis
```

### Slow Responses (> 500ms)
```bash
# Check database
php artisan db:show

# Check Redis latency
redis-cli --latency

# Optimize
php artisan db:optimize
php artisan config:cache
```

### OTP Delayed
```bash
# Check job failures
php artisan queue:failed

# Check worker logs
tail -f storage/logs/worker.log

# Increase workers
# Edit: /etc/supervisor/conf.d/smartline-worker.conf
# Change: numprocs=16
sudo supervisorctl restart smartline-worker:*
```

---

## 📊 Health Checks

**All Good When:**
- ✅ Lock time: < 100ms
- ✅ Response time: < 300ms
- ✅ OTP delivery: < 5s
- ✅ Queue depth: < 100
- ✅ Failed jobs: 0
- ✅ Redis memory: Stable

**Alert When:**
- ⚠️ Lock time: > 500ms
- ⚠️ Response time: > 1,000ms
- ⚠️ Queue depth: > 1,000
- ⚠️ Failed jobs: > 10/hour
- ⚠️ Workers: Down

---

## 🔧 Common Commands

```bash
# Clear caches
php artisan config:clear
php artisan cache:clear

# Restart workers
sudo supervisorctl restart smartline-worker:*

# Check queue
php artisan queue:monitor

# Retry failed jobs
php artisan queue:retry all

# Clear all jobs (CAREFUL!)
redis-cli DEL queues:high-priority
redis-cli DEL queues:default

# Clear specific trip lock (EMERGENCY ONLY!)
redis-cli DEL trip:lock:TRIP_ID_HERE

# View all trip locks
redis-cli KEYS "trip:lock:*"
```

---

## 📈 Performance Targets

| Metric | Target | Alert |
|--------|--------|-------|
| Lock acquisition | < 100ms | > 500ms |
| HTTP response | < 300ms | > 1,000ms |
| OTP delivery | < 5s | > 10s |
| Queue depth | < 100 | > 1,000 |
| Failed jobs | 0 | > 10/hour |
| Worker processes | 8 running | < 4 running |

---

## 🆘 Emergency Procedures

### System Overloaded
```bash
# Scale up workers
# Edit: numprocs=16 in supervisor config
sudo supervisorctl restart smartline-worker:*

# Clear old jobs (if queue > 10,000)
php artisan queue:flush
```

### Redis Crashed
```bash
sudo systemctl restart redis
php artisan config:cache
```

### All Workers Down
```bash
sudo supervisorctl status
sudo supervisorctl start smartline-worker:*
```

### Rollback (Last Resort)
```bash
git revert HEAD~3
php artisan config:clear
php artisan cache:clear
```

---

## 📞 Support Contacts

**Documentation:**
- Implementation: `ATOMIC_TRIP_ACCEPTANCE_IMPLEMENTATION.md`
- Architecture: `ARCHITECTURE_COMPARISON.md`
- Summary: `UBER_GRADE_IMPLEMENTATION_SUMMARY.md`

**Logs:**
- Application: `storage/logs/laravel.log`
- Workers: `storage/logs/worker.log`
- Redis: `redis-cli MONITOR`

---

## 🎯 Success Checklist

Daily:
- [ ] Check queue depth < 100
- [ ] Verify workers running
- [ ] Review failed jobs (should be 0)
- [ ] Spot check response times

Weekly:
- [ ] Analyze performance trends
- [ ] Review customer complaints
- [ ] Check Redis memory usage
- [ ] Optimize if needed

Monthly:
- [ ] Performance audit
- [ ] Capacity planning
- [ ] Worker scaling
- [ ] Redis optimization

---

**Remember:** This system handles Uber-level traffic. Trust the architecture!

🚀 **Fast • Reliable • Scalable**
