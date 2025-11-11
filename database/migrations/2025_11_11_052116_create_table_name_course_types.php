<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_types', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('color');
            $table->string('department_id');
            $table->timestamps();
            
            $table->foreign('department_id')->references('id')->on('departments');
            $table->unique(['name', 'department_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_types');
    }
};
