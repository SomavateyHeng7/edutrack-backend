<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('concentration_courses', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('concentration_id');
            $table->string('course_id');
            $table->timestamps();
            
            $table->foreign('concentration_id')->references('id')->on('concentrations')->onDelete('cascade');
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
            
            $table->unique(['concentration_id', 'course_id']);
            $table->index(['concentration_id']);
            $table->index(['course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('concentration_courses');
    }
};