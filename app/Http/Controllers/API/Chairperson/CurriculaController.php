<?php
namespace App\Http\Controllers\API\Chairperson;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\{
    Curriculum,
    CurriculumCourse,
    CurriculumConcentration,
    CurriculumBlacklist,
    CurriculumConstraint,
    CurriculumElectiveRule,
    Department,
    Concentration,
    Blacklist,
    Constraint,
    ElectiveRule,
    Course
};

class CurriculaController extends Controller
{
    // GET /api/curricula
    public function index(Request $request)
    {
        $departmentId = $request->query('departmentId');
        $perPage = $request->input('limit', $request->input('perPage', 10)); // Accept both limit and perPage
        $page = $request->input('page', 1);
        $search = $request->input('search');

        $query = Curriculum::with([
            'department:id,name,code',
            'faculty:id,name,code'
        ])->withCount([
            'curriculumCourses',
            'curriculumConstraints',
            'electiveRules'
        ]);

        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%$search%")
                  ->orWhere('year', 'ILIKE', "%$search%")
                  ->orWhere('version', 'ILIKE', "%$search%")
                  ->orWhere('id', $search);
            });
        }

        $paginatedResults = $query->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);

        // Transform the results to match frontend expectations
        $curricula = $paginatedResults->items();
        $transformedCurricula = array_map(function ($curriculum) {
            $item = $curriculum->toArray();
            // Transform snake_case counts to camelCase _count object
            $item['_count'] = [
                'curriculumCourses' => $curriculum->curriculum_courses_count ?? 0,
                'curriculumConstraints' => $curriculum->curriculum_constraints_count ?? 0,
                'electiveRules' => $curriculum->elective_rules_count ?? 0,
            ];
            // Remove snake_case count attributes
            unset($item['curriculum_courses_count']);
            unset($item['curriculum_constraints_count']);
            unset($item['elective_rules_count']);
            return $item;
        }, $curricula);

        return response()->json([
            'curricula' => $transformedCurricula,
            'pagination' => [
                'page' => $paginatedResults->currentPage(),
                'limit' => $paginatedResults->perPage(),
                'total' => $paginatedResults->total(),
                'totalPages' => $paginatedResults->lastPage(),
            ]
        ]);
    }

    // POST /api/curricula
    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => 'Chairperson access required'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string',
            'year' => 'required|string',
            'version' => 'nullable|string',
            'description' => 'nullable|string',
            'departmentId' => 'required|exists:departments,id',
            'facultyId' => 'nullable|exists:faculties,id',
            'startId' => 'nullable|string',
            'endId' => 'nullable|string',
            'totalCreditsRequired' => 'nullable|integer',
            'courses' => 'nullable|array',
            'courses.*.code' => 'required|string',
            'courses.*.name' => 'required|string',
            'courses.*.credits' => 'required|integer',
            'courses.*.creditHours' => 'nullable|string',
            'courses.*.description' => 'nullable|string',
            'courses.*.isRequired' => 'nullable|boolean',
            'courses.*.position' => 'nullable|integer',
            'courses.*.requiresPermission' => 'nullable|boolean',
            'courses.*.summerOnly' => 'nullable|boolean',
            'courses.*.requiresSeniorStanding' => 'nullable|boolean',
        ]);

        // Map camelCase to snake_case for database columns
        $curriculum = Curriculum::create([
            'name' => $validated['name'],
            'year' => $validated['year'],
            'version' => $validated['version'] ?? '1.0',
            'description' => $validated['description'] ?? null,
            'department_id' => $validated['departmentId'],
            'faculty_id' => $validated['facultyId'] ?? null,
            'start_id' => $validated['startId'] ?? null,
            'end_id' => $validated['endId'] ?? null,
            'total_credits_required' => $validated['totalCreditsRequired'] ?? 0,
            'is_active' => true,
            'created_by_id' => $user->id,
        ]);

        // Create or get courses and link them to curriculum
        if (!empty($validated['courses'])) {
            foreach ($validated['courses'] as $courseData) {
                // Find or create the course
                $course = Course::firstOrCreate(
                    ['code' => $courseData['code']],
                    [
                        'name' => $courseData['name'],
                        'credits' => $courseData['credits'],
                        'credit_hours' => $courseData['creditHours'] ?? $courseData['credits'] . '-0-' . ($courseData['credits'] * 2),
                        'description' => $courseData['description'] ?? '',
                        'requires_permission' => $courseData['requiresPermission'] ?? false,
                        'summer_only' => $courseData['summerOnly'] ?? false,
                        'requires_senior_standing' => $courseData['requiresSeniorStanding'] ?? false,
                        'is_active' => true,
                    ]
                );

                // Link course to curriculum
                CurriculumCourse::create([
                    'curriculum_id' => $curriculum->id,
                    'course_id' => $course->id,
                    'is_required' => $courseData['isRequired'] ?? true,
                    'position' => $courseData['position'] ?? 0,
                ]);
            }
        }

        // Load the curriculum with courses for return
        $curriculum->load('curriculumCourses.course');

        return response()->json(['curriculum' => $curriculum], 201);
    }

    // GET /api/curricula/{id}
    public function show($id)
    {
        $curriculum = Curriculum::with([
            'department:id,name,code',
            'faculty:id,name,code',
            'curriculumCourses.course.departmentCourseTypes.courseType',
            'curriculumCourses.curriculumPrerequisites.prerequisiteCourse.course:id,code,name',
            'curriculumCourses.curriculumCorequisites.corequisiteCourse.course:id,code,name',
            'curriculumConcentrations',
            'curriculumBlacklists',
            'curriculumConstraints',
            'electiveRules'
        ])->find($id);

        if (!$curriculum) {
            return response()->json(['error' => 'Curriculum not found'], 404);
        }

        return response()->json(['curriculum' => $curriculum]);
    }

    // PUT /api/curricula/{id}
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => 'Chairperson access required'], 403);
        }

        $curriculum = Curriculum::find($id);
        if (!$curriculum) {
            return response()->json(['error' => 'Curriculum not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string',
            'departmentId' => 'sometimes|exists:departments,id',
            'description' => 'nullable|string',
        ]);

        $curriculum->update($validated);

        return response()->json(['curriculum' => $curriculum]);
    }

    // DELETE /api/curricula/{id}
    public function destroy($id)
    {
        try {
            $user = Auth::user();
            if (!$user || $user->role !== 'CHAIRPERSON') {
                return response()->json([
                    'error' => [
                        'message' => 'Chairperson access required'
                    ]
                ], 403);
            }

            $curriculum = Curriculum::find($id);
            if (!$curriculum) {
                return response()->json([
                    'error' => [
                        'message' => 'Curriculum not found'
                    ]
                ], 404);
            }

            // Laravel will cascade delete related records based on foreign key constraints
            // This includes: curriculum_courses, curriculum_constraints, elective_rules, 
            // curriculum_concentrations, curriculum_blacklists, etc.
            $curriculum->delete();

            return response()->json([
                'message' => 'Curriculum deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => [
                    'message' => 'Failed to delete curriculum',
                    'details' => $e->getMessage()
                ]
            ], 500);
        }
    }

    // GET /api/curricula/{id}/elective-rules
    public function electiveRules($id)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => ['code' => 'FORBIDDEN', 'message' => 'Chairperson access required']], 403);
        }

        $curriculum = Curriculum::with([
            'electiveRules',
            'curriculumCourses.course.departmentCourseTypes.courseType'
        ])->find($id);

        if (!$curriculum) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Curriculum not found']], 404);
        }

        // Get elective rules
        $electiveRules = $curriculum->electiveRules;

        // Build course breakdown by category
        $coursesByCategory = [];
        foreach ($curriculum->curriculumCourses as $cc) {
            $courseTypes = $cc->course->departmentCourseTypes;
            $categoryName = 'Uncategorized';
            
            if ($courseTypes->isNotEmpty()) {
                $categoryName = $courseTypes->first()->courseType->name ?? 'Uncategorized';
            }

            if (!isset($coursesByCategory[$categoryName])) {
                $coursesByCategory[$categoryName] = [];
            }

            $coursesByCategory[$categoryName][] = [
                'id' => $cc->course->id,
                'code' => $cc->course->code,
                'name' => $cc->course->name,
                'credits' => $cc->course->credits,
            ];
        }

        return response()->json([
            'electiveRules' => $electiveRules,
            'coursesByCategory' => $coursesByCategory,
        ]);
    }

    // GET /api/curricula/{id}/concentrations
    public function concentrations($id)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => ['code' => 'FORBIDDEN', 'message' => 'Chairperson access required']], 403);
        }

        $curriculum = Curriculum::with([
            'curriculumConcentrations.concentration.courses.course'
        ])->find($id);

        if (!$curriculum) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Curriculum not found']], 404);
        }

        $concentrations = $curriculum->curriculumConcentrations->map(function ($cc) {
            return [
                'id' => $cc->concentration_id,
                'requiredCourses' => $cc->required_courses,
                'concentration' => [
                    'id' => $cc->concentration->id,
                    'name' => $cc->concentration->name,
                    'description' => $cc->concentration->description,
                    'courses' => $cc->concentration->courses->map(function ($concentrationCourse) {
                        return [
                            'id' => $concentrationCourse->course->id,
                            'code' => $concentrationCourse->course->code,
                            'name' => $concentrationCourse->course->name,
                            'credits' => $concentrationCourse->course->credits,
                            'description' => $concentrationCourse->course->description,
                        ];
                    }),
                ],
            ];
        });

        return response()->json(['concentrations' => $concentrations]);
    }

    // GET /api/curricula/{id}/blacklists
    public function blacklists($id)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => ['code' => 'FORBIDDEN', 'message' => 'Chairperson access required']], 403);
        }

        $curriculum = Curriculum::with([
            'curriculumBlacklists.blacklist.courses.course',
            'department'
        ])->find($id);

        if (!$curriculum) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Curriculum not found']], 404);
        }

        // Get all available blacklists for this department
        $availableBlacklists = Blacklist::where('department_id', $curriculum->department_id)
            ->with(['courses.course'])
            ->withCount('courses')
            ->get()
            ->map(function ($blacklist) {
                return [
                    'id' => $blacklist->id,
                    'name' => $blacklist->name,
                    'description' => $blacklist->description,
                    'courses' => $blacklist->courses->map(function ($bc) {
                        return [
                            'id' => $bc->course->id,
                            'code' => $bc->course->code,
                            'name' => $bc->course->name,
                            'credits' => $bc->course->credits,
                            'description' => $bc->course->description,
                        ];
                    }),
                    'courseCount' => $blacklist->courses_count,
                    'createdAt' => $blacklist->created_at,
                ];
            });

        // Get curriculum's assigned blacklists
        $curriculumBlacklists = $curriculum->curriculumBlacklists->map(function ($cb) {
            return [
                'id' => $cb->blacklist->id,
                'name' => $cb->blacklist->name,
                'description' => $cb->blacklist->description,
                'courses' => $cb->blacklist->courses->map(function ($bc) {
                    return [
                        'id' => $bc->course->id,
                        'code' => $bc->course->code,
                        'name' => $bc->course->name,
                        'credits' => $bc->course->credits,
                        'description' => $bc->course->description,
                    ];
                }),
                'createdAt' => $cb->blacklist->created_at,
            ];
        });

        return response()->json([
            'curriculumBlacklists' => $curriculumBlacklists,
            'availableBlacklists' => $availableBlacklists,
        ]);
    }

    // GET /api/curricula/{id}/elective-rules/settings
    public function electiveRuleSettings($id)
    {
        $curriculum = Curriculum::find($id);
        if (!$curriculum) {
            return response()->json(['error' => 'Curriculum not found'], 404);
        }

        // Example: return settings field or structure
        return response()->json([
            'settings' => $curriculum->elective_rule_settings ?? []
        ]);
    }
}