<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Update schedule_notifications table to support guest users
     */
    public function up(): void
    {
        Schema::table('schedule_notifications', function (Blueprint $table) {
            // Drop existing constraints
            $table->dropForeign(['user_id']);
            $table->dropUnique(['user_id', 'department_id']);
            
            // Make user_id nullable for guest users
            $table->string('user_id')->nullable()->change();
            
            // Add curriculum_id for more specific notifications
            $table->string('curriculum_id')->nullable()->after('department_id');
            
            // Re-add foreign key without cascade (so guest subscriptions persist)
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('curriculum_id')->references('id')->on('curricula')->onDelete('set null');
            
            // Add index on email for guest user lookups
            $table->index('email');
            
            // Add composite index for efficient querying
            $table->index(['department_id', 'curriculum_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedule_notifications', function (Blueprint $table) {
            // Remove new foreign key
            $table->dropForeign(['curriculum_id']);
            
            // Remove new indexes
            $table->dropIndex(['email']);
            $table->dropIndex(['department_id', 'curriculum_id', 'is_active']);
            
            // Remove curriculum_id column
            $table->dropColumn('curriculum_id');
            
            // Restore original user_id constraint
            $table->dropForeign(['user_id']);
            $table->string('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            
            // Restore original unique constraint
            $table->unique(['user_id', 'department_id']);
        });
    }
};
