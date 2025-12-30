<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tentative_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('semester', 100);
            $table->string('version', 50);
            $table->timestamp('version_timestamp')->nullable();
            $table->string('department_id')->nullable();
            $table->string('department_name')->nullable();
            $table->string('batch', 100)->nullable();
            $table->string('curriculum_id')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
            $table->foreign('curriculum_id')->references('id')->on('curricula')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');

            // Indexes
            $table->index('department_id');
            $table->index('curriculum_id');
            $table->index('semester');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tentative_schedules');
    }
};
