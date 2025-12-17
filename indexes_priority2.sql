-- ====================================================================
-- PRIORITY 2 DATABASE INDEXES (Deploy Within 1 Month)
-- ====================================================================
-- Apply these indexes after Priority 1 indexes are tested and deployed
-- These improve performance but are not critical for immediate launch
--
-- Expected Impact:
-- - Login queries: 200ms -> <10ms
-- - Transaction history: 10+ seconds -> <200ms
-- - Coupon lookups: 500ms -> <5ms
-- ====================================================================

USE smartline_indexed_copy;

-- --------------------------------------------------------------------
-- 1. USER AUTHENTICATION & LOOKUPS
-- --------------------------------------------------------------------

-- Phone-based login (most common auth method)
CREATE INDEX idx_users_phone_active
ON users(phone, is_active);

-- Email lookups (if used for login)
CREATE INDEX idx_users_email_active
ON users(email, is_active);

-- User type filtering
CREATE INDEX idx_users_type_active
ON users(user_type, is_active);

-- --------------------------------------------------------------------
-- 2. VEHICLE MANAGEMENT
-- --------------------------------------------------------------------

-- Vehicle availability by driver
CREATE INDEX idx_vehicles_driver_active
ON vehicles(driver_id, is_active);

-- Vehicle category filtering
CREATE INDEX idx_vehicles_category
ON vehicles(vehicle_category_id, is_active);

-- Vehicle approval status (for admin queries)
CREATE INDEX idx_vehicles_approval
ON vehicles(is_approved, created_at DESC);

-- --------------------------------------------------------------------
-- 3. TRANSACTION & PAYMENT HISTORY
-- --------------------------------------------------------------------

-- Transaction history by user (wallet page, statements)
CREATE INDEX idx_transactions_user_created
ON transactions(user_id, created_at DESC);

-- Transaction type filtering
CREATE INDEX idx_transactions_type_user
ON transactions(transaction_type, user_id, created_at DESC);

-- Payment tracking
CREATE INDEX idx_payments_payer
ON payment_requests(payer_id, is_paid);

-- Payment method filtering
CREATE INDEX idx_payments_method
ON payment_requests(payment_method, is_paid, created_at DESC);

-- --------------------------------------------------------------------
-- 4. PROMOTIONS & COUPONS
-- --------------------------------------------------------------------

-- Coupon code lookups (apply coupon at checkout)
CREATE INDEX idx_coupons_code
ON coupon_setups(coupon_code);

-- Active promotions filtering
CREATE INDEX idx_coupons_active
ON coupon_setups(is_active, start_date, end_date);

-- User-specific promotions
CREATE INDEX idx_promotions_user
ON user_promotions(user_id, is_used);

-- --------------------------------------------------------------------
-- 5. NOTIFICATIONS & MESSAGING
-- --------------------------------------------------------------------

-- User notifications (if notifications table exists)
-- Uncomment if your schema has these tables
-- CREATE INDEX idx_notifications_user
-- ON notifications(user_id, read_at, created_at DESC);

-- Push tokens for targeting
-- CREATE INDEX idx_push_tokens_user
-- ON push_notification_tokens(user_id, is_active);

-- --------------------------------------------------------------------
-- 6. DRIVER DETAILS & RATINGS
-- --------------------------------------------------------------------

-- Driver details lookups
CREATE INDEX idx_driver_details_user
ON driver_details(user_id);

-- Driver ratings for filtering
CREATE INDEX idx_driver_details_rating
ON driver_details(avg_rating DESC, total_trips DESC);

-- --------------------------------------------------------------------
-- 7. ZONE MANAGEMENT
-- --------------------------------------------------------------------

-- Zone lookups by name
CREATE INDEX idx_zones_name_active
ON zones(name, is_active);

-- Zone by city/region filtering
CREATE INDEX idx_zones_city
ON zones(city, is_active);

-- --------------------------------------------------------------------
-- 8. TRIP BIDDING (if used)
-- --------------------------------------------------------------------

-- Bid lookups by trip
CREATE INDEX idx_bids_trip
ON trip_bids(trip_request_id, created_at DESC);

-- Bid lookups by driver
CREATE INDEX idx_bids_driver
ON trip_bids(driver_id, status);

-- --------------------------------------------------------------------
-- 9. AUDIT & LOGGING
-- --------------------------------------------------------------------

-- API request logs (if you add them)
-- CREATE INDEX idx_api_logs_endpoint
-- ON api_request_logs(endpoint, created_at DESC);

-- CREATE INDEX idx_api_logs_user
-- ON api_request_logs(user_id, created_at DESC);

-- --------------------------------------------------------------------
-- 10. COMPOSITE COVERING INDEXES (Advanced Optimization)
-- --------------------------------------------------------------------

-- Trip queries with common filters (covering index)
CREATE INDEX idx_trips_customer_status_created
ON trip_requests(customer_id, current_status, created_at DESC);

-- Trip queries by driver with status
CREATE INDEX idx_trips_driver_status_created
ON trip_requests(driver_id, current_status, created_at DESC);

-- --------------------------------------------------------------------
-- VERIFICATION QUERIES
-- --------------------------------------------------------------------

-- Check all new indexes
SELECT
    TABLE_NAME,
    INDEX_NAME,
    INDEX_TYPE,
    COLUMN_NAME,
    CARDINALITY,
    INDEX_LENGTH
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = 'smartline_indexed_copy'
    AND INDEX_NAME LIKE 'idx_%'
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;

-- Test query: User login
EXPLAIN SELECT * FROM users
WHERE phone = '+201234567890'
    AND is_active = 1
LIMIT 1;

-- Test query: Transaction history
EXPLAIN SELECT * FROM transactions
WHERE user_id = 'some-user-id'
ORDER BY created_at DESC
LIMIT 20;

-- Test query: Coupon lookup
EXPLAIN SELECT * FROM coupon_setups
WHERE coupon_code = 'SUMMER2025'
    AND is_active = 1
LIMIT 1;

-- ====================================================================
-- MAINTENANCE NOTES:
-- ====================================================================
-- 1. Monitor index usage with:
--    SELECT * FROM sys.schema_unused_indexes;
--
-- 2. Check index fragmentation:
--    OPTIMIZE TABLE trip_requests;
--
-- 3. Update index statistics regularly:
--    ANALYZE TABLE trip_requests;
-- ====================================================================
