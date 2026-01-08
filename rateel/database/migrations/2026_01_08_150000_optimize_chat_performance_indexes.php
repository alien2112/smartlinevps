<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Add indexes to improve chat performance
     *
     * @return void
     */
    public function up()
    {
        Schema::table('channel_conversations', function (Blueprint $table) {
            // Add index on user_id for faster user message queries
            if (!$this->indexExists('channel_conversations', 'idx_conversation_user')) {
                $table->index('user_id', 'idx_conversation_user');
            }

            // Add index on is_read for unread message queries
            if (!$this->indexExists('channel_conversations', 'idx_conversation_is_read')) {
                $table->index('is_read', 'idx_conversation_is_read');
            }

            // Add composite index for channel + user queries
            if (!$this->indexExists('channel_conversations', 'idx_conversation_channel_user')) {
                $table->index(['channel_id', 'user_id'], 'idx_conversation_channel_user');
            }

            // Add composite index for channel + is_read queries
            if (!$this->indexExists('channel_conversations', 'idx_conversation_channel_read')) {
                $table->index(['channel_id', 'is_read'], 'idx_conversation_channel_read');
            }

            // Add composite index for user + is_read + created_at (for recent unread messages)
            if (!$this->indexExists('channel_conversations', 'idx_conversation_user_read_created')) {
                $table->index(['user_id', 'is_read', 'created_at'], 'idx_conversation_user_read_created');
            }
        });

        Schema::table('channel_users', function (Blueprint $table) {
            // Add composite index for channel + user queries
            if (!$this->indexExists('channel_users', 'idx_channel_users_channel_user')) {
                $table->index(['channel_id', 'user_id'], 'idx_channel_users_channel_user');
            }

            // Add index on is_read
            if (!$this->indexExists('channel_users', 'idx_channel_users_is_read')) {
                $table->index('is_read', 'idx_channel_users_is_read');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('channel_conversations', function (Blueprint $table) {
            $table->dropIndex('idx_conversation_user');
            $table->dropIndex('idx_conversation_is_read');
            $table->dropIndex('idx_conversation_channel_user');
            $table->dropIndex('idx_conversation_channel_read');
            $table->dropIndex('idx_conversation_user_read_created');
        });

        Schema::table('channel_users', function (Blueprint $table) {
            $table->dropIndex('idx_channel_users_channel_user');
            $table->dropIndex('idx_channel_users_is_read');
        });
    }

    /**
     * Check if index exists
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $indexes = DB::select("SHOW INDEXES FROM {$table} WHERE Key_name = ?", [$indexName]);
        return count($indexes) > 0;
    }
};
