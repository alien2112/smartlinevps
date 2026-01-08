<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Change city_id from bigint unsigned to char(36) to store UUIDs
        DB::statement('ALTER TABLE users MODIFY COLUMN city_id CHAR(36) NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to bigint unsigned
        DB::statement('ALTER TABLE users MODIFY COLUMN city_id BIGINT UNSIGNED NULL');
    }
};
