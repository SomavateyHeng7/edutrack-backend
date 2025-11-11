<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curriculum_courses', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('curriculum_id');
            $table->string('course_id');
            $table->boolean('is_required')->default(true);
            $table->string('semester')->nullable();
            $table->integer('year')->nullable();
            $table->integer('position')->nullable();
            $table->timestamps();
            
            $table->foreign('curriculum_id')->references('id')->on('curricula')->onDelete('cascade');
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
            
            $table->unique(['curriculum_id', 'course_id']);
            $table->index(['curriculum_id']);
            $table->index(['course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curriculum_courses');
    }
};
