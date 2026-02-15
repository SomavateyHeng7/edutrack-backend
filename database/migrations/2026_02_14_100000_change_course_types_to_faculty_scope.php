<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration changes course_types from being department-scoped to faculty-scoped.
     * This allows all departments within a faculty to share the same category definitions.
     */
    public function up(): void
    {
        // Step 1: Add faculty_id column
        Schema::table('course_types', function (Blueprint $table) {
            $table->string('faculty_id')->nullable()->after('department_id');
            $table->foreign('faculty_id')->references('id')->on('faculties');
        });

        // Step 2: Populate faculty_id from department's faculty
        DB::statement('
            UPDATE course_types
            SET faculty_id = (
                SELECT faculty_id FROM departments WHERE departments.id = course_types.department_id
            )
        ');

        // Step 3: Make faculty_id not nullable now that it's populated
        Schema::table('course_types', function (Blueprint $table) {
            $table->string('faculty_id')->nullable(false)->change();
        });

        // Step 4: Deduplicate course types within each faculty
        // For each faculty, keep only unique course types by name+parent
        // and update any department_course_types references
        $this->deduplicateCourseTypes();

        // Step 5: Update unique constraint (name should be unique per faculty+parent, not department)
        Schema::table('course_types', function (Blueprint $table) {
            // Drop old unique constraint
            $table->dropUnique(['name', 'department_id']);
        });

        // Step 6: Drop department_id column (no longer needed)
        Schema::table('course_types', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn('department_id');
        });

        // Step 7: Add new unique constraint
        Schema::table('course_types', function (Blueprint $table) {
            $table->unique(['name', 'faculty_id', 'parent_course_type_id'], 'course_types_name_faculty_parent_unique');
        });

        // Step 8: Add index for faculty lookups
        Schema::table('course_types', function (Blueprint $table) {
            $table->index('faculty_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-add department_id
        Schema::table('course_types', function (Blueprint $table) {
            $table->dropIndex('course_types_faculty_id_index');
            $table->dropUnique('course_types_name_faculty_parent_unique');
            
            $table->string('department_id')->nullable()->after('id');
            $table->foreign('department_id')->references('id')->on('departments');
        });

        // Populate department_id from faculty's first department
        DB::statement('
            UPDATE course_types
            SET department_id = (
                SELECT id FROM departments WHERE departments.faculty_id = course_types.faculty_id LIMIT 1
            )
        ');

        Schema::table('course_types', function (Blueprint $table) {
            $table->string('department_id')->nullable(false)->change();
            $table->unique(['name', 'department_id']);
            
            $table->dropForeign(['faculty_id']);
            $table->dropColumn('faculty_id');
        });
    }

    /**
     * Deduplicate course types that have the same name within a faculty.
     * Keep one, update references to point to the kept one.
     */
    private function deduplicateCourseTypes(): void
    {
        // Get all faculties
        $faculties = DB::table('faculties')->pluck('id');

        foreach ($faculties as $facultyId) {
            // Find duplicate course types by name and parent within this faculty
            $duplicates = DB::table('course_types')
                ->select('name', 'parent_course_type_id', DB::raw('COUNT(*) as count'), DB::raw('MIN(id) as keep_id'))
                ->where('faculty_id', $facultyId)
                ->groupBy('name', 'parent_course_type_id')
                ->having(DB::raw('COUNT(*)'), '>', 1)
                ->get();

            foreach ($duplicates as $dup) {
                // Get IDs to remove (all except the one we're keeping)
                $toRemove = DB::table('course_types')
                    ->where('faculty_id', $facultyId)
                    ->where('name', $dup->name)
                    ->where(function ($q) use ($dup) {
                        if ($dup->parent_course_type_id === null) {
                            $q->whereNull('parent_course_type_id');
                        } else {
                            $q->where('parent_course_type_id', $dup->parent_course_type_id);
                        }
                    })
                    ->where('id', '!=', $dup->keep_id)
                    ->pluck('id');

                // Update department_course_types to point to the kept course type
                DB::table('department_course_types')
                    ->whereIn('course_type_id', $toRemove)
                    ->update(['course_type_id' => $dup->keep_id]);

                // Update sub_category_pools to point to the kept course type
                // (prevents CASCADE DELETE from removing pool data)
                if (Schema::hasTable('sub_category_pools')) {
                    DB::table('sub_category_pools')
                        ->whereIn('course_type_id', $toRemove)
                        ->update(['course_type_id' => $dup->keep_id]);
                }

                // Update any children's parent_course_type_id to point to kept one
                DB::table('course_types')
                    ->whereIn('parent_course_type_id', $toRemove)
                    ->update(['parent_course_type_id' => $dup->keep_id]);

                // Delete duplicate course types
                DB::table('course_types')
                    ->whereIn('id', $toRemove)
                    ->delete();
            }
        }
    }
};
