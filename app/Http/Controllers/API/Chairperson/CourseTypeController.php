<?php

namespace App\Http\Controllers\API\Chairperson;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Models\{
    CourseType,
    DepartmentCourseType,
    Curriculum,
    Course,
    AuditLog
};

class CourseTypeController extends Controller
{
    /* =====================================================
     * GET /api/course-types
     * ===================================================== */
    public function index(Request $request)
    {
        $user = Auth::user();

        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => 'Chairperson access required'], 403);
        }

        $faculty = $user->faculty?->load('departments');
        if (!$faculty || $faculty->departments->isEmpty()) {
            return response()->json(['error' => 'User faculty or department not found'], 404);
        }

        $departmentId = $request->query('departmentId')
            ?? $faculty->departments->first()->id;

        if (!$faculty->departments->pluck('id')->contains($departmentId)) {
            return response()->json(['error' => 'Department not accessible'], 403);
        }

        $courseTypes = CourseType::where('department_id', $departmentId)
            ->orderBy('name')
            ->get();

        $seeded = $courseTypes->contains(fn ($t) => (bool) $t->seeded);

        return response()->json([
            'courseTypes' => $courseTypes->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'color' => $t->color,
                'departmentId' => $t->department_id,
                'seeded' => (bool) $t->seeded, // 👈 DB truth
                'createdAt' => $t->created_at,
                'updatedAt' => $t->updated_at,
            ])->values(),
            'seeded' => $seeded,   // 👈 DB-derived
            'total' => $courseTypes->count(),
        ]);
    }



    /* =====================================================
     * GET /api/course-types/{id}
     * ===================================================== */
    public function show($id)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => 'Chairperson access required'], 403);
        }

        $facultyDepartmentIds = $user->department->faculty->departments->pluck('id');

        $courseType = CourseType::where('id', $id)
            ->whereIn('department_id', $facultyDepartmentIds)
            ->first();

        if (!$courseType) {
            return response()->json(['error' => 'Course type not found'], 404);
        }

        return response()->json([
            'id' => $courseType->id,
            'name' => $courseType->name,
            'color' => $courseType->color,
            'departmentId' => $courseType->department_id,
            'createdAt' => $courseType->created_at,
            'updatedAt' => $courseType->updated_at,
        ]);
    }

    /* =====================================================
     * POST /api/course-types
     * ===================================================== */
    public function store(Request $request)
    {
        $user = Auth::user();

        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => 'Chairperson access required'], 403);
        }

        $data = $request->validate([
            'name' => 'required|string|max:50',
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'departmentId' => 'nullable|string',
        ]);

        $faculty = $user->faculty->load('departments');
        $departmentId = $data['departmentId']
            ?? $faculty->departments->first()->id;

        if (!$faculty->departments->pluck('id')->contains($departmentId)) {
            return response()->json(['error' => 'Department not accessible'], 403);
        }

        if (CourseType::where('name', $data['name'])->where('department_id', $departmentId)->exists()) {
            return response()->json(['error' => 'Course type name already exists'], 409);
        }

        $courseType = CourseType::create([
            'name' => $data['name'],
            'color' => $data['color'],
            'department_id' => $departmentId,
        ]);

        return response()->json($courseType, 201);
    }

    /* =====================================================
     * PUT /api/course-types/{id}
     * ===================================================== */
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $facultyDepartmentIds = $user->department->faculty->departments->pluck('id');

        $courseType = CourseType::where('id', $id)
            ->whereIn('department_id', $facultyDepartmentIds)
            ->first();

        if (!$courseType) {
            return response()->json(['error' => 'Course type not found'], 404);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:50',
                Rule::unique('course_types')->where(fn ($q) =>
                    $q->where('department_id', $courseType->department_id)
                )->ignore($courseType->id)
            ],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $courseType->update($data);

        return response()->json($courseType);
    }

    /* =====================================================
     * DELETE /api/course-types/{id}
     * ===================================================== */
    public function destroy($id)
    {
        $user = Auth::user();
        $facultyDepartmentIds = $user->department->faculty->departments->pluck('id');

        $courseType = CourseType::where('id', $id)
            ->whereIn('department_id', $facultyDepartmentIds)
            ->first();

        if (!$courseType) {
            return response()->json(['error' => 'Course type not found'], 404);
        }

        if (DepartmentCourseType::where('course_type_id', $id)->exists()) {
            return response()->json(['error' => 'Course type is in use'], 409);
        }

        $courseType->delete();

        return response()->json(['message' => 'Course type deleted']);
    }

    /* =====================================================
     * POST /api/course-types/assign
     * ===================================================== */
    public function bulkAssign(Request $request)
    {
        $user = Auth::user();

        $data = $request->validate([
            'courseIds' => 'required|array',
            'courseIds.*' => 'string',
            'courseTypeId' => 'required|string',
            'departmentId' => 'required|string',
            'curriculumId' => 'required|string',
        ]);

        DB::transaction(function () use ($data, $user) {
            DepartmentCourseType::whereIn('course_id', $data['courseIds'])
                ->where('department_id', $data['departmentId'])
                ->where('curriculum_id', $data['curriculumId'])
                ->delete();

            foreach ($data['courseIds'] as $courseId) {
                DepartmentCourseType::create([
                    'course_id' => $courseId,
                    'department_id' => $data['departmentId'],
                    'course_type_id' => $data['courseTypeId'],
                    'curriculum_id' => $data['curriculumId'],
                    'assigned_by_id' => $user->id,
                ]);
            }

            AuditLog::create([
                'user_id' => $user->id,
                'entity_type' => 'DepartmentCourseType',
                'entity_id' => $data['courseTypeId'],
                'action' => 'CREATE',
                'changes' => $data,
            ]);
        });

        return response()->json(['success' => true]);
    }
}
