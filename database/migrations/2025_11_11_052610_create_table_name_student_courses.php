<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_courses', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('student_id');
            $table->string('course_id');
            $table->enum('status', ['IN_PROGRESS', 'COMPLETED', 'FAILED', 'DROPPED', 'PENDING']);
            $table->string('grade')->nullable();
            $table->string('semester')->nullable();
            $table->integer('year')->nullable();
            $table->integer('credits')->nullable();
            $table->timestamps();
            
            $table->foreign('student_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
            
            $table->unique(['student_id', 'course_id']);
            $table->index(['student_id']);
            $table->index(['course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_courses');
    }
};
