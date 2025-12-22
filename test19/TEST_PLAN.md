# Test Plan - 2025-12-19

Source: Production Readiness Report - 2025-12-19.md

## Scope
- Checklist derived from the report to execute critical tests on 2025-12-19.
- Focus on critical user flows and non-functional checks referenced in Phase 5 (Testing).

## Preconditions
- Production-grade .env: APP_ENV=production, APP_DEBUG=false, APP_URL set, strong DB creds, queue/cache/session on redis.
- DB migrated and seeded; Passport keys installed; storage:link done.
- External services configured: Google Maps/GeoLink keys, Firebase, at least one SMS provider, at least one payment gateway, SMTP.
- Realtime service configured for production URLs and running; Redis available.
- Sentry configured (current DSN: https://8e3a3d97d754e7bf63fbdf2e7f350479@o4507441712201728.ingest.de.sentry.io/4510558297849936).

## Automated checks to run
- php artisan test (currently minimal coverage; add critical-path tests first).
- php artisan sentry:test (last run 2025-12-19 sent event id ee4f8d3fdd394d25bb7f89430f095521; rerun after env changes).
- ab -n 1000 -c 100 https://<host>/api/health (after HTTPS + health endpoint exist).

## Manual QA (critical paths)
- Authentication: registration, login, OTP verification and throttling.
- Driver onboarding: registration, document upload validation, admin approval workflow.
- Trip lifecycle: create request -> driver match -> start -> complete -> receipt; cancellation/refund paths.
- Zone/geo: address geocoding, distance/ETA calculation, zone validation for trips.
- Payments: at least one configured gateway (e.g., Stripe/Paystack) success + failure flows.
- Notifications: push (Firebase) and SMS delivery for OTP/trip updates.
- File uploads: profile/identity uploads enforce size/type/content validation.
- Realtime: websocket location updates broadcast and received by client app.
- Admin: booking overview, fare/zone config changes reflect in APIs.

## Performance/health
- GET /api/health returns ok and checks DB + Redis connectivity.
- Load test `/api/health` or a lightweight endpoint with ab as above; monitor error rate/latency.
- Queue workers active and processing; failed jobs table stays empty.

## Execution status (2025-12-19)
- Not executed here: filesystem is read-only and required services (DB, SMS, payment, maps, firebase) are not configured in this sandbox.
- Next steps: provide production-ready .env + DB access and allow write/command execution; then run the above and record results in this folder.