<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\StudentCourse;

class CompletedCourseController extends Controller
{
    // GET /api/completed-courses?curriculumId=xxx&studentId=yyy
    public function index(Request $request)
    {
        $curriculumId = $request->query('curriculumId');
        $studentId = $request->query('studentId'); // In production, get from Auth

        if (!$curriculumId) {
            return response()->json([
                'error' => 'Missing curriculumId parameter'
            ], 400);
        }

        // In production, fetch from StudentCourse table
        if ($studentId) {
            $completedCourses = StudentCourse::where('studentId', $studentId)
                ->whereIn('status', ['COMPLETED', 'PASSED'])
                ->with('course')
                ->get()
                ->pluck('course.code')
                ->toArray();

            return response()->json([
                'completedCourses' => $completedCourses,
                'source' => 'database'
            ]);
        }

        // Mock data (for development/testing)
        $mockCompletedCourses = [
            'CSX3003', 'CSX3009', 'CSX2003', 'ITX3002', 'CSX3001', 'CSX3002',
            'CSX3004', 'CSX2009', 'CSX2006', 'CSX2008', 'ITX2005', 'ITX2007', 'ITX3007'
        ];

        return response()->json([
            'completedCourses' => $mockCompletedCourses,
            'source' => 'mock_data',
            'note' => 'In production, this would fetch from StudentCourse table based on authenticated student'
        ]);
    }

    // POST /api/completed-courses
    public function store(Request $request)
    {
        $curriculumId = $request->input('curriculumId');
        $completedCourses = $request->input('completedCourses');

        if (!$curriculumId || !is_array($completedCourses)) {
            return response()->json([
                'error' => 'Missing or invalid parameters'
            ], 400);
        }

        // In production, update StudentCourse table here

        return response()->json([
            'success' => true,
            'message' => 'Completed courses updated',
            'completedCourses' => $completedCourses
        ]);
    }
}