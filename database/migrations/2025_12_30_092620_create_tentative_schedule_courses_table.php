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
        Schema::create('tentative_schedule_courses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tentative_schedule_id');
            $table->string('course_id');
            $table->string('section', 50)->nullable();
            $table->json('days')->nullable(); // JSON array of days
            $table->string('time', 100)->nullable();
            $table->string('instructor')->nullable();
            $table->integer('seat_limit')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('tentative_schedule_id')
                  ->references('id')
                  ->on('tentative_schedules')
                  ->onDelete('cascade');
            $table->foreign('course_id')
                  ->references('id')
                  ->on('courses')
                  ->onDelete('cascade');

            // Indexes
            $table->index('tentative_schedule_id');
            $table->index('course_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tentative_schedule_courses');
    }
};
