<?php
namespace App\Http\Controllers\API\Chairperson;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\{Blacklist, Department, User, Course, BlacklistCourse, AuditLog};

class BlacklistController extends Controller
{
    // GET /api/blacklists
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => 'Chairperson access required'], 403);
        }
        $faculty = $user->faculty->load('departments');
        if (!$faculty || $faculty->departments->isEmpty()) {
            return response()->json(['error' => 'User faculty or department not found'], 404);
        }
        $accessibleDepartmentIds = $faculty->departments->pluck('id')->toArray();

        $blacklists = Blacklist::whereIn('departmentId', $accessibleDepartmentIds)
            ->with([
                'department:id,name',
                'createdBy:id,name',
                'courses.course:id,code,name,credits,description',
                'curriculumBlacklists',
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'blacklists' => $blacklists->map(function ($bl) {
                return [
                    'id' => $bl->id,
                    'name' => $bl->name,
                    'description' => $bl->description,
                    'departmentId' => $bl->departmentId,
                    'department' => $bl->department,
                    'createdBy' => $bl->createdBy,
                    'courses' => $bl->courses->map(fn($bc) => $bc->course),
                    'courseCount' => $bl->courses->count(),
                    'usageCount' => $bl->curriculumBlacklists->count(),
                    'createdAt' => $bl->created_at,
                    'updatedAt' => $bl->updated_at,
                ];
            }),
        ]);
    }

    // POST /api/blacklists
    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => 'Chairperson access required'], 403);
        }
        $faculty = $user->faculty;
        if (!$faculty) {
            return response()->json(['error' => 'User faculty not found'], 404);
        }
        $faculty->load('departments');
        if ($faculty->departments->isEmpty()) {
            return response()->json(['error' => 'User department not found'], 404);
        }
        $accessibleDepartmentIds = $faculty->departments->pluck('id')->toArray();

        $validated = $request->validate([
            'name' => 'required|string',
            'description' => 'nullable|string',
            'departmentId' => 'required|integer|in:' . implode(',', $accessibleDepartmentIds),
            'courseIds' => 'nullable|array',
            'courseIds.*' => 'integer|exists:courses,id',
        ]);

        $conflict = Blacklist::where('name', $validated['name'])
            ->where('departmentId', $validated['departmentId'])
            ->where('createdById', $user->id)
            ->first();
        if ($conflict) {
            return response()->json(['error' => 'A blacklist with this name already exists'], 409);
        }

        $blacklist = Blacklist::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'departmentId' => $validated['departmentId'],
            'createdById' => $user->id,
        ]);

        if (!empty($validated['courseIds'])) {
            foreach ($validated['courseIds'] as $courseId) {
                BlacklistCourse::create([
                    'blacklistId' => $blacklist->id,
                    'courseId' => $courseId,
                ]);
            }
        }

        AuditLog::create([
            'userId' => $user->id,
            'entityType' => 'Blacklist',
            'entityId' => $blacklist->id,
            'action' => 'CREATE',
            'changes' => json_encode($validated),
            'description' => 'Created blacklist "' . $blacklist->name . '"'
        ]);

        return response()->json(['blacklist' => $blacklist], 201);
    }

    // GET /api/blacklists/{id}
    public function show($id)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => ['code' => 'FORBIDDEN', 'message' => 'Chairperson access required']], 403);
        }
        $faculty = $user->faculty->load('departments');
        if (!$faculty || $faculty->departments->isEmpty()) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'User faculty or department not found']], 404);
        }
        $accessibleDepartmentIds = $faculty->departments->pluck('id')->toArray();

        $blacklist = Blacklist::where('id', $id)
            ->whereIn('departmentId', $accessibleDepartmentIds)
            ->with([
                'courses.course:id,code,name,credits,description',
                'department:id,name',
                'createdBy:id,name',
                'curriculumBlacklists',
            ])
            ->first();

        if (!$blacklist) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Blacklist not found or access denied']], 404);
        }

        return response()->json([
            'blacklist' => [
                'id' => $blacklist->id,
                'name' => $blacklist->name,
                'description' => $blacklist->description,
                'departmentId' => $blacklist->departmentId,
                'department' => $blacklist->department,
                'createdBy' => $blacklist->createdBy,
                'courses' => $blacklist->courses->map(fn($bc) => $bc->course),
                'courseCount' => $blacklist->courses->count(),
                'usageCount' => $blacklist->curriculumBlacklists->count(),
                'createdAt' => $blacklist->created_at,
                'updatedAt' => $blacklist->updated_at,
            ]
        ]);
    }

    // PUT /api/blacklists/{id}
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => ['code' => 'FORBIDDEN', 'message' => 'Chairperson access required']], 403);
        }
        $faculty = $user->faculty;
        if ($faculty) {
            $faculty->load('departments');
        }
        if (!$faculty || $faculty->departments->isEmpty()) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'User faculty or department not found']], 404);
        }
        $accessibleDepartmentIds = $faculty->departments->pluck('id')->toArray();

        $blacklist = Blacklist::where('id', $id)
            ->whereIn('departmentId', $accessibleDepartmentIds)
            ->with(['courses.course:id,code,name'])
            ->first();

        if (!$blacklist) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Blacklist not found or access denied']], 404);
        }

        $name = $request->input('name');
        $description = $request->input('description');
        $courseIds = $request->input('courseIds');

        if ($name !== null && (!is_string($name) || trim($name) === '')) {
            return response()->json(['error' => ['code' => 'INVALID_INPUT', 'message' => 'Blacklist name must be a non-empty string']], 400);
        }
        if ($courseIds !== null && !is_array($courseIds)) {
            return response()->json(['error' => ['code' => 'INVALID_INPUT', 'message' => 'Course IDs must be an array']], 400);
        }

        if ($name && trim($name) !== $blacklist->name) {
            $nameConflict = Blacklist::where('name', trim($name))
                ->where('departmentId', $blacklist->departmentId)
                ->where('createdById', $user->id)
                ->where('id', '!=', $id)
                ->first();
            if ($nameConflict) {
                return response()->json(['error' => ['code' => 'CONFLICT', 'message' => 'A blacklist with this name already exists']], 409);
            }
        }

        if ($courseIds && count($courseIds) > 0) {
            $existingCourses = Course::whereIn('id', $courseIds)->pluck('id')->toArray();
            if (count($existingCourses) !== count($courseIds)) {
                return response()->json(['error' => ['code' => 'INVALID_INPUT', 'message' => 'Some course IDs do not exist']], 400);
            }
        }

        $changes = [];
        if ($name !== null && trim($name) !== $blacklist->name) {
            $changes['name'] = ['from' => $blacklist->name, 'to' => trim($name)];
        }
        if ($description !== null && $description !== $blacklist->description) {
            $changes['description'] = ['from' => $blacklist->description, 'to' => $description];
        }

        DB::transaction(function () use ($blacklist, $name, $description, $courseIds, $changes, $user, $id) {
            if ($name !== null) $blacklist->name = trim($name);
            if ($description !== null) $blacklist->description = trim($description);
            $blacklist->save();

            if ($courseIds !== null) {
                BlacklistCourse::where('blacklistId', $id)->delete();
                foreach ($courseIds as $courseId) {
                    BlacklistCourse::create([
                        'blacklistId' => $id,
                        'courseId' => $courseId
                    ]);
                }
                $changes['courses'] = [
                    'from' => $blacklist->courses->map(fn($bc) => $bc->course->code)->toArray(),
                    'to' => $courseIds
                ];
            }

            if (!empty($changes)) {
                AuditLog::create([
                    'userId' => $user->id,
                    'entityType' => 'Blacklist',
                    'entityId' => $id,
                    'action' => 'UPDATE',
                    'changes' => json_encode($changes),
                    'description' => 'Updated blacklist "' . ($name ?? $blacklist->name) . '"'
                ]);
            }
        });

        $updatedBlacklist = Blacklist::where('id', $id)
            ->with([
                'courses.course:id,code,name,credits,description',
                'department:id,name',
                'createdBy:id,name',
                'curriculumBlacklists',
            ])
            ->first();

        return response()->json([
            'blacklist' => [
                'id' => $updatedBlacklist->id,
                'name' => $updatedBlacklist->name,
                'description' => $updatedBlacklist->description,
                'departmentId' => $updatedBlacklist->departmentId,
                'department' => $updatedBlacklist->department,
                'createdBy' => $updatedBlacklist->createdBy,
                'courses' => $updatedBlacklist->courses->map(fn($bc) => $bc->course),
                'courseCount' => $updatedBlacklist->courses->count(),
                'usageCount' => $updatedBlacklist->curriculumBlacklists->count(),
                'createdAt' => $updatedBlacklist->created_at,
                'updatedAt' => $updatedBlacklist->updated_at,
            ]
        ]);
    }

    // DELETE /api/blacklists/{id}
    public function destroy($id)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => ['code' => 'FORBIDDEN', 'message' => 'Chairperson access required']], 403);
        }
        $faculty = $user->faculty->load('departments');
        if (!$faculty || $faculty->departments->isEmpty()) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'User faculty or department not found']], 404);
        }
        $accessibleDepartmentIds = $faculty->departments->pluck('id')->toArray();

        $blacklist = Blacklist::where('id', $id)
            ->whereIn('departmentId', $accessibleDepartmentIds)
            ->with('curriculumBlacklists')
            ->first();

        if (!$blacklist) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Blacklist not found or access denied']], 404);
        }

        if ($blacklist->curriculumBlacklists->count() > 0) {
            return response()->json(['error' => ['code' => 'CONFLICT', 'message' => 'Cannot delete blacklist that is currently being used by curricula']], 409);
        }

        DB::transaction(function () use ($blacklist, $user, $id) {
            $blacklist->delete();
            AuditLog::create([
                'userId' => $user->id,
                'entityType' => 'Blacklist',
                'entityId' => $id,
                'action' => 'DELETE',
                'changes' => json_encode([
                    'name' => $blacklist->name,
                    'description' => $blacklist->description
                ]),
                'description' => 'Deleted blacklist "' . $blacklist->name . '"'
            ]);
        });

        return response()->json(['message' => 'Blacklist deleted successfully']);
    }

    // POST /api/blacklists/{id}/courses
    public function addCourse(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => 'Chairperson access required'], 403);
        }
        $request->validate([
            'courseId' => 'required|integer|exists:courses,id',
        ]);
        $blacklist = Blacklist::find($id);
        if (!$blacklist) {
            return response()->json(['error' => 'Blacklist not found'], 404);
        }
        $exists = BlacklistCourse::where('blacklistId', $id)
            ->where('courseId', $request->courseId)
            ->exists();
        if ($exists) {
            return response()->json(['error' => 'Course already in blacklist'], 409);
        }
        BlacklistCourse::create([
            'blacklistId' => $id,
            'courseId' => $request->courseId,
        ]);
        AuditLog::create([
            'userId' => $user->id,
            'entityType' => 'BlacklistCourse',
            'entityId' => $id,
            'action' => 'ADD_COURSE',
            'changes' => json_encode(['courseId' => $request->courseId]),
            'description' => 'Added course to blacklist',
        ]);
        return response()->json(['message' => 'Course added to blacklist']);
    }

    // DELETE /api/blacklists/{id}/courses/{courseId}
    public function removeCourse($id, $courseId)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => 'Chairperson access required'], 403);
        }
        $blacklistCourse = BlacklistCourse::where('blacklistId', $id)
            ->where('courseId', $courseId)
            ->first();
        if (!$blacklistCourse) {
            return response()->json(['error' => 'Course not found in blacklist'], 404);
        }
        $blacklistCourse->delete();
        AuditLog::create([
            'userId' => $user->id,
            'entityType' => 'BlacklistCourse',
            'entityId' => $id,
            'action' => 'REMOVE_COURSE',
            'changes' => json_encode(['courseId' => $courseId]),
            'description' => 'Removed course from blacklist',
        ]);
        return response()->json(['message' => 'Course removed from blacklist']);
    }

    // GET /api/blacklists/{id}/courses
    public function listCourses($id)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => 'Chairperson access required'], 403);
        }
        $blacklist = Blacklist::with('courses.course')->find($id);
        if (!$blacklist) {
            return response()->json(['error' => 'Blacklist not found'], 404);
        }
        return response()->json([
            'courses' => $blacklist->courses->map(fn($bc) => $bc->course),
        ]);
    }
}