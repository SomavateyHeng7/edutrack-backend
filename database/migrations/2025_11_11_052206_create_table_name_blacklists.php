<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blacklists', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('department_id');
            $table->string('created_by_id');
            $table->timestamps();
            
            $table->foreign('department_id')->references('id')->on('departments');
            $table->foreign('created_by_id')->references('id')->on('users');
            
            $table->unique(['name', 'department_id', 'created_by_id']);
            $table->index(['created_by_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blacklists');
    }
};
