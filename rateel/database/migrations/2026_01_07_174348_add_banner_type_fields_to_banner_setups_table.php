<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddBannerTypeFieldsToBannerSetupsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('banner_setups', function (Blueprint $table) {
            $table->enum('banner_type', ['ad', 'coupon', 'discount', 'promotion'])->default('ad')->after('target_audience');
            $table->string('coupon_code')->nullable()->after('banner_type');
            $table->string('discount_code')->nullable()->after('coupon_code');
            $table->boolean('is_promotion')->default(false)->after('discount_code');
            $table->foreignUuid('coupon_id')->nullable()->after('is_promotion');

            // Add foreign key constraint
            $table->foreign('coupon_id')
                ->references('id')
                ->on('coupon_setups')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('banner_setups', function (Blueprint $table) {
            $table->dropForeign(['coupon_id']);
            $table->dropColumn([
                'banner_type',
                'coupon_code',
                'discount_code',
                'is_promotion',
                'coupon_id'
            ]);
        });
    }
}
