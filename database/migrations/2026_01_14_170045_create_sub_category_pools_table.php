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
        Schema::create('sub_category_pools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pool_id')->constrained('curriculum_credit_pools')->onDelete('cascade');
            $table->string('course_type_id'); // String ID from course_types (sub-category)
            $table->integer('required_credits')->default(0);
            $table->integer('order_index')->default(0);
            $table->timestamps();
            
            // Foreign key
            $table->foreign('course_type_id')
                  ->references('id')
                  ->on('course_types')
                  ->onDelete('cascade');
            
            // Indexes
            $table->index('pool_id');
            $table->index('course_type_id');
            
            // Unique constraint - one sub-category per course type per pool
            $table->unique(['pool_id', 'course_type_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sub_category_pools');
    }
};
