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
        Schema::create('curriculum_credit_pools', function (Blueprint $table) {
            $table->id();
            $table->string('curriculum_id');
            $table->string('name');
            $table->string('top_level_course_type_id'); // String ID from course_types
            $table->boolean('enabled')->default(true);
            $table->integer('order_index')->default(0);
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('curriculum_id')
                  ->references('id')
                  ->on('curricula')
                  ->onDelete('cascade');
            
            $table->foreign('top_level_course_type_id')
                  ->references('id')
                  ->on('course_types')
                  ->onDelete('cascade');
            
            // Indexes
            $table->index('curriculum_id');
            $table->index('top_level_course_type_id');
            $table->index('enabled');
            
            // Unique constraint - one pool per top-level course type per curriculum
            $table->unique(['curriculum_id', 'top_level_course_type_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('curriculum_credit_pools');
    }
};
