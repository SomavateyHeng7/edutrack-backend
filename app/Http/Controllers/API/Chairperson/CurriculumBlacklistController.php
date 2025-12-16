<?php

namespace App\Http\Controllers\API\Chairperson;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\{Curriculum, CurriculumBlacklist, Blacklist, User, AuditLog};

class CurriculumBlacklistController extends Controller
{
    // GET /api/curricula/{id}/blacklists
    public function index(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => 'Chairperson access required'], 403);
        }

        // Get all department IDs in user's faculty
        $faculty = $user->department->faculty ?? null;
        if (!$faculty) {
            return response()->json(['error' => 'User department or faculty not found'], 404);
        }
        $facultyDepartmentIds = $faculty->departments->pluck('id')->toArray();

        // Check curriculum access
        $curriculum = Curriculum::where('id', $id)
            ->whereIn('department_id', $facultyDepartmentIds)
            ->first();
        if (!$curriculum) {
            return response()->json(['error' => 'Curriculum not found or access denied'], 404);
        }

        // Available blacklists in faculty
        $availableBlacklists = Blacklist::with(['courses.course'])
            ->whereIn('department_id', $facultyDepartmentIds)
            ->withCount('courses')
            ->orderByDesc('created_at')
            ->get();

        // Assigned blacklists for this curriculum
        $assignedBlacklists = CurriculumBlacklist::with(['blacklist.courses.course'])
            ->where('curriculum_id', $id)
            ->orderByDesc('created_at')
            ->get();

        // Format response
        $formattedAvailable = $availableBlacklists->map(function ($b) {
            return [
                'id' => $b->id,
                'name' => $b->name,
                'description' => $b->description,
                'courses' => $b->courses->map(function ($bc) {
                    return [
                        'id' => $bc->course->id,
                        'code' => $bc->course->code,
                        'name' => $bc->course->name,
                        'credits' => $bc->course->credits,
                        'description' => $bc->course->description,
                    ];
                }),
                'courseCount' => $b->courses_count,
                'createdAt' => $b->created_at,
                'updatedAt' => $b->updated_at,
            ];
        });

        $formattedAssigned = $assignedBlacklists->map(function ($cb) {
            $b = $cb->blacklist;
            return [
                'id' => $cb->id,
                'blacklistId' => $cb->blacklist_id,
                'assignedAt' => $cb->created_at,
                'blacklist' => [
                    'id' => $b->id,
                    'name' => $b->name,
                    'description' => $b->description,
                    'courses' => $b->courses->map(function ($bc) {
                        return [
                            'id' => $bc->course->id,
                            'code' => $bc->course->code,
                            'name' => $bc->course->name,
                            'credits' => $bc->course->credits,
                            'description' => $bc->course->description,
                        ];
                    }),
                    'courseCount' => $b->courses->count(),
                    'createdAt' => $b->created_at,
                    'updatedAt' => $b->updated_at,
                ]
            ];
        });

        return response()->json([
            'availableBlacklists' => $formattedAvailable,
            'assignedBlacklists' => $formattedAssigned,
            'stats' => [
                'totalAvailable' => $formattedAvailable->count(),
                'totalAssigned' => $formattedAssigned->count(),
                'totalBlacklistedCourses' => $formattedAssigned->sum(fn($ab) => $ab['blacklist']['courseCount']),
            ]
        ]);
    }

    // POST /api/curricula/{id}/blacklists
    public function store(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => 'Chairperson access required'], 403);
        }

        $request->validate([
            'blacklistId' => 'required|string|exists:blacklists,id',
        ]);
        $blacklistId = $request->input('blacklistId');

        // Get all department IDs in user's faculty
        $faculty = $user->department->faculty ?? null;
        if (!$faculty) {
            return response()->json(['error' => 'User faculty or department not found'], 404);
        }
        $facultyDepartmentIds = $faculty->departments->pluck('id')->toArray();

        // Check curriculum access
        $curriculum = Curriculum::where('id', $id)
            ->whereIn('department_id', $facultyDepartmentIds)
            ->first();
        if (!$curriculum) {
            return response()->json(['error' => 'Curriculum not found or access denied'], 404);
        }

        // Check blacklist access
        $blacklist = Blacklist::where('id', $blacklistId)
            ->whereIn('department_id', $facultyDepartmentIds)
            ->withCount('courses')
            ->first();
        if (!$blacklist) {
            return response()->json(['error' => 'Blacklist not found or access denied'], 404);
        }

        // Check if already assigned
        if (CurriculumBlacklist::where('curriculum_id', $id)->where('blacklist_id', $blacklistId)->exists()) {
            return response()->json(['error' => 'Blacklist is already assigned to this curriculum'], 409);
        }

        // Assign blacklist and log
        $assignment = null;
        DB::transaction(function () use ($user, $id, $blacklistId, $blacklist, &$assignment, $curriculum) {
            $assignment = CurriculumBlacklist::create([
                'curriculum_id' => $id,
                'blacklist_id' => $blacklistId,
            ]);
            AuditLog::create([
                'user_id' => $user->id,
                'entity_type' => 'CurriculumBlacklist',
                'entity_id' => $assignment->id,
                'action' => 'CREATE',
                'description' => "Assigned blacklist \"{$blacklist->name}\" to curriculum \"{$curriculum->name}\"",
                'curriculum_id' => $id,
                'changes' => [
                    'blacklistId' => $blacklistId,
                    'blacklistName' => $blacklist->name,
                    'courseCount' => $blacklist->courses_count,
                ]
            ]);
        });

        return response()->json([
            'assignment' => $assignment,
            'message' => "Blacklist \"{$blacklist->name}\" assigned successfully and is now effective for this curriculum"
        ]);
    }

    // DELETE /api/curricula/{id}/blacklists/{blacklistId}
    public function destroy($curriculumId, $blacklistId)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => 'Chairperson access required'], 403);
        }

        // Get all department IDs in user's faculty
        $faculty = $user->department->faculty ?? null;
        if (!$faculty) {
            return response()->json(['error' => 'User department not found'], 403);
        }
        $facultyDepartmentIds = $faculty->departments->pluck('id')->toArray();

        // Check curriculum access
        $curriculum = Curriculum::where('id', $curriculumId)
            ->whereIn('department_id', $facultyDepartmentIds)
            ->first();
        if (!$curriculum) {
            return response()->json(['error' => 'Curriculum not found or access denied'], 404);
        }

        // Find assignment
        $assignment = CurriculumBlacklist::with('blacklist')
            ->where('curriculum_id', $curriculumId)
            ->where('blacklist_id', $blacklistId)
            ->first();
        if (!$assignment) {
            return response()->json(['error' => 'Blacklist assignment not found'], 404);
        }

        // Check blacklist access
        if (!in_array($assignment->blacklist->department_id, $facultyDepartmentIds)) {
            return response()->json(['error' => 'Access denied to this blacklist'], 403);
        }

        // Remove assignment and log
        DB::transaction(function () use ($user, $assignment, $curriculumId, $curriculum) {
            $assignmentData = [
                'id' => $assignment->id,
                'blacklistId' => $assignment->blacklist_id,
                'blacklistName' => $assignment->blacklist->name,
                'assignedAt' => $assignment->created_at,
            ];
            $assignment->delete();
            AuditLog::create([
                'user_id' => $user->id,
                'entity_type' => 'CurriculumBlacklist',
                'entity_id' => $assignmentData['id'],
                'action' => 'DELETE',
                'description' => "Removed blacklist \"{$assignmentData['blacklistName']}\" from curriculum \"{$curriculum->name}\"",
                'curriculum_id' => $curriculumId,
                'changes' => [
                    'removedAssignment' => $assignmentData
                ]
            ]);
        });

        return response()->json([
            'message' => "Blacklist \"{$assignment->blacklist->name}\" removed successfully from curriculum"
        ]);
    }
}