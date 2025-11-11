<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curriculum_blacklists', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('curriculum_id');
            $table->string('blacklist_id');
            $table->timestamps();
            
            $table->foreign('curriculum_id')->references('id')->on('curricula')->onDelete('cascade');
            $table->foreign('blacklist_id')->references('id')->on('blacklists')->onDelete('cascade');
            
            $table->unique(['curriculum_id', 'blacklist_id']);
            $table->index(['curriculum_id']);
            $table->index(['blacklist_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curriculum_blacklists');
    }
};