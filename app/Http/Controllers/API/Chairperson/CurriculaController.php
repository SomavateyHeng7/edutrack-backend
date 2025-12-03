<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\{
    Curriculum,
    CurriculumCourse,
    CurriculumConcentration,
    CurriculumBlacklist,
    CurriculumConstraint,
    CurriculumElectiveRule,
    Department,
    Concentration,
    Blacklist,
    Constraint,
    ElectiveRule,
    Course
};

class CurriculaController extends Controller
{
    // GET /api/curricula
    public function index(Request $request)
    {
        $departmentId = $request->query('departmentId');
        $query = Curriculum::with([
            'department:id,name,code',
            'courses.course',
            'concentrations',
            'blacklists',
            'constraints',
            'electiveRules'
        ]);

        if ($departmentId) {
            $query->where('departmentId', $departmentId);
        }

        $curricula = $query->orderBy('name', 'asc')->get();

        return response()->json(['curricula' => $curricula]);
    }

    // POST /api/curricula
    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => 'Chairperson access required'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string',
            'departmentId' => 'required|exists:departments,id',
            'description' => 'nullable|string',
        ]);

        $curriculum = Curriculum::create($validated);

        return response()->json(['curriculum' => $curriculum], 201);
    }

    // GET /api/curricula/{id}
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

    // PUT /api/curricula/{id}
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => 'Chairperson access required'], 403);
        }

        $curriculum = Curriculum::find($id);
        if (!$curriculum) {
            return response()->json(['error' => 'Curriculum not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string',
            'departmentId' => 'sometimes|exists:departments,id',
            'description' => 'nullable|string',
        ]);

        $curriculum->update($validated);

        return response()->json(['curriculum' => $curriculum]);
    }

    // DELETE /api/curricula/{id}
    public function destroy($id)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => 'Chairperson access required'], 403);
        }

        $curriculum = Curriculum::find($id);
        if (!$curriculum) {
            return response()->json(['error' => 'Curriculum not found'], 404);
        }

        $curriculum->delete();

        return response()->json(['message' => 'Curriculum deleted']);
    }

    // GET /api/curricula/{id}/courses
    public function courses($id)
    {
        $curriculum = Curriculum::with('courses.course')->find($id);
        if (!$curriculum) {
            return response()->json(['error' => 'Curriculum not found'], 404);
        }

        $courses = $curriculum->courses->map(function ($cc) {
            return [
                'code' => $cc->course->code,
                'name' => $cc->course->name,
                'credits' => $cc->course->credits,
                'description' => $cc->course->description,
            ];
        });

        return response()->json(['courses' => $courses]);
    }

    // GET /api/curricula/{id}/concentrations
    public function concentrations($id)
    {
        $curriculum = Curriculum::find($id);
        if (!$curriculum) {
            return response()->json(['error' => 'Curriculum not found'], 404);
        }

        $concentrations = CurriculumConcentration::where('curriculumId', $id)
            ->with(['concentration.courses.course'])
            ->get();

        return response()->json(['concentrations' => $concentrations]);
    }

    // GET /api/curricula/{id}/concentrations/{concentrationId}
    public function concentrationShow($id, $concentrationId)
    {
        $concentration = CurriculumConcentration::where('curriculumId', $id)
            ->where('concentrationId', $concentrationId)
            ->with(['concentration.courses.course'])
            ->first();

        if (!$concentration) {
            return response()->json(['error' => 'Concentration not found in curriculum'], 404);
        }

        return response()->json(['concentration' => $concentration]);
    }

    // GET /api/curricula/{id}/blacklists
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

    // GET /api/curricula/{id}/blacklists/{blacklistId}
    public function blacklistShow($id, $blacklistId)
    {
        $blacklist = CurriculumBlacklist::where('curriculumId', $id)
            ->where('blacklistId', $blacklistId)
            ->with('blacklist')
            ->first();

        if (!$blacklist) {
            return response()->json(['error' => 'Blacklist not found in curriculum'], 404);
        }

        return response()->json(['blacklist' => $blacklist]);
    }

    // GET /api/curricula/{id}/constraints
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

    // GET /api/curricula/{id}/constraints/{constraintId}
    public function constraintShow($id, $constraintId)
    {
        $constraint = CurriculumConstraint::where('curriculumId', $id)
            ->where('constraintId', $constraintId)
            ->with('constraint')
            ->first();

        if (!$constraint) {
            return response()->json(['error' => 'Constraint not found in curriculum'], 404);
        }

        return response()->json(['constraint' => $constraint]);
    }

    // GET /api/curricula/{id}/elective-rules
    public function electiveRules($id)
    {
        $curriculum = Curriculum::find($id);
        if (!$curriculum) {
            return response()->json(['error' => 'Curriculum not found'], 404);
        }

        $electiveRules = ElectiveRule::where('curriculumId', $id)
            ->with('electiveRule')
            ->get();

        return response()->json(['electiveRules' => $electiveRules]);
    }

    // GET /api/curricula/{id}/elective-rules/{ruleId}
    public function electiveRuleShow($id, $ruleId)
    {
        $rule = ElectiveRule::where('curriculumId', $id)
            ->where('electiveRuleId', $ruleId)
            ->with('electiveRule')
            ->first();

        if (!$rule) {
            return response()->json(['error' => 'Elective rule not found in curriculum'], 404);
        }

        return response()->json(['electiveRule' => $rule]);
    }

    // GET /api/curricula/{id}/elective-rules/settings
    public function electiveRuleSettings($id)
    {
        $curriculum = Curriculum::find($id);
        if (!$curriculum) {
            return response()->json(['error' => 'Curriculum not found'], 404);
        }

        // Example: return settings field or structure
        return response()->json([
            'settings' => $curriculum->elective_rule_settings ?? []
        ]);
    }
}