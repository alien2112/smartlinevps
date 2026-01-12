<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('max_negative_balance', 10, 2)->default(200.00)->after('is_active');
            $table->boolean('negative_balance_warning_sent')->default(false)->after('max_negative_balance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['max_negative_balance', 'negative_balance_warning_sent']);
        });
    }
};
