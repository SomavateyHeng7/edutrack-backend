<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\Faculty;

class FacultyController extends Controller
{
    // GET /api/faculties
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user || !in_array($user->role, ['SUPER_ADMIN', 'CHAIRPERSON'])) {
                return response()->json(['error' => 'Unauthorized - Admin access required'], 401);
            }

            $faculties = Faculty::withCount(['departments', 'users', 'curricula'])
                ->orderBy('name', 'asc')
                ->get();

            return response()->json(['faculties' => $faculties]);
        } catch (\Exception $e) {
            Log::error('Error fetching faculties: '.$e->getMessage());
            return response()->json(['error' => 'Failed to fetch faculties'], 500);
        }
    }

    // POST /api/faculties
    public function store(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user || $user->role !== 'SUPER_ADMIN') {
                return response()->json(['error' => 'Unauthorized - Super Admin access required'], 401);
            }
            $data = $request->only(['name', 'code']);
            if (!$data['name'] || !$data['code']) {
                return response()->json(['error' => 'Missing required fields'], 400);
            }

            if (Faculty::where('code', $data['code'])->exists()) {
                return response()->json(['error' => 'Faculty code already exists'], 400);
            }

            $faculty = Faculty::create([
                'name' => $data['name'],
                'code' => $data['code'],
            ]);

            return response()->json([
                'message' => 'Faculty created successfully',
                'faculty' => $faculty,
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error creating faculty: '.$e->getMessage());
            return response()->json(['error' => 'Error creating faculty'], 500);
        }
    }

    // PUT /api/faculties/{facultyId}
    public function update(Request $request, $facultyId)
    {
        try {
            $user = Auth::user();
            if (!$user || $user->role !== 'SUPER_ADMIN') {
                return response()->json(['error' => 'Unauthorized - Super Admin access required'], 401);
            }

            $data = $request->only(['name', 'code']);
            if (!$data['name'] || !$data['code']) {
                return response()->json(['error' => 'Missing required fields'], 400);
            }

            $faculty = Faculty::find($facultyId);
            if (!$faculty) {
                return response()->json(['error' => 'Faculty not found'], 404);
            }

            if (Faculty::where('code', $data['code'])->where('id', '!=', $facultyId)->exists()) {
                return response()->json(['error' => 'Faculty code already exists'], 400);
            }

            $faculty->update([
                'name' => $data['name'],
                'code' => $data['code'],
            ]);

            $faculty->loadCount(['users', 'departments', 'curricula']);

            return response()->json([
                'message' => 'Faculty updated successfully',
                'faculty' => $faculty,
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating faculty: '.$e->getMessage());
            return response()->json(['error' => 'Error updating faculty'], 500);
        }
    }

    // DELETE /api/faculties/{facultyId}
    public function destroy($facultyId)
    {
        try {
            $user = Auth::user();
            if (!$user || $user->role !== 'SUPER_ADMIN') {
                return response()->json(['error' => 'Unauthorized - Super Admin access required'], 401);
            }

            $faculty = Faculty::withCount(['users', 'departments', 'curricula'])->find($facultyId);
            if (!$faculty) {
                return response()->json(['error' => 'Faculty not found'], 404);
            }

            if ($faculty->users_count > 0 || $faculty->departments_count > 0 || $faculty->curricula_count > 0) {
                return response()->json([
                    'error' => 'Cannot delete faculty with associated users, departments, or curricula',
                    'details' => [
                        'users' => $faculty->users_count,
                        'departments' => $faculty->departments_count,
                        'curricula' => $faculty->curricula_count,
                    ]
                ], 400);
            }

            $faculty->delete();

            return response()->json([
                'message' => 'Faculty deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting faculty: '.$e->getMessage());
            return response()->json(['error' => 'Error deleting faculty'], 500);
        }
    }
}
