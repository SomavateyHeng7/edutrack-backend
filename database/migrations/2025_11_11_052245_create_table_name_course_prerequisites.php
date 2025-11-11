<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_prerequisites', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('course_id');
            $table->string('prerequisite_id');
            $table->timestamps();
            
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
            $table->foreign('prerequisite_id')->references('id')->on('courses')->onDelete('cascade');
            
            $table->unique(['course_id', 'prerequisite_id']);
            $table->index(['course_id']);
            $table->index(['prerequisite_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_prerequisites');
    }
};
