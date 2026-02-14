<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Curriculum;

class PublicCurriculumController extends Controller
{
    // GET /api/public-curricula
    public function index(Request $request)
    {
        try {
            $facultyId = $request->query('faculty_id');
            $departmentId = $request->query('department_id');
            $year = $request->query('year');
            $curriculumId = $request->query('curriculum_id');

            $query = Curriculum::with([
                'department',
                'faculty',
                'curriculumCourses.course.departmentCourseTypes.courseType',
                'curriculumCourses.course.prerequisites.prerequisite',
                'curriculumCourses.course.corequisites.corequisite',
                'curriculumCourses.curriculumPrerequisites.prerequisiteCourse.course',
                'curriculumCourses.curriculumCorequisites.corequisiteCourse.course',
                'curriculumConstraints',
                'electiveRules'
            ])->where('is_active', true);

            if ($facultyId) $query->where('faculty_id', $facultyId);
            if ($departmentId) $query->where('department_id', $departmentId);
            if ($year) $query->where('year', $year);
            if ($curriculumId) $query->where('id', $curriculumId);

            $curricula = $query->orderBy('updated_at', 'desc')->get();

            $sanitizedCurricula = $curricula->map(function ($curriculum) {
                $courses = $curriculum->curriculumCourses->map(function ($curriculumCourse) use ($curriculum) {
                    $course = $curriculumCourse->course;

                    $curriculumPrereqs = optional($curriculumCourse->curriculumPrerequisites)->map(function ($prereq) {
                        return optional(optional($prereq->prerequisiteCourse)->course)->code;
                    })->filter()->values()->all();

                    $curriculumCoreqs = optional($curriculumCourse->curriculumCorequisites)->map(function ($coreq) {
                        return optional(optional($coreq->corequisiteCourse)->course)->code;
                    })->filter()->values()->all();

                    $prerequisites = !empty($curriculumPrereqs)
                        ? $curriculumPrereqs
                        : (optional($course->prerequisites)->map(function ($prereq) {
                            return optional($prereq->prerequisite)->code;
                        })->filter()->values()->all() ?? []);

                    $corequisites = !empty($curriculumCoreqs)
                        ? $curriculumCoreqs
                        : (optional($course->corequisites)->map(function ($coreq) {
                            return optional($coreq->corequisite)->code;
                        })->filter()->values()->all() ?? []);

                    $requiresPermission = $curriculumCourse->override_requires_permission ?? $course->requires_permission ?? false;
                    $summerOnly = $curriculumCourse->override_summer_only ?? $course->summer_only ?? false;
                    $requiresSeniorStanding = $curriculumCourse->override_requires_senior_standing ?? $course->requires_senior_standing ?? false;
                    $minCreditThreshold = $curriculumCourse->override_min_credit_threshold ?? $course->min_credit_threshold ?? null;

                    $category = optional($course->departmentCourseTypes)
                        ->filter(function ($typeAssignment) use ($curriculum) {
                            return $typeAssignment->curriculum_id === $curriculum->id;
                        })
                        ->map(function ($typeAssignment) {
                            return optional($typeAssignment->courseType)->name;
                        })
                        ->filter()
                        ->first() ?? 'Unassigned';

                    return [
                        'id' => $curriculumCourse->id,
                        'curriculumId' => $curriculumCourse->curriculum_id,
                        'courseId' => $curriculumCourse->course_id,
                        'isRequired' => $curriculumCourse->is_required,
                        'semester' => $curriculumCourse->semester,
                        'year' => $curriculumCourse->year,
                        'position' => $curriculumCourse->position,
                        'requiresPermission' => $requiresPermission,
                        'summerOnly' => $summerOnly,
                        'requiresSeniorStanding' => $requiresSeniorStanding,
                        'minCreditThreshold' => $minCreditThreshold,
                        'course' => [
                            'id' => $course->id,
                            'code' => $course->code,
                            'name' => $course->name,
                            'credits' => $course->credits,
                            'creditHours' => $course->credit_hours,
                            'description' => $course->description,
                            'category' => $category,
                            'prerequisites' => $prerequisites,
                            'corequisites' => $corequisites,
                        ],
                    ];
                });

                return [
                    'id' => $curriculum->id,
                    'name' => $curriculum->name,
                    'year' => $curriculum->year,
                    'version' => $curriculum->version,
                    'description' => $curriculum->description,
                    'totalCreditsRequired' => $curriculum->total_credits_required,
                    'startId' => $curriculum->start_id,
                    'endId' => $curriculum->end_id,
                    'department' => $curriculum->department,
                    'faculty' => $curriculum->faculty,
                    'curriculumConstraints' => $curriculum->curriculumConstraints,
                    'electiveRules' => $curriculum->electiveRules,
                    'curriculumCourses' => $courses,
                ];
            });

            return response()->json(['curricula' => $sanitizedCurricula]);
        } catch (\Exception $error) {
            return response()->json(
                [
                    'error' => [
                        'code' => 'INTERNAL_ERROR',
                        'message' => 'Failed to fetch curricula',
                        'details' => $error->getMessage()
                    ]
                ],
                500
            );
        }
    }

