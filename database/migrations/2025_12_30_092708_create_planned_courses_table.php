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
        Schema::create('planned_courses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('student_id');
            $table->string('course_id');
            $table->string('semester', 50); // e.g., "Fall 2024", "Spring 2025"
            $table->timestamps();

            // Foreign keys
            $table->foreign('student_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
            $table->foreign('course_id')
                  ->references('id')
                  ->on('courses')
                  ->onDelete('cascade');

            // Indexes
            $table->index('student_id');
            $table->index('course_id');
            
            // Unique constraint to prevent duplicate planned courses
            $table->unique(['student_id', 'course_id', 'semester']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('planned_courses');
    }
};
