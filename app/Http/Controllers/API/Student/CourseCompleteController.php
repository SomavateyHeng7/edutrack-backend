<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CourseCompleteController extends Controller
{
    public function get(Request $request)
    {
        $curriculumId = $request->query('curriculumId');
        $studentId = $request->query('studentId'); // normally via auth middleware

        if (!$curriculumId) {
            return response()->json([
                'error' => 'Missing curriculumId parameter'
            ], 400);
        }

        // Mock completed courses (replace with DB logic)
        $mockCompletedCourses = [
            'CSX3003',
            'CSX3009',
            'CSX2003',
            'ITX3002',
            'CSX3001',
            'CSX3002',
            'CSX3004',
            'CSX2009',
            'CSX2006',
            'CSX2008',
            'ITX2005',
            'ITX2007',
            'ITX3007'
        ];

        return response()->json([
            'completedCourses' => $mockCompletedCourses,
            'source' => 'mock_data',
            'note' => 'In production, fetch from StudentCourse table based on authenticated student'
        ]);
    }

    public function update(Request $request)
    {
        $curriculumId = $request->input('curriculumId');
        $completedCourses = $request->input('completedCourses');

        if (!$curriculumId || !is_array($completedCourses)) {
            return response()->json([
                'error' => 'Missing or invalid parameters'
            ], 400);
        }

        // In real app: update DB records here

        return response()->json([
            'success' => true,
            'message' => 'Completed courses updated',
            'completedCourses' => $completedCourses
        ]);
    }
}
