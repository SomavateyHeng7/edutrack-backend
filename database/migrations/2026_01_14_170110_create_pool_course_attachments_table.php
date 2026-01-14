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
        Schema::create('pool_course_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_category_pool_id')->constrained('sub_category_pools')->onDelete('cascade');
            $table->string('course_id'); // String ID from courses
            $table->timestamps();
            
            // Foreign key
            $table->foreign('course_id')
                  ->references('id')
                  ->on('courses')
                  ->onDelete('cascade');
            
            // Indexes
            $table->index('sub_category_pool_id');
            $table->index('course_id');
            
            // Unique constraint - one attachment per course per sub-category pool
            $table->unique(['sub_category_pool_id', 'course_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pool_course_attachments');
    }
};
