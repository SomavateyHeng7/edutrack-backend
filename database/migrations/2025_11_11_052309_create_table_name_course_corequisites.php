<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_corequisites', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('course_id');
            $table->string('corequisite_id');
            $table->timestamps();
            
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
            $table->foreign('corequisite_id')->references('id')->on('courses')->onDelete('cascade');
            
            $table->unique(['course_id', 'corequisite_id']);
            $table->index(['course_id']);
            $table->index(['corequisite_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_corequisites');
    }
};
