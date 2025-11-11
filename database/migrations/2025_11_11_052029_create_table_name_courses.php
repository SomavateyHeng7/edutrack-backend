<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->integer('credits');
            $table->string('credit_hours');
            $table->text('description')->nullable();
            $table->boolean('requires_permission')->default(false);
            $table->boolean('summer_only')->default(false);
            $table->boolean('requires_senior_standing')->default(false);
            $table->integer('min_credit_threshold')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['code']);
            $table->index(['name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
