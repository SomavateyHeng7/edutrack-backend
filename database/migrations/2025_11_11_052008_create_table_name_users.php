<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->enum('role', ['STUDENT', 'ADVISOR', 'CHAIRPERSON', 'SUPER_ADMIN'])->default('CHAIRPERSON');
            $table->string('faculty_id');
            $table->string('department_id');
            $table->string('advisor_id')->nullable();
            $table->float('gpa')->nullable();
            $table->integer('credits')->nullable();
            $table->integer('scholarship_hour')->nullable();
            $table->string('reset_token')->nullable();
            $table->timestamp('reset_token_expiry')->nullable();
            $table->timestamps();
            
            $table->foreign('faculty_id')->references('id')->on('faculties');
            $table->foreign('department_id')->references('id')->on('departments');
            
            $table->index(['department_id']);
            $table->index(['faculty_id']);
            $table->index(['role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
