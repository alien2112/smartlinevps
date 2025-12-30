<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Tests for the performance indexes migration
 * 
 * These tests verify that critical performance indexes exist
 * for production query optimization.
 */
class PerformanceIndexesTest extends TestCase
{
    /**
     * Test that trip_requests table has the critical indexes
     */
    public function test_trip_requests_has_pending_lookup_index(): void
    {
        $indexes = $this->getTableIndexes('trip_requests');
        
        // Check for essential columns being indexed
        $hasZoneIndex = collect($indexes)->contains(function ($index) {
            return str_contains(strtolower($index->Column_name ?? $index->column_name ?? ''), 'zone_id');
        });
        
        $hasStatusIndex = collect($indexes)->contains(function ($index) {
            return str_contains(strtolower($index->Column_name ?? $index->column_name ?? ''), 'current_status');
        });
        
        // At minimum, we should have individual column indexes or composite
        $this->assertTrue(
            $hasZoneIndex || $hasStatusIndex || $this->hasCompositeIndex($indexes, ['zone_id', 'current_status']),
            'trip_requests should have indexes for zone_id and/or current_status'
        );
    }

    /**
     * Test that rejected_driver_requests has trip_user index
     */
    public function test_rejected_driver_requests_has_trip_user_index(): void
    {
        if (!Schema::hasTable('rejected_driver_requests')) {
            $this->markTestSkipped('rejected_driver_requests table does not exist');
        }
        
        $indexes = $this->getTableIndexes('rejected_driver_requests');
        
        $hasTripRequestIdIndex = collect($indexes)->contains(function ($index) {
            return str_contains(strtolower($index->Column_name ?? $index->column_name ?? ''), 'trip_request_id');
        });
        
        $this->assertTrue(
            $hasTripRequestIdIndex,
            'rejected_driver_requests should have index on trip_request_id'
        );
    }

    /**
     * Test that vehicles table has driver_id index for quick lookup
     */
    public function test_vehicles_has_driver_index(): void
    {
        if (!Schema::hasTable('vehicles')) {
            $this->markTestSkipped('vehicles table does not exist');
        }
        
        $indexes = $this->getTableIndexes('vehicles');
        
        $hasDriverIdIndex = collect($indexes)->contains(function ($index) {
            return str_contains(strtolower($index->Column_name ?? $index->column_name ?? ''), 'driver_id');
        });
        
        $this->assertTrue(
            $hasDriverIdIndex,
            'vehicles table should have index on driver_id'
        );
    }

    /**
     * Test that user_last_locations has user_id index
     */
    public function test_user_last_locations_has_user_index(): void
    {
        if (!Schema::hasTable('user_last_locations')) {
            $this->markTestSkipped('user_last_locations table does not exist');
        }
        
        $indexes = $this->getTableIndexes('user_last_locations');
        
        $hasUserIdIndex = collect($indexes)->contains(function ($index) {
            return str_contains(strtolower($index->Column_name ?? $index->column_name ?? ''), 'user_id');
        });
        
        $this->assertTrue(
            $hasUserIdIndex,
            'user_last_locations should have index on user_id'
        );
    }

    /**
     * Get all indexes for a table
     */
    private function getTableIndexes(string $table): array
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        try {
            if ($driver === 'mysql') {
                return DB::select("SHOW INDEX FROM {$table}");
            }

            if ($driver === 'pgsql') {
                return DB::select("
                    SELECT indexname as Key_name, attname as Column_name
                    FROM pg_indexes
                    JOIN pg_attribute ON attrelid = (tablename || '_pkey')::regclass AND attname = ANY(string_to_array(indexdef, ','))
                    WHERE tablename = ?
                ", [$table]);
            }

            // SQLite
            $result = DB::select("PRAGMA index_list({$table})");
            $indexes = [];
            foreach ($result as $index) {
                $columns = DB::select("PRAGMA index_info({$index->name})");
                foreach ($columns as $col) {
                    $indexes[] = (object) [
                        'Key_name' => $index->name,
                        'Column_name' => $col->name,
                    ];
                }
            }
            return $indexes;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Check if a composite index exists for given columns
     */
    private function hasCompositeIndex(array $indexes, array $columns): bool
    {
        $indexGroups = collect($indexes)->groupBy(function ($index) {
            return $index->Key_name ?? $index->key_name ?? 'unknown';
        });

        foreach ($indexGroups as $indexName => $indexColumns) {
            $indexColumnNames = $indexColumns->pluck('Column_name', 'column_name')->values()->toArray();
            
            $matchCount = 0;
            foreach ($columns as $column) {
                foreach ($indexColumnNames as $indexCol) {
                    if (str_contains(strtolower($indexCol), strtolower($column))) {
                        $matchCount++;
                        break;
                    }
                }
            }
            
            if ($matchCount === count($columns)) {
                return true;
            }
        }
        
        return false;
    }
}
