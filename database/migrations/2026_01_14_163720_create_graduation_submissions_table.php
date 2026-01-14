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
        Schema::create('graduation_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portal_id')->constrained('graduation_portals')->onDelete('cascade');
            $table->string('student_id'); // UUID from users table
            $table->string('file_name');
            $table->string('file_path');
            $table->bigInteger('file_size')->default(0);
            $table->enum('status', ['pending', 'processing', 'validated', 'has_issues', 'approved', 'rejected'])->default('pending');
            $table->json('validation_result')->nullable(); // Store validation results as JSON
            $table->text('notes')->nullable(); // Chairperson notes
            $table->string('reviewed_by')->nullable(); // UUID from users table
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('portal_id');
            $table->index('student_id');
            $table->index('status');
            $table->index('reviewed_by');
            
            // Foreign keys
            $table->foreign('student_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
            
            $table->foreign('reviewed_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
            
            // Unique constraint - one submission per student per portal
            $table->unique(['portal_id', 'student_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('graduation_submissions');
    }
};
