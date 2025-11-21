<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Faculty;
use App\Models\Department;
use App\Models\Blacklist;
use App\Models\BlacklistCourse;
use App\Models\Course;

class BlacklistController extends Controller
{
    // GET /api/blacklists
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $faculty = Faculty::with('departments')->find($user->faculty_id);
        if (!$faculty) {
            return response()->json(['error' => 'No faculty or departments found'], 404);
        }
        $departments = $faculty->departments->pluck('id');

        $blacklists = Blacklist::with([
                'department:id,name',
                'courses.course:id,code,name,credits,description',
                'createdBy:id,name',
            ])
            ->whereIn('department_id', $departments)
            ->withCount(['courses', 'curriculumBlacklists'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['blacklists' => $blacklists], 200);
    }

    // GET /api/blacklists/{id}
    public function show($id)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $faculty = Faculty::with('departments')->find($user->faculty_id);
        if (!$faculty) {
            return response()->json(['error' => 'No faculty or departments found'], 404);
        }
        $departments = $faculty->departments->pluck('id');

        $blacklist = Blacklist::where('id', $id)
            ->whereIn('department_id', $departments)
            ->with([
                'department:id,name',
                'courses.course:id,code,name,credits,description',
                'createdBy:id,name',
            ])
            ->withCount(['courses', 'curriculumBlacklists'])
            ->first();

        if (!$blacklist) {
            return response()->json(['error' => 'Blacklist not found or access denied'], 404);
        }

        return response()->json(['blacklist' => $blacklist], 200);
    }

    // POST /api/blacklists
    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'department_id' => 'required|exists:departments,id',
            'course_ids' => 'required|array',
            'course_ids.*' => 'exists:courses,id',
        ]);

        DB::beginTransaction();
        try {
            $blacklist = Blacklist::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? '',
                'department_id' => $data['department_id'],
                'created_by' => $user->id,
            ]);
            foreach ($data['course_ids'] as $courseId) {
                BlacklistCourse::create([
                    'blacklist_id' => $blacklist->id,
                    'course_id' => $courseId,
                ]);
            }
            DB::commit();
            return response()->json(['message' => 'Blacklist created', 'blacklist' => $blacklist], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error creating blacklist', 'details' => $e->getMessage()], 500);
        }
    }

    // PUT /api/blacklists/{id}
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $blacklist = Blacklist::find($id);
        if (!$blacklist) {
            return response()->json(['error' => 'Blacklist not found'], 404);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'course_ids' => 'required|array',
            'course_ids.*' => 'exists:courses,id',
        ]);

        DB::beginTransaction();
        try {
            $blacklist->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? '',
            ]);
            // Update courses relation
            $blacklist->courses()->sync($data['course_ids']);
            DB::commit();
            return response()->json(['message' => 'Blacklist updated', 'blacklist' => $blacklist], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error updating blacklist', 'details' => $e->getMessage()], 500);
        }
    }

    // DELETE /api/blacklists/{id}
    public function destroy($id)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $blacklist = Blacklist::find($id);
        if (!$blacklist) {
            return response()->json(['error' => 'Blacklist not found'], 404);
        }

        // If blacklist is used in curricula, forbid delete
        if ($blacklist->curriculumBlacklists()->count() > 0) {
            return response()->json(['error' => 'Cannot delete: used in curriculum'], 400);
        }

        $blacklist->delete();
        return response()->json(['message' => 'Blacklist deleted'], 200);
    }
}
