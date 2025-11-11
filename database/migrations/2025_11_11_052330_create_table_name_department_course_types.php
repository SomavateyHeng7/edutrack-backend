<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('department_course_types', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('course_id');
            $table->string('department_id');
            $table->string('course_type_id');
            $table->timestamp('assigned_at')->useCurrent();
            $table->string('assigned_by_id')->nullable();
            $table->timestamps();
            
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('cascade');
            $table->foreign('course_type_id')->references('id')->on('course_types')->onDelete('cascade');
            $table->foreign('assigned_by_id')->references('id')->on('users');
            
            $table->unique(['course_id', 'department_id']);
            $table->index(['department_id']);
            $table->index(['course_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_course_types');
    }
};
