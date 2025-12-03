<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Faculty;

class FacultyController extends Controller
{
    // GET /api/faculties
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user || !in_array($user->role, ['SUPER_ADMIN', 'CHAIRPERSON'])) {
            return response()->json(['error' => 'Unauthorized - Admin access required'], 401);
        }

        // Fetch faculties with counts
        $faculties = Faculty::withCount(['departments', 'users', 'curricula'])
            ->orderBy('name', 'asc')
            ->get();

        return response()->json(['faculties' => $faculties]);
    }

    // POST /api/faculties
    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'SUPER_ADMIN') {
            return response()->json(['error' => 'Unauthorized - Super Admin access required'], 401);
        }

        $name = $request->input('name');
        $code = $request->input('code');

        // Validate input
        if (!$name || !$code) {
            return response()->json(['error' => 'Missing required fields'], 400);
        }

        // Check if faculty code already exists
        $existingFaculty = Faculty::where('code', $code)->first();
        if ($existingFaculty) {
            return response()->json(['error' => 'Faculty code already exists'], 400);
        }

        // Create faculty
        $faculty = Faculty::create([
            'name' => $name,
            'code' => $code,
        ]);

        return response()->json([
            'message' => 'Faculty created successfully',
            'faculty' => $faculty,
        ], 201);
    }

    // PUT /api/faculties/{id}
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'SUPER_ADMIN') {
            return response()->json(['error' => 'Unauthorized - Super Admin access required'], 401);
        }

        $name = $request->input('name');
        $code = $request->input('code');

        // Validate input
        if (!$name || !$code) {
            return response()->json(['error' => 'Missing required fields'], 400);
        }

        // Check if faculty exists
        $faculty = Faculty::find($id);
        if (!$faculty) {
            return response()->json(['error' => 'Faculty not found'], 404);
        }

        // Check if faculty code already exists (excluding current faculty)
        $codeExists = Faculty::where('code', $code)
            ->where('id', '!=', $id)
            ->first();

        if ($codeExists) {
            return response()->json(['error' => 'Faculty code already exists'], 400);
        }

        // Update faculty
        $faculty->update([
            'name' => $name,
            'code' => $code,
        ]);

        $updatedFaculty = Faculty::withCount(['users', 'departments', 'curricula'])->find($id);

        return response()->json([
            'message' => 'Faculty updated successfully',
            'faculty' => $updatedFaculty,
        ]);
    }

    // DELETE /api/faculties/{id}
    public function destroy($id)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'SUPER_ADMIN') {
            return response()->json(['error' => 'Unauthorized - Super Admin access required'], 401);
        }

        $faculty = Faculty::withCount(['users', 'departments', 'curricula'])->find($id);

        if (!$faculty) {
            return response()->json(['error' => 'Faculty not found'], 404);
        }

        // Check if faculty has associated data
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
    }

    // GET /api/faculties/{id}
    public function show($id)
    {
        $user = Auth::user();
        if (!$user || !in_array($user->role, ['SUPER_ADMIN', 'CHAIRPERSON'])) {
            return response()->json(['error' => 'Unauthorized - Admin access required'], 401);
        }

        $faculty = Faculty::withCount(['departments', 'users', 'curricula'])->find($id);

        if (!$faculty) {
            return response()->json(['error' => 'Faculty not found'], 404);
        }

        return response()->json(['faculty' => $faculty]);
    }

    // GET /api/faculty/concentration-label
    public function concentrationLabel(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->faculty_id) {
            return response()->json(['error' => 'User not authenticated or no faculty associated'], 401);
        }

        $faculty = Faculty::find($user->faculty_id);

        if (!$faculty) {
            return response()->json(['error' => 'Faculty not found'], 404);
        }

        return response()->json([
            'concentrationLabel' => $faculty->concentrationLabel ?? 'Concentrations'
        ]);
    }
}