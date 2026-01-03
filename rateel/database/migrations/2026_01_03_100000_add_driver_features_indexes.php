<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Performance indexes for new driver features (2026)
 *
 * These indexes optimize the following high-frequency queries:
 * - Dashboard widgets (trip stats by driver + date)
 * - Leaderboard calculations (rankings by trips/earnings/rating)
 * - Gamification queries (achievements, badges, progress)
 * - Notification listing (unread by driver)
 * - Promotion lookups (active promotions for driver)
 * - Phone change requests (pending by driver)
 * - Support tickets (by driver + status)
 */
class AddDriverFeaturesIndexes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Trip requests - Dashboard and leaderboard queries
        Schema::table('trip_requests', function (Blueprint $table) {
            // For dashboard widgets: trips by driver + date + status
            if (!$this->indexExists('trip_requests', 'idx_trips_driver_date_status')) {
                $table->index(
                    ['driver_id', 'created_at', 'current_status', 'payment_status'],
                    'idx_trips_driver_date_status'
                );
            }

            // For leaderboard earnings queries
            if (!$this->indexExists('trip_requests', 'idx_trips_driver_status_fare')) {
                $table->index(
                    ['driver_id', 'current_status', 'paid_fare'],
                    'idx_trips_driver_status_fare'
                );
            }
        });

        // Reviews - For rating leaderboard
        Schema::table('reviews', function (Blueprint $table) {
            if (!$this->indexExists('reviews', 'idx_reviews_received_rating')) {
                $table->index(
                    ['received_by', 'rating', 'created_at'],
                    'idx_reviews_received_rating'
                );
            }
        });

        // Driver achievements - For achievement lookups
        if (Schema::hasTable('driver_achievements')) {
            Schema::table('driver_achievements', function (Blueprint $table) {
                if (!$this->indexExists('driver_achievements', 'idx_driver_achievements_lookup')) {
                    $table->index(
                        ['driver_id', 'achievement_id'],
                        'idx_driver_achievements_lookup'
                    );
                }
            });
        }

        // Gamification progress - For leaderboard by points
        if (Schema::hasTable('driver_gamification_progress')) {
            Schema::table('driver_gamification_progress', function (Blueprint $table) {
                if (!$this->indexExists('driver_gamification_progress', 'idx_gamification_progress_points')) {
                    $table->index(['total_points'], 'idx_gamification_progress_points');
                }
            });
        }

        // Driver notifications - For notification listing
        if (Schema::hasTable('driver_notifications')) {
            Schema::table('driver_notifications', function (Blueprint $table) {
                if (!$this->indexExists('driver_notifications', 'idx_notifications_driver_unread')) {
                    $table->index(
                        ['driver_id', 'is_read', 'created_at'],
                        'idx_notifications_driver_unread'
                    );
                }
            });
        }

        // Driver promotions - For active promotion lookups
        if (Schema::hasTable('driver_promotions')) {
            Schema::table('driver_promotions', function (Blueprint $table) {
                if (!$this->indexExists('driver_promotions', 'idx_promotions_active_dates')) {
                    $table->index(
                        ['is_active', 'starts_at', 'expires_at', 'target_driver_id'],
                        'idx_promotions_active_dates'
                    );
                }
            });
        }

        // Promotion claims - For claim lookups
        if (Schema::hasTable('promotion_claims')) {
            Schema::table('promotion_claims', function (Blueprint $table) {
                if (!$this->indexExists('promotion_claims', 'idx_promotion_claims_lookup')) {
                    $table->index(
                        ['promotion_id', 'driver_id'],
                        'idx_promotion_claims_lookup'
                    );
                }
            });
        }

        // Phone change requests - For pending request lookups
        if (Schema::hasTable('phone_change_requests')) {
            Schema::table('phone_change_requests', function (Blueprint $table) {
                if (!$this->indexExists('phone_change_requests', 'idx_phone_change_driver_status')) {
                    $table->index(
                        ['driver_id', 'status', 'expires_at'],
                        'idx_phone_change_driver_status'
                    );
                }

                // Add otp_hash column if it doesn't exist (for OTP security fix)
                if (!Schema::hasColumn('phone_change_requests', 'otp_hash')) {
                    $table->string('otp_hash', 255)->nullable()->after('new_phone');
                }

                // Add otp_attempts column if it doesn't exist
                if (!Schema::hasColumn('phone_change_requests', 'otp_attempts')) {
                    $table->unsignedTinyInteger('otp_attempts')->default(0)->after('otp_hash');
                }
            });
        }

        // Support tickets - For ticket listing
        if (Schema::hasTable('support_tickets')) {
            Schema::table('support_tickets', function (Blueprint $table) {
                if (!$this->indexExists('support_tickets', 'idx_tickets_driver_status')) {
                    $table->index(
                        ['driver_id', 'status', 'created_at'],
                        'idx_tickets_driver_status'
                    );
                }
            });
        }

        // Account deletion requests - For pending deletion lookups
        if (Schema::hasTable('account_deletion_requests')) {
            Schema::table('account_deletion_requests', function (Blueprint $table) {
                if (!$this->indexExists('account_deletion_requests', 'idx_deletion_driver_status')) {
                    $table->index(
                        ['driver_id', 'status'],
                        'idx_deletion_driver_status'
                    );
                }
            });
        }

        // Emergency contacts - For contact lookups
        if (Schema::hasTable('emergency_contacts')) {
            Schema::table('emergency_contacts', function (Blueprint $table) {
                if (!$this->indexExists('emergency_contacts', 'idx_emergency_driver_primary')) {
                    $table->index(
                        ['driver_id', 'is_primary'],
                        'idx_emergency_driver_primary'
                    );
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('trip_requests', function (Blueprint $table) {
            $table->dropIndex('idx_trips_driver_date_status');
            $table->dropIndex('idx_trips_driver_status_fare');
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex('idx_reviews_received_rating');
        });

        if (Schema::hasTable('driver_achievements')) {
            Schema::table('driver_achievements', function (Blueprint $table) {
                $table->dropIndex('idx_driver_achievements_lookup');
            });
        }

        if (Schema::hasTable('driver_gamification_progress')) {
            Schema::table('driver_gamification_progress', function (Blueprint $table) {
                $table->dropIndex('idx_gamification_progress_points');
            });
        }

        if (Schema::hasTable('driver_notifications')) {
            Schema::table('driver_notifications', function (Blueprint $table) {
                $table->dropIndex('idx_notifications_driver_unread');
            });
        }

        if (Schema::hasTable('driver_promotions')) {
            Schema::table('driver_promotions', function (Blueprint $table) {
                $table->dropIndex('idx_promotions_active_dates');
            });
        }

        if (Schema::hasTable('promotion_claims')) {
            Schema::table('promotion_claims', function (Blueprint $table) {
                $table->dropIndex('idx_promotion_claims_lookup');
            });
        }

        if (Schema::hasTable('phone_change_requests')) {
            Schema::table('phone_change_requests', function (Blueprint $table) {
                $table->dropIndex('idx_phone_change_driver_status');
                $table->dropColumn(['otp_hash', 'otp_attempts']);
            });
        }

        if (Schema::hasTable('support_tickets')) {
            Schema::table('support_tickets', function (Blueprint $table) {
                $table->dropIndex('idx_tickets_driver_status');
            });
        }

        if (Schema::hasTable('account_deletion_requests')) {
            Schema::table('account_deletion_requests', function (Blueprint $table) {
                $table->dropIndex('idx_deletion_driver_status');
            });
        }

        if (Schema::hasTable('emergency_contacts')) {
            Schema::table('emergency_contacts', function (Blueprint $table) {
                $table->dropIndex('idx_emergency_driver_primary');
            });
        }
    }

    /**
     * Check if an index exists on a table
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $dbName = $connection->getDatabaseName();

        $result = $connection->select("
            SELECT COUNT(*) as count
            FROM information_schema.statistics
            WHERE table_schema = ?
            AND table_name = ?
            AND index_name = ?
        ", [$dbName, $table, $indexName]);

        return $result[0]->count > 0;
    }
}
