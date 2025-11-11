<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('user_id');
            $table->string('entity_type');
            $table->string('entity_id');
            $table->enum('action', ['CREATE', 'UPDATE', 'DELETE', 'ASSIGN', 'UNASSIGN', 'IMPORT', 'EXPORT']);
            $table->json('changes')->nullable();
            $table->text('description')->nullable();
            $table->string('curriculum_id')->nullable();
            $table->string('course_id')->nullable();
            $table->string('concentration_id')->nullable();
            $table->string('blacklist_id')->nullable();
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('curriculum_id')->references('id')->on('curricula');
            $table->foreign('course_id')->references('id')->on('courses');
            $table->foreign('concentration_id')->references('id')->on('concentrations');
            $table->foreign('blacklist_id')->references('id')->on('blacklists');
            
            $table->index(['user_id']);
            $table->index(['entity_type', 'entity_id']);
            $table->index(['curriculum_id']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
