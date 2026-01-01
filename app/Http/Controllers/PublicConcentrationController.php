<?php
namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Models\{CurriculumConcentration, Concentration, Course};

class PublicConcentrationController extends Controller
{
    // GET /api/public-concentrations
    public function index(Request $request)
    {
        $curriculumId = $request->query('curriculum_id');
        $departmentId = $request->query('department_id');

        if (!$curriculumId || !$departmentId) {
            return response()->json([
                'error' => 'Missing curriculumId or departmentId parameter'
            ], 400);
        }

        try {
            // Fetch all concentrations for the department
            $allDepartmentConcentrations = Concentration::where('department_id', $departmentId)
                ->with([
                    'courses.course',
                    'curriculumConcentrations' => function ($q) use ($curriculumId) {
                        $q->where('curriculum_id', $curriculumId);
                    }
                ])
                ->get();

            $concentrations = $allDepartmentConcentrations->map(function ($concentration) {
                $curriculumInfo = $concentration->curriculumConcentrations->first();
                $requiredCourses = $curriculumInfo && isset($curriculumInfo->requiredCourses)
                    ? $curriculumInfo->requiredCourses
                    : $concentration->courses->count();

                return [
                    'id' => $concentration->id,
                    'name' => $concentration->name,
                    'description' => $concentration->description,
                    'requiredCourses' => $requiredCourses,
                    'totalCourses' => $concentration->courses->count(),
                    'courses' => $concentration->courses->map(function ($cc) {
                        if (!$cc->course) return null;
                        return [
                            'code' => $cc->course->code,
                            'name' => $cc->course->name,
                            'credits' => $cc->course->credits,
                            'description' => $cc->course->description
                        ];
                    })->filter()->values(), // Remove nulls and reindex
                ];
            });

            return response()->json([
                'concentrations' => $concentrations,
                'totalConcentrations' => $concentrations->count()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch concentrations', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => 'Failed to fetch concentrations',
                'details' => $e->getMessage()
            ], 500);
        }
    }
}