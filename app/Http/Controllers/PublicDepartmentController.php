<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Department;

class PublicDepartmentController extends Controller
{
    // GET /api/public-departments
    public function index(Request $request)
    {
        try {
            $facultyId = $request->query('facultyId');

            // Build query based on facultyId filter
            $query = Department::with([
                'faculty:id,name,code'
            ])->orderBy('name', 'asc');

            if ($facultyId) {
                $query->where('faculty_id', $facultyId);
            }

            $departments = $query->get()->map(function ($dept) {
                return [
                    'id' => $dept->id,
                    'name' => $dept->name,
                    'code' => $dept->code,
                    'facultyId' => $dept->faculty_id, // camelCase for frontend compatibility
                    'faculty' => $dept->faculty,
                ];
            });

            return response()->json(['departments' => $departments]);
        } catch (\Exception $error) {
            return response()->json([
                'error' => 'Failed to fetch departments',
                'details' => [
                    'message' => $error->getMessage(),
                    'stack' => $error->getTraceAsString(),
                    'name' => get_class($error)
                ]
            ], 500);
        }
    }
}