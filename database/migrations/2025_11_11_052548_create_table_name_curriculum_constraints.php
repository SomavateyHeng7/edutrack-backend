<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curriculum_constraints', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('curriculum_id');
            $table->enum('type', ['MINIMUM_GPA', 'SENIOR_STANDING', 'TOTAL_CREDITS', 'CATEGORY_CREDITS', 'CUSTOM']);
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_required')->default(true);
            $table->json('config')->nullable();
            $table->timestamps();
            
            $table->foreign('curriculum_id')->references('id')->on('curricula')->onDelete('cascade');
            
            $table->unique(['curriculum_id', 'type', 'name']);
            $table->index(['curriculum_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curriculum_constraints');
    }
};
