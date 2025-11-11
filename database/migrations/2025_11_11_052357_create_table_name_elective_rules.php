<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('elective_rules', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('curriculum_id');
            $table->string('category');
            $table->integer('required_credits');
            $table->text('description')->nullable();
            $table->timestamps();
            
            $table->foreign('curriculum_id')->references('id')->on('curricula')->onDelete('cascade');
            
            $table->unique(['curriculum_id', 'category']);
            $table->index(['curriculum_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('elective_rules');
    }
};
