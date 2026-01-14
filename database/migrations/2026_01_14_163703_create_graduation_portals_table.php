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
        Schema::create('graduation_portals', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('batch')->nullable();
            $table->string('curriculum')->nullable();
            $table->string('curriculum_id')->nullable();
            $table->date('deadline');
            $table->enum('status', ['active', 'closed'])->default('active');
            $table->string('pin', 20)->unique();
            $table->json('accepted_formats')->nullable(); // Store accepted file formats as JSON
            $table->string('created_by')->nullable(); // UUID from users table
            $table->string('department_id')->nullable(); // String ID from departments table
            $table->timestamps();
            
            // Indexes
            $table->index('status');
            $table->index('curriculum_id');
            $table->index('department_id');
            $table->index('created_by');
            
            // Foreign keys
            $table->foreign('curriculum_id')
                  ->references('id')
                  ->on('curricula')
                  ->onDelete('set null');
            
            $table->foreign('created_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
            
            $table->foreign('department_id')
                  ->references('id')
                  ->on('departments')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('graduation_portals');
    }
};
