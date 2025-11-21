<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\Department;
use App\Models\Faculty;

class DepartmentController extends Controller
{
    // GET /api/departments
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user || !in_array($user->role, ['SUPER_ADMIN', 'CHAIRPERSON'])) {
                return response()->json(['error' => 'Unauthorized - Admin access required'], 401);
            }
            $facultyId = $request->query('facultyId');
            $departments = Department::with([
                'faculty:id,name,code',
                'users', 'curricula'
            ])
            ->when($facultyId, function ($q) use ($facultyId) {
                $q->where('faculty_id', $facultyId);
            })
            ->withCount(['users', 'curricula'])
            ->orderBy('name', 'asc')
            ->get();

            return response()->json(['departments' => $departments]);
        } catch (\Exception $e) {
            Log::error('Error fetching departments: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch departments'], 500);
        }
    }

    // POST /api/departments
    public function store(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user || $user->role !== 'SUPER_ADMIN') {
                return response()->json(['error' => 'Unauthorized - Super Admin access required'], 401);
            }
            $data = $request->only(['name', 'code', 'facultyId']);
            if (!$data['name'] || !$data['code'] || !$data['facultyId']) {
                return response()->json(['error' => 'Missing required fields'], 400);
            }
            $faculty = Faculty::find($data['facultyId']);
            if (!$faculty) {
                return response()->json(['error' => 'Invalid faculty'], 400);
            }
            $exists = Department::where([
                ['code', $data['code']],
                ['faculty_id', $data['facultyId']],
            ])->exists();
            if ($exists) {
                return response()->json(['error' => 'Department code already exists in this faculty'], 400);
            }
            $department = Department::create([
                'name' => $data['name'],
                'code' => $data['code'],
                'faculty_id' => $data['facultyId'],
            ]);
            $department->load(['faculty:id,name,code']);
            return response()->json([
                'message' => 'Department created successfully',
                'department' => $department,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating department: ' . $e->getMessage());
            return response()->json(['error' => 'Error creating department'], 500);
        }
    }

    // PUT /api/departments/{departmentId}
    public function update(Request $request, $departmentId)
    {
        try {
            $data = $request->only(['name', 'code', 'facultyId']);
            if (!$data['name'] || !$data['code'] || !$data['facultyId']) {
                return response()->json(['error' => 'Missing required fields'], 400);
            }
            $department = Department::find($departmentId);
            if (!$department) {
                return response()->json(['error' => 'Department not found'], 404);
            }
            $faculty = Faculty::find($data['facultyId']);
            if (!$faculty) {
                return response()->json(['error' => 'Invalid faculty'], 400);
            }
            $exists = Department::where([
                ['code', $data['code']],
                ['faculty_id', $data['facultyId']],
            ])->where('id', '!=', $departmentId)->exists();
            if ($exists) {
                return response()->json(['error' => 'Department code already exists in this faculty'], 400);
            }
            $department->update([
                'name' => $data['name'],
                'code' => $data['code'],
                'faculty_id' => $data['facultyId'],
            ]);
            $department->load([
                'faculty:id,name,code',
                'curricula', 'blacklists', 'concentrations'
            ]);
            return response()->json([
                'message' => 'Department updated successfully',
                'department' => $department
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating department: '.$e->getMessage());
            return response()->json(['error' => 'Error updating department'], 500);
        }
    }

    // DELETE /api/departments/{departmentId}
    public function destroy($departmentId)
    {
        try {
            $department = Department::withCount(['curricula', 'blacklists', 'concentrations'])->find($departmentId);
            if (!$department) {
                return response()->json(['error' => 'Department not found'], 404);
            }
            if ($department->curricula_count > 0 || $department->blacklists_count > 0 || $department->concentrations_count > 0) {
                return response()->json([
                    'error' => 'Cannot delete department with associated curricula, blacklists, or concentrations',
                    'details' => [
                        'curricula' => $department->curricula_count,
                        'blacklists' => $department->blacklists_count,
                        'concentrations' => $department->concentrations_count,
                    ]
                ], 400);
            }
            $department->delete();
            return response()->json([
                'message' => 'Department deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting department: '.$e->getMessage());
            return response()->json(['error' => 'Error deleting department'], 500);
        }
    }
}
