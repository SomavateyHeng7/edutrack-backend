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
        Schema::create('curriculum_course_prerequisites', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('curriculum_course_id');
            $table->string('prerequisite_course_id');
            $table->timestamps();
            
            $table->foreign('curriculum_course_id')->references('id')->on('curriculum_courses')->onDelete('cascade');
            $table->foreign('prerequisite_course_id')->references('id')->on('curriculum_courses')->onDelete('cascade');
            
            $table->unique(['curriculum_course_id', 'prerequisite_course_id']);
            $table->index(['curriculum_course_id']);
            $table->index(['prerequisite_course_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('curriculum_course_prerequisites');
    }
};
