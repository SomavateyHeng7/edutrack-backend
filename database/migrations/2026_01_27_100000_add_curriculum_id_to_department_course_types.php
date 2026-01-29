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
        // First, drop the old unique constraint
        Schema::table('department_course_types', function (Blueprint $table) {
            $table->dropUnique(['course_id', 'department_id']);
        });

        // Then add the new column and constraints
        Schema::table('department_course_types', function (Blueprint $table) {
            $table->string('curriculum_id')->nullable()->after('department_id');
            
            $table->foreign('curriculum_id')
                ->references('id')
                ->on('curricula')
                ->onDelete('cascade');
            
            $table->unique(['course_id', 'department_id', 'curriculum_id'], 'dct_course_dept_curriculum_unique');
            
            $table->index('curriculum_id', 'idx_dct_curriculum');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('department_course_types', function (Blueprint $table) {
            $table->dropForeign(['curriculum_id']);
            $table->dropUnique('dct_course_dept_curriculum_unique');
            $table->dropIndex('idx_dct_curriculum');
            $table->dropColumn('curriculum_id');
            
            $table->unique(['course_id', 'department_id']);
        });
    }
};
