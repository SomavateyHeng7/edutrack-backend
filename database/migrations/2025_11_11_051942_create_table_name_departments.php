<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('code');
            $table->string('faculty_id');
            $table->timestamps();
            
            $table->foreign('faculty_id')->references('id')->on('faculties');
            $table->unique(['code', 'faculty_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
