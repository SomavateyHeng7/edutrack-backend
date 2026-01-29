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
        Schema::table('course_types', function (Blueprint $table) {
            $table->string('parent_course_type_id')->nullable()->after('department_id');
            $table->integer('position')->default(0)->after('parent_course_type_id');
            $table->boolean('seeded')->default(false)->after('position');
            
            $table->foreign('parent_course_type_id')
                ->references('id')
                ->on('course_types')
                ->onDelete('set null');
            
            $table->index('parent_course_type_id', 'idx_course_types_parent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('course_types', function (Blueprint $table) {
            $table->dropForeign(['parent_course_type_id']);
            $table->dropIndex('idx_course_types_parent');
            $table->dropColumn([
                'parent_course_type_id',
                'position',
                'seeded'
            ]);
        });
    }
};
