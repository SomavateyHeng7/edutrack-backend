<?php

namespace App\Http\Controllers\API\Chairperson;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\{
    Blacklist,
    Course,
    BlacklistCourse,
    AuditLog
};

class BlacklistController extends Controller
{
    /* =========================================================
     * GET /api/blacklists
     * ========================================================= */
    public function index()
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => 'Chairperson access required'], 403);
        }

        $faculty = $user->faculty?->load('departments');
        if (!$faculty || $faculty->departments->isEmpty()) {
            return response()->json(['error' => 'User faculty or department not found'], 404);
        }

        $departmentIds = $faculty->departments->pluck('id')->toArray();

        $blacklists = Blacklist::whereIn('department_id', $departmentIds)
            ->with([
                'department:id,name',
                'createdBy:id,name',
                'courses.course:id,code,name,credits,description',
                'curriculumBlacklists',
            ])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'blacklists' => $blacklists->map(fn ($bl) => [
                'id' => $bl->id,
                'name' => $bl->name,
                'description' => $bl->description,
                'departmentId' => $bl->department_id,
                'department' => $bl->department,
                'createdBy' => $bl->createdBy,
                'courses' => $bl->courses->map(fn ($bc) => $bc->course),
                'courseCount' => $bl->courses->count(),
                'usageCount' => $bl->curriculumBlacklists->count(),
                'createdAt' => $bl->created_at,
                'updatedAt' => $bl->updated_at,
            ]),
        ]);
    }

    /* =========================================================
     * POST /api/blacklists
     * ========================================================= */
    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => 'Chairperson access required'], 403);
        }

        $faculty = $user->faculty?->load('departments');
        if (!$faculty || $faculty->departments->isEmpty()) {
            return response()->json(['error' => 'User faculty or department not found'], 404);
        }

        $departmentIds = $faculty->departments->pluck('id')->toArray();

        $validated = $request->validate([
            'name' => 'required|string',
            'description' => 'nullable|string',
            'departmentId' => 'nullable|integer|in:' . implode(',', $departmentIds),
            'courseIds' => 'nullable|array',
            'courseIds.*' => 'integer',
        ]);

        // Auto-assign department if not provided
        $departmentId = $validated['departmentId'] ?? $departmentIds[0];

        $exists = Blacklist::where('name', $validated['name'])
            ->where('department_id', $departmentId)
            ->first();

        if ($exists) {
            return response()->json(['error' => 'Blacklist with this name already exists'], 409);
        }

        $blacklist = DB::transaction(function () use ($validated, $user, $departmentId) {
            $blacklist = Blacklist::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'department_id' => $departmentId,
                'created_by_id' => $user->id,
            ]);

            if (!empty($validated['courseIds'])) {
                // Filter to only include course IDs that exist in the database
                $existingCourseIds = Course::whereIn('id', $validated['courseIds'])->pluck('id')->toArray();
                
                foreach ($existingCourseIds as $courseId) {
                    BlacklistCourse::create([
                        'blacklist_id' => $blacklist->id,
                        'course_id' => $courseId,
                    ]);
                }
            }

            AuditLog::create([
                'user_id' => $user->id,
                'entity_type' => 'Blacklist',
                'entity_id' => $blacklist->id,
                'action' => 'CREATE',
                'changes' => $validated,
                'description' => 'Created blacklist "' . $blacklist->name . '"',
            ]);

            return $blacklist;
        });

        // Load relationships and return the created blacklist
        $blacklist->load([
            'department:id,name',
            'createdBy:id,name',
            'courses.course:id,code,name,credits,description',
            'curriculumBlacklists',
        ]);

        return response()->json([
            'id' => $blacklist->id,
            'name' => $blacklist->name,
            'description' => $blacklist->description,
            'departmentId' => $blacklist->department_id,
            'department' => $blacklist->department,
            'createdBy' => $blacklist->createdBy,
            'courses' => $blacklist->courses->map(fn ($bc) => $bc->course),
            'courseCount' => $blacklist->courses->count(),
            'usageCount' => $blacklist->curriculumBlacklists->count(),
            'createdAt' => $blacklist->created_at,
            'updatedAt' => $blacklist->updated_at,
        ], 201);
    }

    /* =========================================================
     * GET /api/blacklists/{id}
     * ========================================================= */
    public function show($id)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => 'Chairperson access required'], 403);
        }

        $faculty = $user->faculty?->load('departments');
        if (!$faculty) {
            return response()->json(['error' => 'User faculty not found'], 404);
        }

        $departmentIds = $faculty->departments->pluck('id')->toArray();

        $blacklist = Blacklist::where('id', $id)
            ->whereIn('department_id', $departmentIds)
            ->with([
                'department:id,name',
                'createdBy:id,name',
                'courses.course:id,code,name,credits,description',
                'curriculumBlacklists',
            ])
            ->first();

        if (!$blacklist) {
            return response()->json(['error' => 'Blacklist not found or access denied'], 404);
        }

        return response()->json(['blacklist' => $blacklist]);
    }

    /* =========================================================
     * PUT /api/blacklists/{id}
     * ========================================================= */
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => 'Chairperson access required'], 403);
        }

        $faculty = $user->faculty?->load('departments');
        if (!$faculty) {
            return response()->json(['error' => 'User faculty not found'], 404);
        }

        $departmentIds = $faculty->departments->pluck('id')->toArray();

        $blacklist = Blacklist::where('id', $id)
            ->whereIn('department_id', $departmentIds)
            ->first();

        if (!$blacklist) {
            return response()->json(['error' => 'Blacklist not found or access denied'], 404);
        }

        $validated = $request->validate([
            'name' => 'nullable|string',
            'description' => 'nullable|string',
            'courseIds' => 'nullable|array',
            'courseIds.*' => 'integer',
        ]);

        DB::transaction(function () use ($validated, $blacklist, $user) {
            $changes = [];

            foreach (['name', 'description'] as $field) {
                if (array_key_exists($field, $validated) && $validated[$field] !== $blacklist->$field) {
                    $changes[$field] = [
                        'from' => $blacklist->$field,
                        'to' => $validated[$field],
                    ];
                    $blacklist->$field = $validated[$field];
                }
            }

            $blacklist->save();

            if (array_key_exists('courseIds', $validated)) {
                BlacklistCourse::where('blacklist_id', $blacklist->id)->delete();
                
                // Filter to only include course IDs that exist in the database
                $existingCourseIds = Course::whereIn('id', $validated['courseIds'])->pluck('id')->toArray();
                
                foreach ($existingCourseIds as $courseId) {
                    BlacklistCourse::create([
                        'blacklist_id' => $blacklist->id,
                        'course_id' => $courseId,
                    ]);
                }
                $changes['courses'] = $validated['courseIds'];
            }

            if (!empty($changes)) {
                AuditLog::create([
                    'user_id' => $user->id,
                    'entity_type' => 'Blacklist',
                    'entity_id' => $blacklist->id,
                    'action' => 'UPDATE',
                    'changes' => $changes,
                    'description' => 'Updated blacklist "' . $blacklist->name . '"',
                ]);
            }
        });

        // Load relationships and return the updated blacklist
        $blacklist->load([
            'department:id,name',
            'createdBy:id,name',
            'courses.course:id,code,name,credits,description',
            'curriculumBlacklists',
        ]);

        return response()->json([
            'id' => $blacklist->id,
            'name' => $blacklist->name,
            'description' => $blacklist->description,
            'departmentId' => $blacklist->department_id,
            'department' => $blacklist->department,
            'createdBy' => $blacklist->createdBy,
            'courses' => $blacklist->courses->map(fn ($bc) => $bc->course),
            'courseCount' => $blacklist->courses->count(),
            'usageCount' => $blacklist->curriculumBlacklists->count(),
            'createdAt' => $blacklist->created_at,
            'updatedAt' => $blacklist->updated_at,
        ]);
    }

    /* =========================================================
     * DELETE /api/blacklists/{id}
     * ========================================================= */
    public function destroy($id)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => 'Chairperson access required'], 403);
        }

        $faculty = $user->faculty?->load('departments');
        if (!$faculty) {
            return response()->json(['error' => 'User faculty not found'], 404);
        }

        $departmentIds = $faculty->departments->pluck('id')->toArray();

        $blacklist = Blacklist::where('id', $id)
            ->whereIn('department_id', $departmentIds)
            ->with('curriculumBlacklists')
            ->first();

        if (!$blacklist) {
            return response()->json(['error' => 'Blacklist not found or access denied'], 404);
        }

        if ($blacklist->curriculumBlacklists->isNotEmpty()) {
            return response()->json(['error' => 'Blacklist is in use and cannot be deleted'], 409);
        }

        DB::transaction(function () use ($blacklist, $user) {
            $blacklist->delete();

            AuditLog::create([
                'user_id' => $user->id,
                'entity_type' => 'Blacklist',
                'entity_id' => $blacklist->id,
                'action' => 'DELETE',
                'changes' => [
                    'name' => $blacklist->name,
                    'description' => $blacklist->description,
                ],
                'description' => 'Deleted blacklist "' . $blacklist->name . '"',
            ]);
        });

        return response()->json(['message' => 'Blacklist deleted successfully']);
    }

    /* =========================================================
     * GET /api/blacklists/courses/search
     * ========================================================= */
    public function searchCourses(Request $request)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => 'Chairperson access required'], 403);
        }

        $code = $request->query('code');
        if (!$code) {
            return response()->json(['error' => 'Course code is required'], 400);
        }

        $course = Course::where('code', $code)->first();
        if (!$course) {
            return response()->json(['error' => 'Course not found'], 404);
        }

        $faculty = $user->faculty?->load('departments');
        $departmentIds = $faculty->departments->pluck('id')->toArray();

        $blacklists = Blacklist::whereIn('department_id', $departmentIds)
            ->whereHas('courses', fn ($q) => $q->where('course_id', $course->id))
            ->with('department:id,name')
            ->get();

        return response()->json([
            'course' => $course,
            'blacklists' => $blacklists,
        ]);
    }
}
