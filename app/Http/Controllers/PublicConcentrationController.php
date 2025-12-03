<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\{CurriculumConcentration, Concentration, Course};

class PublicConcentrationController extends Controller
{
    // GET /api/public-concentrations
    public function index(Request $request)
    {
        $curriculumId = $request->query('curriculumId');
        $departmentId = $request->query('departmentId');

        if (!$curriculumId || !$departmentId) {
            return response()->json([
                'error' => 'Missing curriculumId or departmentId parameter'
            ], 400);
        }

        // Fetch curriculum concentrations for this curriculum
        $curriculumConcentrations = CurriculumConcentration::where('curriculumId', $curriculumId)
            ->with(['concentration.courses.course'])
            ->get();

        // Fetch all concentrations for the department
        $allDepartmentConcentrations = Concentration::where('departmentId', $departmentId)
            ->with([
                'courses.course',
                'curriculumConcentrations' => function ($q) use ($curriculumId) {
                    $q->where('curriculumId', $curriculumId);
                }
            ])
            ->get();

        // Transform data for frontend
        $concentrations = $allDepartmentConcentrations->map(function ($concentration) {
            $curriculumInfo = $concentration->curriculumConcentrations->first();
            $requiredCourses = $curriculumInfo->requiredCourses ?? $concentration->courses->count();

            return [
                'id' => $concentration->id,
                'name' => $concentration->name,
                'description' => $concentration->description,
                'requiredCourses' => $requiredCourses,
                'totalCourses' => $concentration->courses->count(),
                'courses' => $concentration->courses->map(function ($cc) {
                    return [
                        'code' => $cc->course->code,
                        'name' => $cc->course->name,
                        'credits' => $cc->course->credits,
                        'description' => $cc->course->description
                    ];
                })
            ];
        });

        return response()->json([
            'concentrations' => $concentrations,
            'totalConcentrations' => $concentrations->count()
        ]);
    }
}