-- ====================================================================
-- STEP 1: CREATE DATABASE COPY FOR SAFE INDEXING
-- ====================================================================
-- This script creates a copy of your production database for testing indexes
-- Run this first, then run the index scripts on the copy

-- Create the copy database
CREATE DATABASE IF NOT EXISTS smartline_indexed_copy;

-- Note: To copy all data, run this command in your terminal:
-- mysqldump -u your_username -p your_database_name | mysql -u your_username -p smartline_indexed_copy

-- Or if you want to copy just the structure and test with sample data:
-- mysqldump -u your_username -p --no-data your_database_name | mysql -u your_username -p smartline_indexed_copy

-- ====================================================================
-- INSTRUCTIONS:
-- ====================================================================
-- 1. First, identify your database name from .env file
-- 2. Run one of these commands in PowerShell:
--
--    For full copy (structure + data):
--    mysqldump -u root -p smartline_db | mysql -u root -p smartline_indexed_copy
--
--    For structure only (faster for testing):
--    mysqldump -u root -p --no-data smartline_db | mysql -u root -p smartline_indexed_copy
--
-- 3. Then run the index scripts against smartline_indexed_copy
-- 4. After verification, apply the Laravel migrations to production
-- ====================================================================
