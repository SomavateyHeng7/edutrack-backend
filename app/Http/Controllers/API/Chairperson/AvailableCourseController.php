<?php

namespace App\Http\Controllers\API\Chairperson;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Curriculum;
use App\Models\Course;

class AvailableCourseController extends Controller
{
    // GET /api/available-courses?curriculumId=xxx&departmentId=yyy
    public function index(Request $request)
    {
        $curriculumId = $request->query('curriculum_id');
        $departmentId = $request->query('department_id');

        if (!$curriculumId || !$departmentId) {
            return response()->json([
                'error' => 'Missing curriculumId or departmentId parameter'
            ], 400);
        }

        // Fetch curriculum with its courses and their prerequisites
        $curriculum = Curriculum::with([
            'curriculumCourses.course.prerequisites',
            'curriculumCourses.course.corequisites',
            'curriculumCourses.course.departmentCourseTypes' => function ($q) use ($departmentId) {
                $q->where('department_id', $departmentId)->with('courseType');
            },
            'curriculumCourses.course.blacklistCourses.blacklist.courses.course'
        ])->find($curriculumId);

        if (!$curriculum) {
            return response()->json([
                'error' => 'Curriculum not found'
            ], 404);
        }

        // Transform curriculum courses
        $availableCourses = [];
        foreach ($curriculum->curriculumCourses as $currCourse) {
            $course = $currCourse->course;

            // Category logic
            $category = 'Uncategorized';
            if ($course->departmentCourseTypes && count($course->departmentCourseTypes) > 0) {
                $category = $course->departmentCourseTypes[0]->courseType->name ?? 'Uncategorized';
            }

            // Prerequisites (BelongsToMany returns Course models directly)
            $prerequisites = $course->prerequisites->map(fn($prereq) => $prereq->code)->toArray();

            // Corequisites (BelongsToMany returns Course models directly)
            $corequisites = $course->corequisites->map(fn($coreq) => $coreq->code)->toArray();

            // Banned combinations
            $bannedWith = [];
            foreach ($course->blacklistCourses as $blacklistCourse) {
                foreach ($blacklistCourse->blacklist->courses as $otherBlacklistCourse) {
                    if ($otherBlacklistCourse->course->code !== $course->code) {
                        $bannedWith[] = $otherBlacklistCourse->course->code;
                    }
                }
            }

            // Level from code
            preg_match('/\d/', $course->code, $levelMatch);
            $level = isset($levelMatch[0]) ? intval($levelMatch[0]) : 1;

            // Use CurriculumCourse overrides if set, otherwise fall back to Course defaults
            $requiresPermission = $currCourse->override_requires_permission ?? $course->requires_permission ?? false;
            $summerOnly = $currCourse->override_summer_only ?? $course->summer_only ?? false;
            $requiresSeniorStanding = $currCourse->override_requires_senior_standing ?? $course->requires_senior_standing ?? false;
            $minCreditThreshold = $currCourse->override_min_credit_threshold ?? $course->min_credit_threshold ?? null;

            $availableCourses[] = [
                'code' => $course->code,
                'title' => $course->name,
                'credits' => $course->credit_hours ?? $course->credits ?? 0,
                'description' => $course->description ?? '',
                'prerequisites' => $prerequisites,
                'corequisites' => $corequisites,
                'bannedWith' => array_values(array_unique($bannedWith)),
                'category' => $category,
                'level' => $level,
                'requiresPermission' => (bool) $requiresPermission,
                'summerOnly' => (bool) $summerOnly,
                'requiresSeniorStanding' => (bool) $requiresSeniorStanding,
                'minCreditThreshold' => $minCreditThreshold,
            ];
        }

        // Fetch additional department courses not in curriculum
        $curriculumCourseCodes = collect($availableCourses)->pluck('code')->toArray();

        $departmentCourses = Course::with([
            'prerequisites.prerequisite',
            'corequisites.corequisite',
            'departmentCourseTypes' => function ($q) use ($departmentId) {
                $q->where('department_id', $departmentId)->with('courseType');
            },
            'blacklistCourses.blacklist.courses.course'
        ])->whereHas('departmentCourseTypes', function ($q) use ($departmentId) {
            $q->where('department_id', $departmentId);
        })->get();

        $additionalCourses = [];
        foreach ($departmentCourses as $course) {
            if (in_array($course->code, $curriculumCourseCodes)) continue;

            $category = 'Free Elective';
            if ($course->departmentCourseTypes && count($course->departmentCourseTypes) > 0) {
                $category = $course->departmentCourseTypes[0]->courseType->name ?? 'Free Elective';
            }

            $prerequisites = $course->prerequisites->map(fn($prereq) => $prereq->code)->toArray();
            $corequisites = $course->corequisites->map(fn($coreq) => $coreq->code)->toArray();

            $bannedWith = [];
            foreach ($course->blacklistCourses as $blacklistCourse) {
                foreach ($blacklistCourse->blacklist->courses as $otherBlacklistCourse) {
                    if ($otherBlacklistCourse->course->code !== $course->code) {
                        $bannedWith[] = $otherBlacklistCourse->course->code;
                    }
                }
            }

            preg_match('/\d/', $course->code, $levelMatch);
            $level = isset($levelMatch[0]) ? intval($levelMatch[0]) : 1;

            $additionalCourses[] = [
                'code' => $course->code,
                'title' => $course->name,
                'credits' => $course->credit_hours ?? $course->credits ?? 0,
                'description' => $course->description ?? '',
                'prerequisites' => $prerequisites,
                'corequisites' => $corequisites,
                'bannedWith' => array_values(array_unique($bannedWith)),
                'category' => $category,
                'level' => $level,
                'requiresPermission' => (bool) ($course->requires_permission ?? false),
                'summerOnly' => (bool) ($course->summer_only ?? false),
                'requiresSeniorStanding' => (bool) ($course->requires_senior_standing ?? false),
                'minCreditThreshold' => $course->min_credit_threshold ?? null,
            ];
        }

        $allAvailableCourses = array_merge($availableCourses, $additionalCourses);

        return response()->json([
            'courses' => $allAvailableCourses,
            'totalCourses' => count($allAvailableCourses)
        ]);
    }
}