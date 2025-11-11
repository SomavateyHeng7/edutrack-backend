<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blacklist_courses', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('blacklist_id');
            $table->string('course_id');
            $table->timestamps();
            
            $table->foreign('blacklist_id')->references('id')->on('blacklists')->onDelete('cascade');
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
            
            $table->unique(['blacklist_id', 'course_id']);
            $table->index(['blacklist_id']);
            $table->index(['course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blacklist_courses');
    }
};
