<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates the graduation_portal_logs table for audit logging.
     * This tracks all actions performed on graduation portals.
     */
    public function up(): void
    {
        Schema::create('graduation_portal_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('portal_id');
            $table->string('action'); // created, updated, closed, pin_verified, submission_received, etc.
            $table->string('performed_by')->nullable(); // User ID (nullable for anonymous actions)
            $table->json('metadata')->nullable(); // Additional context data
            $table->timestamp('created_at')->useCurrent();
            
            // Indexes
            $table->index('portal_id');
            $table->index('action');
            $table->index('performed_by');
            $table->index('created_at');
            
            // Foreign keys
            $table->foreign('portal_id')
                  ->references('id')
                  ->on('graduation_portals')
                  ->onDelete('cascade');
            
            $table->foreign('performed_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('graduation_portal_logs');
    }
};
