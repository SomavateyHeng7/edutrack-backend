<?php
namespace App\Http\Controllers\API\Chairperson;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\{
    Curriculum,
    CurriculumCourse,
    Course,
    CurriculumCoursePrerequisite,
    CurriculumCourseCorequisite,
    CurriculumConcentration,
    CurriculumBlacklist,
    CurriculumConstraint,
    ElectiveRule,
    AuditLog,
    User
};

class CurriculumController extends Controller
{
    // GET /api/curriculum
    public function index(Request $request)
    {
        $curricula = Curriculum::with(['department:id,name,code'])->orderBy('name')->get();
        return response()->json(['curricula' => $curricula]);
    }

    // POST /api/curriculum
    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => 'Chairperson access required'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string',
            'departmentId' => 'required|exists:departments,id',
            'description' => 'nullable|string',
        ]);

        $curriculum = Curriculum::create($validated);

        return response()->json(['curriculum' => $curriculum], 201);
    }

    // GET /api/curriculum/{id}
    public function show($id)
    {
        $curriculum = Curriculum::with([
            'department:id,name,code',
            'curriculumCourses.course.departmentCourseTypes.courseType',
            'curriculumConcentrations',
            'curriculumBlacklists',
            'curriculumConstraints',
            'electiveRules'
        ])->find($id);

        if (!$curriculum) {
            return response()->json(['error' => 'Curriculum not found'], 404);
        }

        // Transform to include course_type on each course (scoped to this curriculum)
        $curriculum->curriculumCourses->each(function ($cc) use ($curriculum) {
            $dct = $cc->course->departmentCourseTypes
                ->where('curriculum_id', $curriculum->id)
                ->first();
            
            // Use setAttribute to ensure it serializes in JSON response
            $courseTypeData = $dct?->courseType ? [
                'id' => $dct->courseType->id,
                'name' => $dct->courseType->name,
                'color' => $dct->courseType->color,
                'parentId' => $dct->courseType->parent_course_type_id,
            ] : null;
            
            $cc->course->setAttribute('course_type', $courseTypeData);
            
            // Clean up the loaded relationship to avoid duplication in response
            unset($cc->course->departmentCourseTypes);
        });

        return response()->json(['curriculum' => $curriculum]);
    }

    // PUT /api/curriculum/{id}
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

    // DELETE /api/curriculum/{id}
    public function destroy($id)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => 'Chairperson access required'], 403);
        }

        $curriculum = Curriculum::find($id);
        if (!$curriculum) {
            return response()->json(['error' => 'Curriculum not found'], 404);
        }

        $curriculum->delete();

        return response()->json(['message' => 'Curriculum deleted']);
    }

    // GET /api/curriculum/{id}/courses
    public function courses($id)
    {
        $curriculum = Curriculum::with('curriculumCourses.course')->find($id);
        if (!$curriculum) {
            return response()->json(['error' => 'Curriculum not found'], 404);
        }

        $courses = $curriculum->curriculumCourses->map(function ($cc) {
            return [
                'code' => $cc->course->code,
                'name' => $cc->course->name,
                'credits' => $cc->course->credits,
                'description' => $cc->course->description,
            ];
        });

        return response()->json(['courses' => $courses]);
    }

    // POST /api/curriculum/{id}/courses
    public function addCourse(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => 'Chairperson access required'], 403);
        }

        $validated = $request->validate([
            'courseId' => 'required|exists:courses,id',
        ]);

        $curriculumCourse = CurriculumCourse::create([
            'curriculum_id' => $id,
            'course_id' => $validated['courseId'],
        ]);

        return response()->json(['curriculumCourse' => $curriculumCourse], 201);
    }

    // DELETE /api/curriculum/{id}/courses/{courseId}
    public function removeCourse($id, $courseId)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => 'Chairperson access required'], 403);
        }

        $curriculumCourse = CurriculumCourse::where('curriculum_id', $id)
            ->where('course_id', $courseId)
            ->first();

        if (!$curriculumCourse) {
            return response()->json(['error' => 'Course not found in curriculum'], 404);
        }

        $curriculumCourse->delete();
        return response()->json(['message' => 'Course removed from curriculum']);
    }

    // GET /api/curriculum/{id}/concentrations
    public function concentrations($id)
    {
        $curriculum = Curriculum::find($id);
        if (!$curriculum) {
            return response()->json(['error' => 'Curriculum not found'], 404);
        }

        $concentrations = $curriculum->curriculumConcentrations()->with('concentration.concentrationCourses.course')->get();

        return response()->json(['concentrations' => $concentrations]);
    }

    // GET /api/curriculum/{id}/blacklists
    public function blacklists($id)
    {
        $curriculum = Curriculum::find($id);
        if (!$curriculum) {
            return response()->json(['error' => 'Curriculum not found'], 404);
        }

        $blacklists = $curriculum->curriculumBlacklists()->with('blacklist.blacklistCourses.course')->get();

        return response()->json(['blacklists' => $blacklists]);
    }

    // GET /api/curriculum/{id}/constraints
    public function constraints($id)
    {
        $curriculum = Curriculum::find($id);
        if (!$curriculum) {
            return response()->json(['error' => 'Curriculum not found'], 404);
        }

        $constraints = $curriculum->curriculumConstraints()->get();

        return response()->json(['constraints' => $constraints]);
    }

    // GET /api/curriculum/{id}/elective-rules
    public function electiveRules($id)
    {
        $curriculum = Curriculum::find($id);
        if (!$curriculum) {
            return response()->json(['error' => 'Curriculum not found'], 404);
        }

        $electiveRules = $curriculum->electiveRules()->get();

        return response()->json(['electiveRules' => $electiveRules]);
    }

    // GET /api/curriculum/{id}/courses/{courseId}/prerequisites
    public function prerequisites($id, $courseId)
    {
        $prerequisites = CurriculumCoursePrerequisite::where('curriculum_id', $id)
            ->where('course_id', $courseId)
            ->with('prerequisiteCourse')
            ->get();

        return response()->json(['prerequisites' => $prerequisites]);
    }

    // POST /api/curriculum/{id}/courses/{courseId}/prerequisites
    public function addPrerequisite(Request $request, $id, $courseId)
    {
        $validated = $request->validate([
            'prerequisiteCourseId' => 'required|exists:courses,id',
        ]);

        $prerequisite = CurriculumCoursePrerequisite::create([
            'curriculum_id' => $id,
            'course_id' => $courseId,
            'prerequisite_course_id' => $validated['prerequisiteCourseId'],
        ]);

        return response()->json(['prerequisite' => $prerequisite], 201);
    }

    // DELETE /api/curriculum/{id}/courses/{courseId}/prerequisites/{prerequisiteId}
    public function removePrerequisite($id, $courseId, $prerequisiteId)
    {
        $prerequisite = CurriculumCoursePrerequisite::where('curriculum_id', $id)
            ->where('course_id', $courseId)
            ->where('id', $prerequisiteId)
            ->first();

        if (!$prerequisite) {
            return response()->json(['error' => 'Prerequisite not found'], 404);
        }

        $prerequisite->delete();
        return response()->json(['message' => 'Prerequisite removed']);
    }

    // GET /api/curriculum/{id}/courses/{courseId}/corequisites
    public function corequisites($id, $courseId)
    {
        $corequisites = CurriculumCourseCorequisite::where('curriculum_id', $id)
            ->where('course_id', $courseId)
            ->with('corequisiteCourse')
            ->get();

        return response()->json(['corequisites' => $corequisites]);
    }

    // POST /api/curriculum/{id}/courses/{courseId}/corequisites
    public function addCorequisite(Request $request, $id, $courseId)
    {
        $validated = $request->validate([
            'corequisiteCourseId' => 'required|exists:courses,id',
        ]);

        $corequisite = CurriculumCourseCorequisite::create([
            'curriculum_id' => $id,
            'course_id' => $courseId,
            'corequisite_course_id' => $validated['corequisiteCourseId'],
        ]);

        return response()->json(['corequisite' => $corequisite], 201);
    }

    // DELETE /api/curriculum/{id}/courses/{courseId}/corequisites/{corequisiteId}
    public function removeCorequisite($id, $courseId, $corequisiteId)
    {
        $corequisite = CurriculumCourseCorequisite::where('curriculum_id', $id)
            ->where('course_id', $courseId)
            ->where('id', $corequisiteId)
            ->first();

        if (!$corequisite) {
            return response()->json(['error' => 'Corequisite not found'], 404);
        }

        $corequisite->delete();
        return response()->json(['message' => 'Corequisite removed']);
    }

    // GET /api/curriculum/bscs2022
    public function bscs2022()
    {
        $curriculum = Curriculum::where('code', 'BSCS2022')
            ->with(['curriculumCourses.course', 'department:id,name,code'])
            ->first();

        if (!$curriculum) {
            return response()->json(['error' => 'BSCS2022 curriculum not found'], 404);
        }

        return response()->json(['curriculum' => $curriculum]);
    }

    // GET /api/curriculum/template
    public function template()
    {
        $template = [
            'name' => 'Curriculum Template',
            'fields' => [
                'name', 'departmentId', 'description', 'courses', 'concentrations'
            ]
        ];

        return response()->json(['template' => $template]);
    }

    // POST /api/curriculum/upload
    public function upload(Request $request)
    {
        try {
            Log::info('📁 Curriculum upload endpoint called');
            
            $user = Auth::user();
            Log::info('🔐 Session user:', ['id' => $user?->id, 'role' => $user?->role]);
            
            if (!$user || $user->role !== 'CHAIRPERSON') {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            // Validate request
            $request->validate([
                'file' => 'required|file|mimes:csv,txt',
                'curriculumId' => 'required|string|exists:curricula,id'
            ]);

            $file = $request->file('file');
            $curriculumId = $request->input('curriculumId');

            Log::info('📋 Form data', ['file' => $file->getClientOriginalName(), 'curriculumId' => $curriculumId]);

            // Get user's department and faculty for access control
            $userWithRelations = User::with([
                'department.faculty.departments'
            ])->find($user->id);

            if (!$userWithRelations->department || !$userWithRelations->department->faculty) {
                return response()->json(['error' => 'User department not found'], 403);
            }

            // Get accessible department IDs (all departments in user's faculty)
            $accessibleDepartmentIds = $userWithRelations->department->faculty->departments->pluck('id')->toArray();

            // Check if curriculum exists and user has department access
            $curriculum = Curriculum::whereIn('department_id', $accessibleDepartmentIds)
                ->find($curriculumId);

            Log::info('🎓 Curriculum found', ['found' => !!$curriculum, 'id' => $curriculumId]);

            if (!$curriculum) {
                return response()->json(['error' => 'Curriculum not found'], 404);
            }

            // Read and parse CSV file
            $fileContent = file_get_contents($file->getRealPath());
            $rows = array_map('str_getcsv', explode("\n", $fileContent));
            $header = array_shift($rows);
            
            // Remove empty rows
            $rows = array_filter($rows, function($row) {
                return !empty(array_filter($row));
            });

            $records = [];
            foreach ($rows as $row) {
                if (count($row) === count($header)) {
                    $records[] = array_combine($header, $row);
                }
            }

            Log::info('📊 CSV records parsed', ['count' => count($records)]);
            if (!empty($records)) {
                Log::info('📝 First record', $records[0]);
            }

            // Validate CSV structure
            $requiredColumns = ['code', 'name', 'credits', 'category'];
            if (empty($records) || !empty(array_diff($requiredColumns, array_keys($records[0])))) {
                return response()->json([
                    'error' => 'Invalid CSV format. Required columns: code, name, credits, category'
                ], 400);
            }

            // Process records and create/update courses
            $courses = array_map(function($record) {
                return [
                    'code' => $record['code'],
                    'name' => $record['name'],
                    'credits' => (int)$record['credits'],
                    'category' => $record['category'],
                    'credit_hours' => $record['creditHours'] ?? $record['credit_hours'] ?? ($record['credits'] . '-0-' . ($record['credits'] * 2)),
                    'description' => $record['description'] ?? '',
                ];
            }, $records);

            // Use transaction to ensure data consistency
            $result = DB::transaction(function () use ($curriculumId, $courses, $user) {
                Log::info('🔄 Starting database transaction', ['courseCount' => count($courses)]);
                
                // Remove existing course relationships for this curriculum
                CurriculumCourse::where('curriculum_id', $curriculumId)->delete();
                Log::info('🗑️ Removed existing curriculum-course relationships');

                // Process each course
                $coursesProcessed = 0;
                foreach ($courses as $courseData) {
                    Log::info('📚 Processing course', [
                        'progress' => ($coursesProcessed + 1) . '/' . count($courses),
                        'code' => $courseData['code']
                    ]);
                    
                    // Check if course exists globally
                    $course = Course::where('code', $courseData['code'])->first();

                    if ($course) {
                        Log::info('♻️ Updating existing course', ['code' => $courseData['code']]);
                        // Course exists - update it
                        $course->update([
                            'name' => $courseData['name'],
                            'credits' => $courseData['credits'],
                            'credit_hours' => $courseData['credit_hours'],
                            'description' => $courseData['description'],
                        ]);
                    } else {
                        Log::info('✨ Creating new course', ['code' => $courseData['code']]);
                        // Create new course in global pool
                        $course = Course::create([
                            'code' => $courseData['code'],
                            'name' => $courseData['name'],
                            'credits' => $courseData['credits'],
                            'credit_hours' => $courseData['credit_hours'],
                            'description' => $courseData['description'],
                            'requires_permission' => false,
                            'summer_only' => false,
                            'requires_senior_standing' => false,
                            'is_active' => true,
                        ]);
                    }

                    Log::info('🔗 Creating curriculum-course relationship', ['code' => $course->code]);
                    // Create curriculum-course relationship
                    CurriculumCourse::create([
                        'curriculum_id' => $curriculumId,
                        'course_id' => $course->id,
                        'is_required' => true,
                        'position' => $coursesProcessed,
                    ]);

                    $coursesProcessed++;
                }

                // Create audit log
                AuditLog::create([
                    'user_id' => $user->id,
                    'entity_type' => 'Curriculum',
                    'entity_id' => $curriculumId,
                    'action' => 'IMPORT',
                    'description' => "Uploaded {$coursesProcessed} courses via CSV",
                    'curriculum_id' => $curriculumId,
                    'changes' => [
                        'coursesUploaded' => $coursesProcessed,
                        'courseList' => array_column($courses, 'code'),
                    ],
                ]);

                Log::info('✅ Transaction completed successfully', ['coursesProcessed' => $coursesProcessed]);
                return ['coursesProcessed' => $coursesProcessed];
            });

            return response()->json([
                'message' => 'Curriculum updated successfully',
                'coursesProcessed' => $result['coursesProcessed'],
            ]);
        } catch (\Exception $e) {
            Log::error('❌ Error uploading curriculum', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'error' => 'Error uploading curriculum: ' . $e->getMessage()
            ], 500);
        }
    }
}