    // GET /api/public-curricula/{id}
    public function show($id)
    {
        try {
            $curriculum = Curriculum::with([
                'department',
                'faculty',
                'curriculumCourses.course.departmentCourseTypes.courseType',
                'curriculumCourses.course.prerequisites.prerequisite',
                'curriculumCourses.course.corequisites.corequisite',
                'curriculumCourses.curriculumPrerequisites.prerequisiteCourse.course',
                'curriculumCourses.curriculumCorequisites.corequisiteCourse.course',
                'curriculumConstraints',
                'electiveRules'
            ])->where('is_active', true)->findOrFail($id);

            $courses = $curriculum->curriculumCourses->map(function ($curriculumCourse) use ($curriculum) {
                $course = $curriculumCourse->course;

                $curriculumPrereqs = optional($curriculumCourse->curriculumPrerequisites)->map(function ($prereq) {
                    return optional(optional($prereq->prerequisiteCourse)->course)->code;
                })->filter()->values()->all();

                $curriculumCoreqs = optional($curriculumCourse->curriculumCorequisites)->map(function ($coreq) {
                    return optional(optional($coreq->corequisiteCourse)->course)->code;
                })->filter()->values()->all();

                $prerequisites = !empty($curriculumPrereqs)
                    ? $curriculumPrereqs
                    : (optional($course->prerequisites)->map(function ($prereq) {
                        return optional($prereq->prerequisite)->code;
                    })->filter()->values()->all() ?? []);

                $corequisites = !empty($curriculumCoreqs)
                    ? $curriculumCoreqs
                    : (optional($course->corequisites)->map(function ($coreq) {
                        return optional($coreq->corequisite)->code;
                    })->filter()->values()->all() ?? []);

                $requiresPermission = $curriculumCourse->override_requires_permission ?? $course->requires_permission ?? false;
                $summerOnly = $curriculumCourse->override_summer_only ?? $course->summer_only ?? false;
                $requiresSeniorStanding = $curriculumCourse->override_requires_senior_standing ?? $course->requires_senior_standing ?? false;
                $minCreditThreshold = $curriculumCourse->override_min_credit_threshold ?? $course->min_credit_threshold ?? null;

                // Find course type assignment for this curriculum
                $typeAssignment = optional($course->departmentCourseTypes)
                    ->filter(function ($dct) use ($curriculum) {
                        return $dct->curriculum_id === $curriculum->id;
                    })
                    ->first();

                $category = optional($typeAssignment?->courseType)->name ?? 'Unassigned';
                
                $courseType = $typeAssignment?->courseType ? [
                    'id' => $typeAssignment->courseType->id,
                    'name' => $typeAssignment->courseType->name,
                    'color' => $typeAssignment->courseType->color,
                    'parentId' => $typeAssignment->courseType->parent_course_type_id,
                ] : null;

                return [
                    'id' => $curriculumCourse->id,
                    'curriculumId' => $curriculumCourse->curriculum_id,
                    'courseId' => $curriculumCourse->course_id,
                    'isRequired' => $curriculumCourse->is_required,
                    'semester' => $curriculumCourse->semester,
                    'year' => $curriculumCourse->year,
                    'position' => $curriculumCourse->position,
                    'requiresPermission' => $requiresPermission,
                    'summerOnly' => $summerOnly,
                    'requiresSeniorStanding' => $requiresSeniorStanding,
                    'minCreditThreshold' => $minCreditThreshold,
                    'course' => [
                        'id' => $course->id,
                        'code' => $course->code,
                        'name' => $course->name,
                        'credits' => $course->credits,
                        'creditHours' => $course->credit_hours,
                        'description' => $course->description,
                        'category' => $category,
                        'courseType' => $courseType,
                        'prerequisites' => $prerequisites,
                        'corequisites' => $corequisites,
                    ],
                ];
            });

            $sanitizedCurriculum = [
                'id' => $curriculum->id,
                'name' => $curriculum->name,
                'year' => $curriculum->year,
                'version' => $curriculum->version,
                'description' => $curriculum->description,
                'totalCreditsRequired' => $curriculum->total_credits_required,
                'startId' => $curriculum->start_id,
                'endId' => $curriculum->end_id,
                'department' => $curriculum->department,
                'faculty' => $curriculum->faculty,
                'curriculumConstraints' => $curriculum->curriculumConstraints,
                'electiveRules' => $curriculum->electiveRules,
                'curriculumCourses' => $courses,
            ];

            return response()->json(['curriculum' => $sanitizedCurriculum]);
        } catch (\Exception $error) {
            return response()->json(
                [
                    'error' => [
                        'code' => 'INTERNAL_ERROR',
                        'message' => 'Failed to fetch curriculum',
                        'details' => $error->getMessage()
                    ]
                ],
                500
            );
        }
    }

    // GET /api/public-curricula/{id}/blacklists
    public function blacklists($id)
    {
        try {
            $curriculum = Curriculum::with([
                'curriculumBlacklists.blacklist.blacklistCourses.course'
            ])->findOrFail($id);

            $blacklists = $curriculum->curriculumBlacklists->map(function ($curriculumBlacklist) {
                $blacklist = $curriculumBlacklist->blacklist;
                if (!$blacklist) {
                    return null;
                }
                
                return [
                    'id' => $blacklist->id,
                    'name' => $blacklist->name,
                    'description' => $blacklist->description,
                    'createdAt' => $curriculumBlacklist->created_at,
                    'courses' => $blacklist->blacklistCourses->map(function ($blacklistCourse) {
                        return [
                            'course' => [
                                'code' => $blacklistCourse->course->code,
                                'name' => $blacklistCourse->course->name,
                            ]
                        ];
                    })
                ];
            })->filter(); // Remove null values

            return response()->json(['blacklists' => $blacklists->values()]);
        } catch (\Exception $error) {
            return response()->json(
                [
                    'error' => [
                        'code' => 'INTERNAL_ERROR',
                        'message' => 'Failed to fetch blacklists',
                        'details' => $error->getMessage()
                    ]
                ],
                500
            );
        }
    }
}