<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Curriculum;
use App\Models\Course;

class AvailableCourseController extends Controller
{
    // GET /api/available-courses?curriculumId=xxx&departmentId=yyy
    public function index(Request $request)
    {
        $curriculumId = $request->query('curriculumId');
        $departmentId = $request->query('departmentId');

        if (!$curriculumId || !$departmentId) {
            return response()->json([
                'error' => 'Missing curriculumId or departmentId parameter'
            ], 400);
        }

        // Fetch curriculum with its courses and their prerequisites
        $curriculum = Curriculum::with([
            'curriculumCourses.course.prerequisites.prerequisite',
            'curriculumCourses.course.corequisites.corequisite',
            'curriculumCourses.course.departmentCourseTypes' => function ($q) use ($departmentId) {
                $q->where('departmentId', $departmentId)->with('courseType');
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
            $category = 'Unassigned';
            if ($course->departmentCourseTypes && count($course->departmentCourseTypes) > 0) {
                $category = $course->departmentCourseTypes[0]->courseType->name ?? 'Unassigned';
            }

            // Prerequisites
            $prerequisites = $course->prerequisites->map(fn($prereq) => $prereq->prerequisite->code)->toArray();

            // Corequisites
            $corequisites = $course->corequisites->map(fn($coreq) => $coreq->corequisite->code)->toArray();

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

            $availableCourses[] = [
                'code' => $course->code,
                'title' => $course->name,
                'credits' => $course->creditHours ?? $course->credits ?? 0,
                'description' => $course->description ?? '',
                'prerequisites' => $prerequisites,
                'corequisites' => $corequisites,
                'bannedWith' => array_values(array_unique($bannedWith)),
                'category' => $category,
                'level' => $level,
                'requiresPermission' => $course->requiresPermission ?? false,
                'summerOnly' => $course->summerOnly ?? false,
                'requiresSeniorStanding' => $course->requiresSeniorStanding ?? false,
                'minCreditThreshold' => $course->minCreditThreshold ?? null,
            ];
        }

        // Fetch additional department courses not in curriculum
        $curriculumCourseCodes = collect($availableCourses)->pluck('code')->toArray();

        $departmentCourses = Course::with([
            'prerequisites.prerequisite',
            'corequisites.corequisite',
            'departmentCourseTypes' => function ($q) use ($departmentId) {
                $q->where('departmentId', $departmentId)->with('courseType');
            },
            'blacklistCourses.blacklist.courses.course'
        ])->whereHas('departmentCourseTypes', function ($q) use ($departmentId) {
            $q->where('departmentId', $departmentId);
        })->get();

        $additionalCourses = [];
        foreach ($departmentCourses as $course) {
            if (in_array($course->code, $curriculumCourseCodes)) continue;

            $category = 'Free Elective';
            if ($course->departmentCourseTypes && count($course->departmentCourseTypes) > 0) {
                $category = $course->departmentCourseTypes[0]->courseType->name ?? 'Free Elective';
            }

            $prerequisites = $course->prerequisites->map(fn($prereq) => $prereq->prerequisite->code)->toArray();
            $corequisites = $course->corequisites->map(fn($coreq) => $coreq->corequisite->code)->toArray();

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
                'credits' => $course->creditHours ?? $course->credits ?? 0,
                'description' => $course->description ?? '',
                'prerequisites' => $prerequisites,
                'corequisites' => $corequisites,
                'bannedWith' => array_values(array_unique($bannedWith)),
                'category' => $category,
                'level' => $level,
            ];
        }

        $allAvailableCourses = array_merge($availableCourses, $additionalCourses);

        return response()->json([
            'courses' => $allAvailableCourses,
            'totalCourses' => count($allAvailableCourses)
        ]);
    }
}