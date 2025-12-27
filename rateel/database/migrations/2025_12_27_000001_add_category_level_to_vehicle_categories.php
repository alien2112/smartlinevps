<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds category_level for dispatch priority (Budget=1, Pro=2, VIP=3)
     */
    public function up(): void
    {
        Schema::table('vehicle_categories', function (Blueprint $table) {
            $table->tinyInteger('category_level')->default(1)->after('type')
                ->comment('1=budget, 2=pro, 3=vip - higher level can accept lower level trips');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicle_categories', function (Blueprint $table) {
            $table->dropColumn('category_level');
        });
    }
};
