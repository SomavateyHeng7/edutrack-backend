<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curriculum_concentrations', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('curriculum_id');
            $table->string('concentration_id');
            $table->integer('required_courses');
            $table->timestamps();
            
            $table->foreign('curriculum_id')->references('id')->on('curricula')->onDelete('cascade');
            $table->foreign('concentration_id')->references('id')->on('concentrations')->onDelete('cascade');
            
            $table->unique(['curriculum_id', 'concentration_id']);
            $table->index(['curriculum_id']);
            $table->index(['concentration_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curriculum_concentrations');
    }
};
