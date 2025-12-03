<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Department;
use App\Models\Faculty;

class DepartmentController extends Controller
{
    /**
     * GET /api/departments
     */
    public function index(Request $request)
    {
        $departments = Department::with([
            'faculty:id,name,code',
            'curricula',
            'blacklists',
            'concentrations'
        ])->get();

        return response()->json(['departments' => $departments]);
    }

    /**
     * GET /api/departments/{id}
     */
    public function show($id)
    {
        $department = Department::with([
            'faculty:id,name,code',
            'curricula',
            'blacklists',
            'concentrations'
        ])->find($id);

        if (!$department) {
            return response()->json(['error' => 'Department not found'], 404);
        }

        return response()->json(['department' => $department]);
    }

    /**
     * POST /api/departments
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:255',
            'code'       => 'required|string|max:50',
            'facultyId'  => 'required|integer',
        ]);

        // Validate faculty
        $faculty = Faculty::find($validated['facultyId']);
        if (!$faculty) {
            return response()->json(['error' => 'Invalid faculty'], 400);
        }

        // Ensure department code uniqueness inside the same faculty
        $exists = Department::where('code', $validated['code'])
            ->where('faculty_id', $validated['facultyId'])
            ->exists();

        if ($exists) {
            return response()->json(['error' => 'Department code already exists in this faculty'], 400);
        }

        // Create department
        $department = Department::create([
            'name'       => $validated['name'],
            'code'       => $validated['code'],
            'faculty_id' => $validated['facultyId'],
        ]);

        // Reload with relationships
        $department = Department::with([
            'faculty:id,name,code',
            'curricula',
            'blacklists',
            'concentrations'
        ])->find($department->id);

        return response()->json([
            'message'    => 'Department created successfully',
            'department' => $department,
        ], 201);
    }

    /**
     * PUT /api/departments/{id}
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:255',
            'code'       => 'required|string|max:50',
            'facultyId'  => 'required|integer',
        ]);

        $department = Department::find($id);
        if (!$department) {
            return response()->json(['error' => 'Department not found'], 404);
        }

        // Validate faculty
        $faculty = Faculty::find($validated['facultyId']);
        if (!$faculty) {
            return response()->json(['error' => 'Invalid faculty'], 400);
        }

        // Check for unique department code in the same faculty (exclude current dept)
        $exists = Department::where('code', $validated['code'])
            ->where('faculty_id', $validated['facultyId'])
            ->where('id', '!=', $id)
            ->exists();

        if ($exists) {
            return response()->json(['error' => 'Department code already exists in this faculty'], 400);
        }

        // Update department
        $department->update([
            'name'       => $validated['name'],
            'code'       => $validated['code'],
            'faculty_id' => $validated['facultyId'],
        ]);

        // Reload with relationships
        $department = Department::with([
            'faculty:id,name,code',
            'curricula',
            'blacklists',
            'concentrations'
        ])->find($id);

        return response()->json([
            'message'    => 'Department updated successfully',
            'department' => $department,
        ]);
    }

    /**
     * DELETE /api/departments/{id}
     */
    public function destroy($id)
    {
        $department = Department::withCount(['curricula', 'blacklists', 'concentrations'])->find($id);

        if (!$department) {
            return response()->json(['error' => 'Department not found'], 404);
        }

        // Prevent deletion if related data exists
        if ($department->curricula_count > 0 ||
            $department->blacklists_count > 0 ||
            $department->concentrations_count > 0) {

            return response()->json([
                'error' => 'Cannot delete department with associated curricula, blacklists, or concentrations',
                'details' => [
                    'curricula'      => $department->curricula_count,
                    'blacklists'     => $department->blacklists_count,
                    'concentrations' => $department->concentrations_count,
                ],
            ], 400);
        }

        // Safe delete
        $department->delete();

        return response()->json([
            'message' => 'Department deleted successfully',
        ]);
    }
}
