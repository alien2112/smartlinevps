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
        Schema::table('safety_alerts', function (Blueprint $table) {
            $table->foreignUuid('trip_request_id')->nullable()->change();
            $table->string('trip_status_when_make_alert')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('safety_alerts', function (Blueprint $table) {
            $table->foreignUuid('trip_request_id')->nullable(false)->change();
            $table->string('trip_status_when_make_alert')->nullable(false)->change();
        });
    }
};
