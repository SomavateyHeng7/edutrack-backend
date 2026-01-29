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
     * Returns flat list with hierarchy metadata
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
            ->withCount(['children', 'departmentCourseTypes as usage_count'])
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        $seeded = $courseTypes->contains(fn ($t) => (bool) $t->seeded);

        return response()->json([
            'courseTypes' => $courseTypes->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'color' => $t->color,
                'departmentId' => $t->department_id,
                'parentId' => $t->parent_course_type_id,
                'position' => $t->position,
                'childCount' => $t->children_count,
                'usageCount' => $t->usage_count,
                'seeded' => (bool) $t->seeded,
                'createdAt' => $t->created_at,
                'updatedAt' => $t->updated_at,
            ])->values(),
            'seeded' => $seeded,
            'total' => $courseTypes->count(),
        ]);
    }

    /* =====================================================
     * GET /api/course-types/tree
     * Returns nested tree structure for efficient rendering
     * ===================================================== */
    public function tree(Request $request)
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
            ->withCount('departmentCourseTypes as usage_count')
            ->orderBy('position')
            ->get();

        $tree = CourseType::buildTree($courseTypes);

        return response()->json([
            'tree' => $tree,
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
            ->withCount(['children', 'departmentCourseTypes as usage_count'])
            ->first();

        if (!$courseType) {
            return response()->json(['error' => 'Course type not found'], 404);
        }

        return response()->json([
            'id' => $courseType->id,
            'name' => $courseType->name,
            'color' => $courseType->color,
            'departmentId' => $courseType->department_id,
            'parentId' => $courseType->parent_course_type_id,
            'position' => $courseType->position,
            'childCount' => $courseType->children_count,
            'usageCount' => $courseType->usage_count,
            'seeded' => (bool) $courseType->seeded,
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
            'parentId' => 'nullable|string|exists:course_types,id',
            'position' => 'nullable|integer|min:0',
        ]);

        $faculty = $user->faculty->load('departments');
        $departmentId = $data['departmentId']
            ?? $faculty->departments->first()->id;

        if (!$faculty->departments->pluck('id')->contains($departmentId)) {
            return response()->json(['error' => 'Department not accessible'], 403);
        }

        // Check unique name within same parent level
        $existingQuery = CourseType::where('name', $data['name'])
            ->where('department_id', $departmentId);
        
        if (isset($data['parentId'])) {
            $existingQuery->where('parent_course_type_id', $data['parentId']);
        } else {
            $existingQuery->whereNull('parent_course_type_id');
        }

        if ($existingQuery->exists()) {
            return response()->json(['error' => 'Course type name already exists at this level'], 409);
        }

        // Calculate next position if not provided
        $position = $data['position'] ?? CourseType::where('department_id', $departmentId)
            ->where('parent_course_type_id', $data['parentId'] ?? null)
            ->max('position') + 1;

        $courseType = CourseType::create([
            'name' => $data['name'],
            'color' => $data['color'],
            'department_id' => $departmentId,
            'parent_course_type_id' => $data['parentId'] ?? null,
            'position' => $position,
        ]);

        // Return with computed counts
        $courseType->loadCount(['children', 'departmentCourseTypes as usage_count']);

        return response()->json([
            'id' => $courseType->id,
            'name' => $courseType->name,
            'color' => $courseType->color,
            'departmentId' => $courseType->department_id,
            'parentId' => $courseType->parent_course_type_id,
            'position' => $courseType->position,
            'childCount' => $courseType->children_count ?? 0,
            'usageCount' => $courseType->usage_count ?? 0,
            'seeded' => (bool) $courseType->seeded,
            'createdAt' => $courseType->created_at,
            'updatedAt' => $courseType->updated_at,
        ], 201);
    }

    /* =====================================================
     * PUT /api/course-types/{id}
     * With cycle detection for hierarchy
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
            'name' => ['required', 'string', 'max:50'],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'parentId' => 'nullable|string|exists:course_types,id',
            'position' => 'nullable|integer|min:0',
        ]);

        // Cycle detection: cannot set parentId to self or any descendant
        if (isset($data['parentId'])) {
            if ($data['parentId'] === $id) {
                return response()->json([
                    'error' => 'Cannot set a course type as its own parent',
                    'code' => 'CYCLE_DETECTED'
                ], 422);
            }

            if ($courseType->isDescendant($data['parentId'])) {
                return response()->json([
                    'error' => 'Cannot set a descendant as parent (would create cycle)',
                    'code' => 'CYCLE_DETECTED'
                ], 422);
            }
        }

        // Check unique name within same parent level (excluding self)
        $existingQuery = CourseType::where('name', $data['name'])
            ->where('department_id', $courseType->department_id)
            ->where('id', '!=', $id);
        
        if (isset($data['parentId'])) {
            $existingQuery->where('parent_course_type_id', $data['parentId']);
        } else {
            $existingQuery->whereNull('parent_course_type_id');
        }

        if ($existingQuery->exists()) {
            return response()->json(['error' => 'Course type name already exists at this level'], 409);
        }

        $courseType->update([
            'name' => $data['name'],
            'color' => $data['color'],
            'parent_course_type_id' => $data['parentId'] ?? null,
            'position' => $data['position'] ?? $courseType->position,
        ]);

        // Return with computed counts
        $courseType->loadCount(['children', 'departmentCourseTypes as usage_count']);

        return response()->json([
            'id' => $courseType->id,
            'name' => $courseType->name,
            'color' => $courseType->color,
            'departmentId' => $courseType->department_id,
            'parentId' => $courseType->parent_course_type_id,
            'position' => $courseType->position,
            'childCount' => $courseType->children_count ?? 0,
            'usageCount' => $courseType->usage_count ?? 0,
            'seeded' => (bool) $courseType->seeded,
            'createdAt' => $courseType->created_at,
            'updatedAt' => $courseType->updated_at,
        ]);
    }

    /* =====================================================
     * DELETE /api/course-types/{id}
     * Handles children by promoting them to root (ON DELETE SET NULL)
     * ===================================================== */
    public function destroy($id)
    {
        $user = Auth::user();
        $facultyDepartmentIds = $user->department->faculty->departments->pluck('id');

        $courseType = CourseType::where('id', $id)
            ->whereIn('department_id', $facultyDepartmentIds)
            ->withCount('children')
            ->first();

        if (!$courseType) {
            return response()->json(['error' => 'Course type not found'], 404);
        }

        if (DepartmentCourseType::where('course_type_id', $id)->exists()) {
            return response()->json(['error' => 'Course type is in use by courses'], 409);
        }

        $childrenCount = $courseType->children_count;

        // Children will have parent_course_type_id set to NULL (become roots) 
        // due to ON DELETE SET NULL foreign key constraint
        $courseType->delete();

        return response()->json([
            'message' => 'Course type deleted',
            'childrenPromoted' => $childrenCount,
        ]);
    }

    /* =====================================================
     * POST /api/course-types/reorder
     * Bulk update positions and parent relationships
     * ===================================================== */
    public function reorder(Request $request)
    {
        $user = Auth::user();

        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => 'Chairperson access required'], 403);
        }

        $data = $request->validate([
            'updates' => 'required|array',
            'updates.*.id' => 'required|string|exists:course_types,id',
            'updates.*.parentId' => 'nullable|string|exists:course_types,id',
            'updates.*.position' => 'required|integer|min:0',
        ]);

        $facultyDepartmentIds = $user->department->faculty->departments->pluck('id');

        DB::transaction(function () use ($data, $facultyDepartmentIds) {
            foreach ($data['updates'] as $update) {
                $courseType = CourseType::where('id', $update['id'])
                    ->whereIn('department_id', $facultyDepartmentIds)
                    ->first();

                if (!$courseType) {
                    continue;
                }

                // Cycle detection
                if (isset($update['parentId'])) {
                    if ($update['parentId'] === $update['id']) {
                        continue; // Skip self-reference
                    }
                    if ($courseType->isDescendant($update['parentId'])) {
                        continue; // Skip cycle-creating updates
                    }
                }

                $courseType->update([
                    'parent_course_type_id' => $update['parentId'] ?? null,
                    'position' => $update['position'],
                ]);
            }
        });

        return response()->json(['success' => true]);
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
