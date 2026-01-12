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
        Schema::table('support_tickets', function (Blueprint $table) {
            // Add new columns for driver support system
            if (!Schema::hasColumn('support_tickets', 'user_id')) {
                $table->foreignUuid('user_id')->nullable()->constrained('users')->onDelete('cascade');
            }
            if (!Schema::hasColumn('support_tickets', 'user_type')) {
                $table->string('user_type')->nullable(); // 'driver' or 'customer'
            }
            if (!Schema::hasColumn('support_tickets', 'message')) {
                $table->text('message')->nullable();
            }
            if (!Schema::hasColumn('support_tickets', 'trip_id')) {
                $table->uuid('trip_id')->nullable();
            }
            if (!Schema::hasColumn('support_tickets', 'admin_response')) {
                $table->text('admin_response')->nullable();
            }
            if (!Schema::hasColumn('support_tickets', 'responded_at')) {
                $table->timestamp('responded_at')->nullable();
            }
            if (!Schema::hasColumn('support_tickets', 'responded_by')) {
                $table->uuid('responded_by')->nullable();
            }
            if (!Schema::hasColumn('support_tickets', 'driver_reply')) {
                $table->text('driver_reply')->nullable();
            }
            if (!Schema::hasColumn('support_tickets', 'replied_at')) {
                $table->timestamp('replied_at')->nullable();
            }
            if (!Schema::hasColumn('support_tickets', 'rating_feedback')) {
                $table->text('rating_feedback')->nullable();
            }
            if (!Schema::hasColumn('support_tickets', 'rated_at')) {
                $table->timestamp('rated_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $columns = ['user_id', 'user_type', 'message', 'trip_id', 'admin_response',
                       'responded_at', 'responded_by', 'driver_reply', 'replied_at', 'rating_feedback', 'rated_at'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('support_tickets', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
