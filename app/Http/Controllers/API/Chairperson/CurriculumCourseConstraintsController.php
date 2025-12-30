<?php

namespace App\Http\Controllers\API\Chairperson;

use App\Http\Controllers\Controller;
use App\Models\CurriculumCourse;
use App\Models\CurriculumCoursePrerequisite;
use App\Models\CurriculumCourseCorequisite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CurriculumCourseConstraintsController extends Controller
{
    /**
     * Get constraints for a specific curriculum course
     * 
     * GET /api/curricula/{curriculumId}/courses/{curriculumCourseId}/constraints
     */
    public function index($curriculumId, $curriculumCourseId)
    {
        $curriculumCourse = CurriculumCourse::with([
            'course.prerequisites.prerequisite',
            'course.corequisites.corequisite',
            'curriculumPrerequisites.prerequisiteCourse.course',
            'curriculumCorequisites.corequisiteCourse.course'
        ])
        ->where('curriculum_id', $curriculumId)
        ->where('id', $curriculumCourseId)
        ->firstOrFail();

        $course = $curriculumCourse->course;

        // Base flags from course
        $baseFlags = [
            'requiresPermission' => (bool) $course->requires_permission,
            'summerOnly' => (bool) $course->summer_only,
            'requiresSeniorStanding' => (bool) $course->requires_senior_standing,
            'minCreditThreshold' => $course->min_credit_threshold,
        ];

        // Override flags from curriculum course
        $overrideFlags = [
            'overrideRequiresPermission' => $curriculumCourse->override_requires_permission,
            'overrideSummerOnly' => $curriculumCourse->override_summer_only,
            'overrideRequiresSeniorStanding' => $curriculumCourse->override_requires_senior_standing,
            'overrideMinCreditThreshold' => $curriculumCourse->override_min_credit_threshold,
        ];

        // Merged flags (overrides take precedence)
        $mergedFlags = [
            'requiresPermission' => $overrideFlags['overrideRequiresPermission'] !== null 
                ? (bool) $overrideFlags['overrideRequiresPermission'] 
                : $baseFlags['requiresPermission'],
            'summerOnly' => $overrideFlags['overrideSummerOnly'] !== null 
                ? (bool) $overrideFlags['overrideSummerOnly'] 
                : $baseFlags['summerOnly'],
            'requiresSeniorStanding' => $overrideFlags['overrideRequiresSeniorStanding'] !== null 
                ? (bool) $overrideFlags['overrideRequiresSeniorStanding'] 
                : $baseFlags['requiresSeniorStanding'],
            'minCreditThreshold' => $overrideFlags['overrideMinCreditThreshold'] !== null 
                ? $overrideFlags['overrideMinCreditThreshold'] 
                : $baseFlags['minCreditThreshold'],
        ];

        // Base prerequisites from course
        $basePrerequisites = $course->prerequisites->map(fn($prereq) => [
            'courseId' => $prereq->prerequisite->id,
            'code' => $prereq->prerequisite->code,
            'name' => $prereq->prerequisite->name,
        ])->toArray();

        // Base corequisites from course
        $baseCorequisites = $course->corequisites->map(fn($coreq) => [
            'courseId' => $coreq->corequisite->id,
            'code' => $coreq->corequisite->code,
            'name' => $coreq->corequisite->name,
        ])->toArray();

        // Curriculum prerequisites
        $curriculumPrerequisites = $curriculumCourse->curriculumPrerequisites->map(fn($prereq) => [
            'id' => $prereq->id,
            'curriculumCourseId' => $prereq->prerequisite_course_id,
            'courseId' => $prereq->prerequisiteCourse->course->id,
            'code' => $prereq->prerequisiteCourse->course->code,
            'name' => $prereq->prerequisiteCourse->course->name,
            'credits' => $prereq->prerequisiteCourse->course->credits,
        ])->toArray();

        // Curriculum corequisites
        $curriculumCorequisites = $curriculumCourse->curriculumCorequisites->map(fn($coreq) => [
            'id' => $coreq->id,
            'curriculumCourseId' => $coreq->corequisite_course_id,
            'courseId' => $coreq->corequisiteCourse->course->id,
            'code' => $coreq->corequisiteCourse->course->code,
            'name' => $coreq->corequisiteCourse->course->name,
            'credits' => $coreq->corequisiteCourse->course->credits,
        ])->toArray();

        return response()->json([
            'success' => true,
            'curriculumCourse' => [
                'id' => $curriculumCourse->id,
                'curriculumId' => $curriculumCourse->curriculum_id,
                'courseId' => $course->id,
                'courseCode' => $course->code,
                'courseName' => $course->name,
            ],
            'baseFlags' => $baseFlags,
            'overrideFlags' => $overrideFlags,
            'mergedFlags' => $mergedFlags,
            'basePrerequisites' => $basePrerequisites,
            'baseCorequisites' => $baseCorequisites,
            'curriculumPrerequisites' => $curriculumPrerequisites,
            'curriculumCorequisites' => $curriculumCorequisites,
        ]);
    }

    /**
     * Update override flags for a curriculum course
     * 
     * PUT /api/curricula/{curriculumId}/courses/{curriculumCourseId}/constraints
     */
    public function update(Request $request, $curriculumId, $curriculumCourseId)
    {
        $curriculumCourse = CurriculumCourse::where('curriculum_id', $curriculumId)
            ->where('id', $curriculumCourseId)
            ->firstOrFail();

        $validated = $request->validate([
            'overrideRequiresPermission' => 'nullable|boolean',
            'overrideSummerOnly' => 'nullable|boolean',
            'overrideRequiresSeniorStanding' => 'nullable|boolean',
            'overrideMinCreditThreshold' => 'nullable|numeric|min:0',
        ]);

        // Update fields if provided
        if (array_key_exists('overrideRequiresPermission', $validated)) {
            $curriculumCourse->override_requires_permission = $validated['overrideRequiresPermission'];
        }
        if (array_key_exists('overrideSummerOnly', $validated)) {
            $curriculumCourse->override_summer_only = $validated['overrideSummerOnly'];
        }
        if (array_key_exists('overrideRequiresSeniorStanding', $validated)) {
            $curriculumCourse->override_requires_senior_standing = $validated['overrideRequiresSeniorStanding'];
        }
        if (array_key_exists('overrideMinCreditThreshold', $validated)) {
            $curriculumCourse->override_min_credit_threshold = $validated['overrideMinCreditThreshold'];
        }

        $curriculumCourse->save();

        return response()->json([
            'success' => true,
            'message' => 'Constraint overrides updated successfully',
            'overrides' => [
                'overrideRequiresPermission' => $curriculumCourse->override_requires_permission,
                'overrideSummerOnly' => $curriculumCourse->override_summer_only,
                'overrideRequiresSeniorStanding' => $curriculumCourse->override_requires_senior_standing,
                'overrideMinCreditThreshold' => $curriculumCourse->override_min_credit_threshold,
            ],
        ]);
    }

    /**
     * Add a prerequisite to a curriculum course
     * 
     * POST /api/curricula/{curriculumId}/courses/{curriculumCourseId}/prerequisites
     */
    public function addPrerequisite(Request $request, $curriculumId, $curriculumCourseId)
    {
        $validated = $request->validate([
            'targetCurriculumCourseId' => 'required|exists:curriculum_courses,id',
        ]);

        $curriculumCourse = CurriculumCourse::where('curriculum_id', $curriculumId)
            ->where('id', $curriculumCourseId)
            ->firstOrFail();

        $targetCurriculumCourse = CurriculumCourse::where('curriculum_id', $curriculumId)
            ->where('id', $validated['targetCurriculumCourseId'])
            ->firstOrFail();

        // Check for self-reference
        if ($curriculumCourseId === $validated['targetCurriculumCourseId']) {
            return response()->json(['error' => 'Cannot add self as prerequisite'], 400);
        }

        // Check if prerequisite already exists
        $existing = CurriculumCoursePrerequisite::where('curriculum_course_id', $curriculumCourseId)
            ->where('prerequisite_course_id', $validated['targetCurriculumCourseId'])
            ->exists();

        if ($existing) {
            return response()->json(['error' => 'Prerequisite already exists'], 409);
        }

        $prerequisite = CurriculumCoursePrerequisite::create([
            'curriculum_course_id' => $curriculumCourseId,
            'prerequisite_course_id' => $validated['targetCurriculumCourseId'],
        ]);

        // Load the related data
        $prerequisite->load('prerequisiteCourse.course');

        return response()->json([
            'success' => true,
            'message' => 'Prerequisite added successfully',
            'prerequisite' => [
                'id' => $prerequisite->id,
                'curriculumCourseId' => $prerequisite->prerequisite_course_id,
                'courseId' => $prerequisite->prerequisiteCourse->course->id,
                'code' => $prerequisite->prerequisiteCourse->course->code,
                'name' => $prerequisite->prerequisiteCourse->course->name,
                'credits' => $prerequisite->prerequisiteCourse->course->credits,
            ],
        ]);
    }

    /**
     * Remove a prerequisite from a curriculum course
     * 
     * DELETE /api/curricula/{curriculumId}/courses/{curriculumCourseId}/prerequisites/{relationId}
     */
    public function removePrerequisite($curriculumId, $curriculumCourseId, $relationId)
    {
        $prerequisite = CurriculumCoursePrerequisite::where('id', $relationId)
            ->where('curriculum_course_id', $curriculumCourseId)
            ->firstOrFail();

        $prerequisite->delete();

        return response()->json([
            'success' => true,
            'message' => 'Prerequisite removed successfully',
        ]);
    }

    /**
     * Add a corequisite to a curriculum course
     * 
     * POST /api/curricula/{curriculumId}/courses/{curriculumCourseId}/corequisites
     */
    public function addCorequisite(Request $request, $curriculumId, $curriculumCourseId)
    {
        $validated = $request->validate([
            'targetCurriculumCourseId' => 'required|exists:curriculum_courses,id',
        ]);

        $curriculumCourse = CurriculumCourse::where('curriculum_id', $curriculumId)
            ->where('id', $curriculumCourseId)
            ->firstOrFail();

        $targetCurriculumCourse = CurriculumCourse::where('curriculum_id', $curriculumId)
            ->where('id', $validated['targetCurriculumCourseId'])
            ->firstOrFail();

        // Check for self-reference
        if ($curriculumCourseId === $validated['targetCurriculumCourseId']) {
            return response()->json(['error' => 'Cannot add self as corequisite'], 400);
        }

        // Check if corequisite already exists
        $existing = CurriculumCourseCorequisite::where('curriculum_course_id', $curriculumCourseId)
            ->where('corequisite_course_id', $validated['targetCurriculumCourseId'])
            ->exists();

        if ($existing) {
            return response()->json(['error' => 'Corequisite already exists'], 409);
        }

        // Create bidirectional corequisites
        $corequisite = null;
        DB::transaction(function () use ($curriculumCourseId, $validated, &$corequisite) {
            $corequisite = CurriculumCourseCorequisite::create([
                'curriculum_course_id' => $curriculumCourseId,
                'corequisite_course_id' => $validated['targetCurriculumCourseId'],
            ]);

            CurriculumCourseCorequisite::create([
                'curriculum_course_id' => $validated['targetCurriculumCourseId'],
                'corequisite_course_id' => $curriculumCourseId,
            ]);
        });

        // Load the related data if corequisite is not null
        if ($corequisite) {
            $corequisite->load('corequisiteCourse.course');
        }

        return response()->json([
            'success' => true,
            'message' => 'Corequisite added successfully',
            'corequisite' => $corequisite ? [
                'id' => $corequisite->id,
                'curriculumCourseId' => $corequisite->corequisite_course_id,
                'courseId' => $corequisite->corequisiteCourse->course->id,
                'code' => $corequisite->corequisiteCourse->course->code,
                'name' => $corequisite->corequisiteCourse->course->name,
                'credits' => $corequisite->corequisiteCourse->course->credits,
            ] : null,
        ]);
    }

    /**
     * Remove a corequisite from a curriculum course
     * 
     * DELETE /api/curricula/{curriculumId}/courses/{curriculumCourseId}/corequisites/{relationId}
     */
    public function removeCorequisite($curriculumId, $curriculumCourseId, $relationId)
    {
        $corequisite = CurriculumCourseCorequisite::where('id', $relationId)
            ->where('curriculum_course_id', $curriculumCourseId)
            ->firstOrFail();

        // Remove both directions of the corequisite relationship
        DB::transaction(function () use ($corequisite) {
            CurriculumCourseCorequisite::where('curriculum_course_id', $corequisite->corequisite_course_id)
                ->where('corequisite_course_id', $corequisite->curriculum_course_id)
                ->delete();

            $corequisite->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Corequisite removed successfully',
        ]);
    }
}
