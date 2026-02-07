<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates the graduation_notifications table for notifying
     * chairpersons/advisors when students submit to graduation portals.
     */
    public function up(): void
    {
        Schema::create('graduation_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('user_id'); // UUID from users table
            $table->string('type'); // 'new_submission', 'submission_validated', etc.
            $table->unsignedBigInteger('portal_id');
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable(); // submission_id, student_identifier, etc.
            $table->boolean('read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('user_id');
            $table->index('portal_id');
            $table->index('type');
            $table->index('read');
            $table->index(['user_id', 'read']); // For unread count queries
            $table->index('created_at');
            
            // Foreign keys
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
            
            $table->foreign('portal_id')
                  ->references('id')
                  ->on('graduation_portals')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('graduation_notifications');
    }
};
