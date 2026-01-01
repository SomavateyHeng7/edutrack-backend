<?php
namespace App\Http\Controllers\API\Chairperson;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Concentration;
use App\Models\ConcentrationCourse;
use App\Models\Course;
use App\Models\AuditLog;

class ConcentrationController extends Controller
{
    // GET /api/concentrations
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user || !in_array($user->role, ['CHAIRPERSON', 'SUPER_ADMIN'])) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $faculty = $user->faculty?->load('departments');
        if (!$faculty || $faculty->departments->isEmpty()) {
            return response()->json(['error' => 'User faculty or department not found'], 404);
        }

        $departmentIds = $faculty->departments->pluck('id')->toArray();

        $concentrations = Concentration::whereIn('department_id', $departmentIds)
            ->with([
                'department:id,name',
                'createdBy:id,name',
                'courses.course:id,code,name,credits,description',
                'curriculumConcentrations',
            ])
            ->orderBy('name')
            ->get();
        
        \Log::info('DEBUG Concentrations query result:', [
            'count' => $concentrations->count(),
            'first_concentration' => $concentrations->first() ? [
                'id' => $concentrations->first()->id,
                'name' => $concentrations->first()->name,
                'courses_count' => $concentrations->first()->courses->count(),
                'courses_relation_loaded' => $concentrations->first()->relationLoaded('courses'),
                'first_course' => $concentrations->first()->courses->first()
            ] : null
        ]);
        
        $formatted = $concentrations->map(function ($concentration) {
            $coursesData = $concentration->courses->map(fn ($cc) => $cc->course)->values();
            
            \Log::info('DEBUG Formatting concentration:', [
                'concentration_id' => $concentration->id,
                'concentration_name' => $concentration->name,
                'courses_relation_count' => $concentration->courses->count(),
                'formatted_courses_count' => $coursesData->count(),
                'courses_data' => $coursesData->toArray()
            ]);
            
            return [
                'id' => $concentration->id,
                'name' => $concentration->name,
                'description' => $concentration->description,
                'departmentId' => $concentration->department_id,
                'department' => $concentration->department,
                'createdBy' => $concentration->createdBy,
                'courses' => $coursesData,
                'courseCount' => $concentration->courses->count(),
                'usageCount' => $concentration->curriculumConcentrations->count(),
                'createdAt' => $concentration->created_at,
                'updatedAt' => $concentration->updated_at,
            ];
        });
        
        return response()->json(['concentrations' => $formatted]);
    }

    // POST /api/concentrations
    public function store(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user || $user->role !== 'CHAIRPERSON') {
                return response()->json(['error' => 'Forbidden'], 403);
            }

            // Get user's faculty and departments
            $faculty = $user->faculty?->load('departments');
            if (!$faculty || $faculty->departments->isEmpty()) {
                return response()->json(['error' => 'User faculty or department not found'], 404);
            }

            $departmentIds = $faculty->departments->pluck('id')->toArray();

            $validated = $request->validate([
                'name' => 'required|string',
                'departmentId' => 'nullable|exists:departments,id',
                'description' => 'nullable|string',
                'courseIds' => 'nullable|array',
            'courseIds.*' => 'string',
        ]);

        // Auto-assign department if not provided
        $departmentId = $validated['departmentId'] ?? $departmentIds[0];

            $exists = Concentration::where('name', $validated['name'])
                ->where('department_id', $departmentId)
                ->first();

            if ($exists) {
                return response()->json(['error' => 'Concentration with this name already exists'], 409);
            }

            $concentration = DB::transaction(function () use ($validated, $user, $departmentId) {
                $concentration = Concentration::create([
                    'name' => $validated['name'],
                    'description' => $validated['description'] ?? null,
                    'department_id' => $departmentId,
                    'created_by_id' => $user->id,
                ]);

                if (!empty($validated['courseIds'])) {
                    // Filter to only include course IDs that exist in the database
                    $existingCourseIds = Course::whereIn('id', $validated['courseIds'])->pluck('id')->toArray();
                    
                    \Log::info('DEBUG Creating ConcentrationCourse records:', [
                        'concentration_id' => $concentration->id,
                        'requested_courseIds' => $validated['courseIds'],
                        'existing_courseIds' => $existingCourseIds,
                        'faculty_id' => $user->faculty_id
                    ]);
                    
                    foreach ($existingCourseIds as $courseId) {
                        $cc = ConcentrationCourse::create([
                            'concentration_id' => $concentration->id,
                            'course_id' => $courseId,
                            'faculty_id' => $user->faculty_id,
                        ]);
                        
                        \Log::info('DEBUG Created ConcentrationCourse:', [
                            'id' => $cc->id,
                            'concentration_id' => $cc->concentration_id,
                            'course_id' => $cc->course_id
                        ]);
                    }
                }

                AuditLog::create([
                    'user_id' => $user->id,
                    'entity_type' => 'Concentration',
                    'entity_id' => $concentration->id,
                    'action' => 'CREATE',
                    'changes' => $validated,
                    'description' => 'Created concentration "' . $concentration->name . '"',
                ]);

                return $concentration;
            });

            // Load relationships and return the created concentration
            $concentration->load([
                'department:id,name',
                'createdBy:id,name',
                'courses.course:id,code,name,credits,description',
            ]);

            return response()->json(['concentration' => $concentration], 201);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            return response()->json(['error' => 'Concentration with this name already exists'], 409);
        } catch (\Exception $e) {
            \Log::error('Failed to create concentration: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to create concentration: ' . $e->getMessage()], 500);
        }
    }

    // GET /api/concentrations/{id}
    public function show($id)
    {
        $user = Auth::user();
        if (!$user || !in_array($user->role, ['CHAIRPERSON', 'SUPER_ADMIN'])) {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        $concentration = Concentration::with(['department', 'courses.course'])->find($id);
        if (!$concentration) {
            return response()->json(['error' => 'Not found'], 404);
        }
        return response()->json(['concentration' => $concentration]);
    }

    // PUT /api/concentrations/{id}
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        $concentration = Concentration::find($id);
        if (!$concentration) {
            return response()->json(['error' => 'Not found'], 404);
        }
        $concentration->update($request->only(['name', 'departmentId', 'description']));
        return response()->json(['concentration' => $concentration]);
    }

    // DELETE /api/concentrations/{id}
    public function destroy($id)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        $concentration = Concentration::find($id);
        if (!$concentration) {
            return response()->json(['error' => 'Not found'], 404);
        }
        $concentration->delete();
        return response()->json(['message' => 'Deleted']);
    }
}