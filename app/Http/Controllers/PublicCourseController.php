<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Course;

class PublicCourseController extends Controller
{
    // GET /api/public-courses
    public function index(Request $request)
    {
        try {
            $departmentId = $request->query('department_id');
            $facultyId = $request->query('faculty_id');
            $search = $request->query('search');

            $query = Course::with([
                'prerequisites',
                'corequisites'
            ])->where('is_active', true);

            // Note: Courses don't have direct department relationship in the schema
            // Filtering by department/faculty would require joining through DepartmentCourseType
            // For now, we'll ignore these filters or implement them properly later

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'ILIKE', "%{$search}%")
                      ->orWhere('name', 'ILIKE', "%{$search}%");
                });
            }

            $courses = $query->orderBy('code')->get()->map(function ($course) {
                return [
                    'id' => $course->id,
                    'code' => $course->code,
                    'name' => $course->name,
                    'credits' => $course->credits,
                    'creditHours' => $course->creditHours ?? $course->credit_hours,
                    'description' => $course->description,
                    'prerequisites' => $course->prerequisites->pluck('code')->toArray(),
                    'corequisites' => $course->corequisites->pluck('code')->toArray(),
                    'requiresPermission' => $course->requires_permission ?? false,
                    'summerOnly' => $course->summer_only ?? false,
                    'requiresSeniorStanding' => $course->requires_senior_standing ?? false,
                    'minCreditThreshold' => $course->min_credit_threshold,
                ];
            });

            return response()->json($courses);
        } catch (\Exception $error) {
            return response()->json([
                'error' => 'Failed to fetch courses',
                'details' => [
                    'message' => $error->getMessage(),
                    'stack' => $error->getTraceAsString(),
                    'name' => get_class($error)
                ]
            ], 500);
        }
    }
}