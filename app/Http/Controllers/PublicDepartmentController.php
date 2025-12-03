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
                $query->where('facultyId', $facultyId);
            }

            $departments = $query->get();

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