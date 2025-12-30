<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('banner_setups', function (Blueprint $table) {
            if (!$this->hasIndex('banner_setups', 'idx_banners_active_date')) {
                $table->index(['is_active', 'created_at'], 'idx_banners_active_date');
            }
        });

        Schema::table('coupon_setups', function (Blueprint $table) {
            if (!$this->hasIndex('coupon_setups', 'idx_coupons_active_expiry')) {
                $table->index(['is_active', 'end_date'], 'idx_coupons_active_expiry');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('banner_setups', function (Blueprint $table) {
            if ($this->hasIndex('banner_setups', 'idx_banners_active_date')) {
                $table->dropIndex('idx_banners_active_date');
            }
        });

        Schema::table('coupon_setups', function (Blueprint $table) {
            if ($this->hasIndex('coupon_setups', 'idx_coupons_active_expiry')) {
                $table->dropIndex('idx_coupons_active_expiry');
            }
        });
    }

    private function hasIndex($table, $index)
    {
        $results = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = '{$index}'");
        return count($results) > 0;
    }
};