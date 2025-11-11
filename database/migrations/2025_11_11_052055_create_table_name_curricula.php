<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curricula', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('year');
            $table->string('version')->default('1.0');
            $table->text('description')->nullable();
            $table->string('start_id');
            $table->string('end_id');
            $table->boolean('is_active')->default(true);
            $table->string('department_id');
            $table->string('faculty_id');
            $table->string('created_by_id');
            $table->timestamps();
            
            $table->foreign('department_id')->references('id')->on('departments');
            $table->foreign('faculty_id')->references('id')->on('faculties');
            $table->foreign('created_by_id')->references('id')->on('users');
            
            $table->unique(['year', 'start_id', 'end_id', 'department_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curricula');
    }
};
