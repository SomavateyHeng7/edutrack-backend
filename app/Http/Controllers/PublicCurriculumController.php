<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\{
    Curriculum,
    CurriculumBlacklist,
    CurriculumConstraint,
    CurriculumElectiveRule,
    Blacklist,
    Constraint,
    ElectiveRule
};

class PublicCurriculumController extends Controller
{
    // GET /api/public-curricula
    public function index(Request $request)
    {
        $departmentId = $request->query('departmentId');
        $query = Curriculum::with(['department:id,name,code']);

        if ($departmentId) {
            $query->where('departmentId', $departmentId);
        }

        $curricula = $query->orderBy('name', 'asc')->get();

        return response()->json(['curricula' => $curricula]);
    }

    // GET /api/public-curricula/{id}
    public function show($id)
    {
        $curriculum = Curriculum::with([
            'department:id,name,code',
            'courses.course',
            'concentrations',
            'blacklists',
            'constraints',
            'electiveRules'
        ])->find($id);

        if (!$curriculum) {
            return response()->json(['error' => 'Curriculum not found'], 404);
        }

        return response()->json(['curriculum' => $curriculum]);
    }

    // GET /api/public-curricula/{id}/blacklists
    public function blacklists($id)
    {
        $curriculum = Curriculum::find($id);
        if (!$curriculum) {
            return response()->json(['error' => 'Curriculum not found'], 404);
        }

        $blacklists = CurriculumBlacklist::where('curriculumId', $id)
            ->with('blacklist')
            ->get();

        return response()->json(['blacklists' => $blacklists]);
    }

    // GET /api/public-curricula/{id}/constraints
    public function constraints($id)
    {
        $curriculum = Curriculum::find($id);
        if (!$curriculum) {
            return response()->json(['error' => 'Curriculum not found'], 404);
        }

        $constraints = CurriculumConstraint::where('curriculumId', $id)
            ->with('constraint')
            ->get();

        return response()->json(['constraints' => $constraints]);
    }

    // GET /api/public-curricula/{id}/elective-rules
    public function electiveRules($id)
    {
        $curriculum = Curriculum::find($id);
        if (!$curriculum) {
            return response()->json(['error' => 'Curriculum not found'], 404);
        }

        $electiveRules = CurriculumElectiveRule::where('curriculumId', $id)
            ->with('electiveRule')
            ->get();

        return response()->json(['electiveRules' => $electiveRules]);
    }
}