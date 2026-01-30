<?php

namespace App\Http\Controllers\API\Chairperson;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\{
    Curriculum,
    CurriculumCourse,
    ElectiveRule,
    AuditLog
};

class ElectiveRuleController extends Controller
{
    /* =========================================================
     * GET /api/curricula/{id}/elective-rules
     * ========================================================= */
    public function index($curriculumId)
    {
        $user = Auth::user();

        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => 'Chairperson access required'], 403);
        }

        $faculty = $user->department?->faculty?->load('departments');
        if (!$faculty) {
            return response()->json(['error' => 'User department or faculty not found'], 404);
        }

        $departmentIds = $faculty->departments->pluck('id')->toArray();

        $curriculum = Curriculum::where('id', $curriculumId)
            ->whereIn('department_id', $departmentIds)
            ->first();

        if (!$curriculum) {
            return response()->json(['error' => 'Curriculum not found or access denied'], 404);
        }

        $electiveRules = ElectiveRule::where('curriculum_id', $curriculumId)
            ->orderBy('category')
            ->get();

        $curriculumCourses = CurriculumCourse::with([
            'course.departmentCourseTypes.courseType'
        ])
            ->where('curriculum_id', $curriculumId)
            ->get();

        /* =====================================================
         * ✅ FIXED PART — GUARANTEED ARRAY
         * ===================================================== */
        $categories = [];

        foreach ($curriculumCourses as $cc) {
            if (
                isset($cc->course) &&
                isset($cc->course->departmentCourseTypes) &&
                isset($cc->course->departmentCourseTypes[0]) &&
                isset($cc->course->departmentCourseTypes[0]->courseType)
            ) {
                $categories[] = $cc->course->departmentCourseTypes[0]->courseType->name;
            }
        }

        // force clean indexed array
        $categories = array_values(array_unique($categories));
        /* ===================================================== */

        return response()->json([
            'electiveRules' => $electiveRules,
            'courseCategories' => $categories, // ALWAYS array
            'curriculumCourses' => $curriculumCourses->map(fn ($cc) => [
                'id' => $cc->course->id,
                'code' => $cc->course->code,
                'name' => $cc->course->name,
                'category' => $cc->course->departmentCourseTypes[0]->courseType->name ?? 'Unassigned',
                'credits' => $cc->course->credits,
                'isRequired' => (bool) $cc->is_required,
                'semester' => $cc->semester,
                'year' => $cc->year,
            ])->values(), // force array
        ]);
    }

    /* =========================================================
     * POST /api/curricula/{id}/elective-rules
     * ========================================================= */
    public function store(Request $request, $curriculumId)
    {
        $user = Auth::user();

        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => 'Chairperson access required'], 403);
        }

        $data = $request->validate([
            'category' => 'required|string',
            'requiredCredits' => 'required|integer|min:0',
            'description' => 'nullable|string',
        ]);

        $faculty = $user->department?->faculty?->load('departments');
        $departmentIds = $faculty->departments->pluck('id')->toArray();

        $curriculum = Curriculum::where('id', $curriculumId)
            ->whereIn('department_id', $departmentIds)
            ->firstOrFail();

        $rule = ElectiveRule::create([
            'curriculum_id' => $curriculumId,
            'category' => $data['category'],
            'required_credits' => $data['requiredCredits'],
            'description' => $data['description'] ?? null,
        ]);

        AuditLog::create([
            'user_id' => $user->id,
            'entity_type' => 'ElectiveRule',
            'entity_id' => $rule->id,
            'action' => 'CREATE',
            'changes' => $data,
            'curriculum_id' => $curriculumId,
        ]);

        return response()->json(['electiveRule' => $rule], 201);
    }

    /* =========================================================
     * PUT /api/curricula/{id}/elective-rules/settings
     * ========================================================= */
    public function updateSettings(Request $request, $curriculumId)
    {
        $user = Auth::user();

        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => 'Chairperson access required'], 403);
        }

        $faculty = $user->department?->faculty?->load('departments');
        $departmentIds = $faculty->departments->pluck('id')->toArray();

        $curriculum = Curriculum::where('id', $curriculumId)
            ->whereIn('department_id', $departmentIds)
            ->firstOrFail();

        $updates = [];

        DB::transaction(function () use ($request, $curriculumId, $user, &$updates) {

            /* ---------- Free Electives ---------- */
            if ($request->hasAny(['freeElectiveCredits', 'freeElectiveName'])) {
                // Get the name first to determine what we're looking for
                $name = $request->freeElectiveName ?? 'Free Electives';
                
                // First try to find by exact name, then try case-insensitive LIKE search
                $rule = ElectiveRule::where('curriculum_id', $curriculumId)
                    ->where(function($query) use ($name) {
                        $query->where('category', $name)
                              ->orWhere('category', 'LIKE', '%free%');
                    })
                    ->first();

                $credits = $request->freeElectiveCredits ?? $rule?->required_credits ?? 0;

                if ($rule) {
                    $rule->update([
                        'category' => $name,
                        'required_credits' => $credits,
                    ]);
                } else {
                    ElectiveRule::create([
                        'curriculum_id' => $curriculumId,
                        'category' => $name,
                        'required_credits' => $credits,
                    ]);
                }

                $updates[] = [
                    'type' => 'freeElective',
                    'name' => $name,
                    'credits' => $credits,
                ];
            }

            /* ---------- Course Requirements ---------- */
            if (is_array($request->courseRequirements)) {
                foreach ($request->courseRequirements as $cr) {
                    if (!isset($cr['courseId'], $cr['isRequired'])) {
                        continue;
                    }

                    $cc = CurriculumCourse::where('curriculum_id', $curriculumId)
                        ->where('course_id', $cr['courseId'])
                        ->first();

                    if ($cc) {
                        $cc->update(['is_required' => (bool) $cr['isRequired']]);
                        $updates[] = [
                            'courseId' => $cr['courseId'],
                            'isRequired' => (bool) $cr['isRequired'],
                        ];
                    }
                }
            }

            if (!empty($updates)) {
                AuditLog::create([
                    'user_id' => $user->id,
                    'entity_type' => 'ElectiveRule',
                    'entity_id' => $curriculumId,
                    'action' => 'UPDATE',
                    'changes' => ['updates' => $updates],
                    'curriculum_id' => $curriculumId,
                ]);
            }
        });

        return response()->json([
            'message' => 'Elective rules updated successfully',
            'updatesCount' => count($updates),
        ]);
    }

    /* =========================================================
     * PUT /api/curricula/{id}/elective-rules/{rule}
     * ========================================================= */
    public function update(Request $request, $curriculumId, $ruleId)
    {
        $rule = ElectiveRule::where('id', $ruleId)
            ->where('curriculum_id', $curriculumId)
            ->firstOrFail();

        $rule->update($request->only(['required_credits', 'description']));

        return response()->json(['electiveRule' => $rule]);
    }

    /* =========================================================
     * DELETE /api/curricula/{id}/elective-rules/{rule}
     * ========================================================= */
    public function destroy($curriculumId, $ruleId)
    {
        ElectiveRule::where('id', $ruleId)
            ->where('curriculum_id', $curriculumId)
            ->delete();

        return response()->json(['message' => 'Elective rule deleted']);
    }
}
