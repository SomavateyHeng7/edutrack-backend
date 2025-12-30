<?php

namespace App\Http\Controllers\API\Chairperson;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Course;
use App\Models\CurriculumCoursePrerequisite;
use App\Models\CurriculumCourseCorequisite;
use App\Models\AuditLog;

class CourseController extends Controller
{
    /* =====================================================
     * GET /api/courses
     * ===================================================== */
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => 'Chairperson access required'], 403);
        }

        $search = trim($request->query('search', ''));
        $limit = (int) $request->query('limit', 50);

        $query = Course::where('is_active', true);

        if ($search !== '') {
            $query->where(function ($sub) use ($search) {
                $sub->where('code', 'ILIKE', "%{$search}%")
                    ->orWhere('name', 'ILIKE', "%{$search}%");
            });
        }

        $courses = $query->orderBy('code')->limit($limit)->get();

        return response()->json([
            'courses' => $courses->map(fn ($c) => [
                'id' => $c->id,
                'code' => $c->code,
                'name' => $c->name,
                'credits' => $c->credits,
                'creditHours' => $c->credit_hours,
                'description' => $c->description,
                'requiresPermission' => $c->requires_permission,
                'summerOnly' => $c->summer_only,
                'requiresSeniorStanding' => $c->requires_senior_standing,
                'minCreditThreshold' => $c->min_credit_threshold,
            ]),
            'total' => $courses->count(),
        ]);
    }

    /* =====================================================
     * POST /api/courses
     * ===================================================== */
    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => 'Chairperson access required'], 403);
        }

        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:courses,code',
            'name' => 'required|string|max:255',
            'credits' => 'required|numeric|min:0',
            'creditHours' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:100',
        ]);

        $course = Course::create([
            'code' => trim($validated['code']),
            'name' => trim($validated['name']),
            'credits' => $validated['credits'],
            'credit_hours' => $validated['creditHours'] ?? "{$validated['credits']}-0-" . ($validated['credits'] * 2),
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'] ?? null,
            'is_active' => true,
        ]);

        AuditLog::create([
            'user_id' => $user->id,
            'entity_type' => 'Course',
            'entity_id' => $course->id,
            'action' => 'CREATE',
            'description' => "Created course {$course->code}",
        ]);

        return response()->json([
            'course' => $course,
            'message' => 'Course created successfully',
        ], 201);
    }

    /* =====================================================
     * GET /api/courses/{course}
     * ===================================================== */
    public function show($courseId)
    {
        $course = Course::with([
            'prerequisites.prerequisite',
            'corequisites.corequisite'
        ])->find($courseId);

        if (!$course) {
            return response()->json(['error' => 'Course not found'], 404);
        }

        return response()->json([
            'course' => [
                'id' => $course->id,
                'code' => $course->code,
                'name' => $course->name,
                'credits' => $course->credits,
                'creditHours' => $course->credit_hours,
                'description' => $course->description,
                'category' => $course->category,
                'requiresPermission' => $course->requires_permission,
                'summerOnly' => $course->summer_only,
                'requiresSeniorStanding' => $course->requires_senior_standing,
                'minCreditThreshold' => $course->min_credit_threshold,
                'prerequisites' => $course->prerequisites->pluck('prerequisite'),
                'corequisites' => $course->corequisites->pluck('corequisite'),
            ]
        ]);
    }

    /* =====================================================
     * PUT /api/courses/{course}
     * ===================================================== */
    public function update(Request $request, $courseId)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => 'Chairperson access required'], 403);
        }

        $course = Course::find($courseId);

        if (!$course) {
            return response()->json(['error' => 'Course not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'credits' => 'sometimes|numeric|min:0',
            'creditHours' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:100',
            'requiresPermission' => 'sometimes|boolean',
            'summerOnly' => 'sometimes|boolean',
            'requiresSeniorStanding' => 'sometimes|boolean',
            'minCreditThreshold' => 'nullable|numeric|min:0',
        ]);

        if (isset($validated['name'])) {
            $course->name = trim($validated['name']);
        }
        if (isset($validated['credits'])) {
            $course->credits = $validated['credits'];
        }
        if (isset($validated['creditHours'])) {
            $course->credit_hours = $validated['creditHours'];
        }
        if (isset($validated['description'])) {
            $course->description = $validated['description'];
        }
        if (isset($validated['category'])) {
            $course->category = $validated['category'];
        }
        if (isset($validated['requiresPermission'])) {
            $course->requires_permission = $validated['requiresPermission'];
        }
        if (isset($validated['summerOnly'])) {
            $course->summer_only = $validated['summerOnly'];
        }
        if (isset($validated['requiresSeniorStanding'])) {
            $course->requires_senior_standing = $validated['requiresSeniorStanding'];
        }
        if (isset($validated['minCreditThreshold'])) {
            $course->min_credit_threshold = $validated['minCreditThreshold'];
        }

        $course->save();

        AuditLog::create([
            'user_id' => $user->id,
            'entity_type' => 'Course',
            'entity_id' => $course->id,
            'action' => 'UPDATE',
            'description' => "Updated course {$course->code}",
        ]);

        return response()->json([
            'course' => $course,
            'message' => 'Course updated successfully',
        ]);
    }

    /* =====================================================
     * DELETE /api/courses/{course}
     * ===================================================== */
    public function destroy($courseId)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => 'Chairperson access required'], 403);
        }

        $course = Course::find($courseId);

        if (!$course) {
            return response()->json(['error' => 'Course not found'], 404);
        }

        // Soft delete by marking as inactive
        $course->is_active = false;
        $course->save();

        AuditLog::create([
            'user_id' => $user->id,
            'entity_type' => 'Course',
            'entity_id' => $course->id,
            'action' => 'DELETE',
            'description' => "Deleted course {$course->code}",
        ]);

        return response()->json([
            'message' => 'Course deleted successfully',
        ]);
    }

    /* =====================================================
     * GET /api/courses/search
     * ===================================================== */
    public function search(Request $request)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => 'Chairperson access required'], 403);
        }

        $q = trim($request->query('q', ''));
        $limit = (int) $request->query('limit', 10);
        $exclude = $request->filled('exclude')
            ? explode(',', $request->query('exclude'))
            : [];

        $query = Course::where('is_active', true);

        if ($exclude) {
            $query->whereNotIn('id', $exclude);
        }

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('code', 'ILIKE', "%{$q}%")
                    ->orWhere('name', 'ILIKE', "%{$q}%");
            });
        }

        $courses = $query->orderBy('code')->limit($limit)->get();

        return response()->json([
            'courses' => $courses->map(fn ($c) => [
                'id' => $c->id,
                'code' => $c->code,
                'name' => $c->name,
                'credits' => $c->credits,
                'creditHours' => $c->credit_hours,
                'description' => $c->description,
                'requiresPermission' => $c->requires_permission,
                'summerOnly' => $c->summer_only,
                'requiresSeniorStanding' => $c->requires_senior_standing,
                'minCreditThreshold' => $c->min_credit_threshold,
                'displayName' => "{$c->code}: {$c->name}",
            ]),
            'total' => $courses->count(),
        ]);
    }

    /* =====================================================
     * POST /api/courses/bulk-create
     * ===================================================== */
    public function bulkCreate(Request $request)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => 'Chairperson access required'], 403);
        }

        $courses = $request->input('courses');
        if (!is_array($courses)) {
            return response()->json(['error' => 'Courses array is required'], 400);
        }

        return DB::transaction(function () use ($courses, $user) {
            $codes = array_map(fn ($c) => strtolower(trim($c['code'])), $courses);

            if (count($codes) !== count(array_unique($codes))) {
                return response()->json(['error' => 'Duplicate course codes'], 400);
            }

            $existing = Course::whereIn(DB::raw('LOWER(code)'), $codes)->pluck('code');
            if ($existing->isNotEmpty()) {
                return response()->json([
                    'error' => 'Courses already exist: ' . $existing->implode(', ')
                ], 409);
            }

            $created = [];

            foreach ($courses as $c) {
                $course = Course::create([
                    'code' => trim($c['code']),
                    'name' => trim($c['name']),
                    'credits' => $c['credits'],
                    'credit_hours' => $c['creditHours'] ?? "{$c['credits']}-0-" . ($c['credits'] * 2),
                    'description' => $c['description'] ?? null,
                    'is_active' => true,
                ]);

                AuditLog::create([
                    'user_id' => $user->id,
                    'entity_type' => 'Course',
                    'entity_id' => $course->id,
                    'action' => 'CREATE',
                    'description' => "Created course {$course->code}",
                ]);

                $created[] = $course;
            }

            return response()->json([
                'courses' => $created,
                'message' => 'Courses created successfully',
            ]);
        });
    }

    /* =====================================================
     * GET /api/courses/{course}/constraints
     * ===================================================== */
    public function constraints($courseId)
    {
        $course = Course::with([
            'prerequisites.prerequisite',
            'corequisites.corequisite'
        ])->find($courseId);

        if (!$course) {
            return response()->json(['error' => 'Course not found'], 404);
        }

        return response()->json([
            'constraints' => [
                'course' => $course,
                'flags' => [
                    'requiresPermission' => $course->requires_permission,
                    'summerOnly' => $course->summer_only,
                    'requiresSeniorStanding' => $course->requires_senior_standing,
                    'minCreditThreshold' => $course->min_credit_threshold,
                ],
                'prerequisites' => $course->prerequisites->pluck('prerequisite'),
                'corequisites' => $course->corequisites->pluck('corequisite'),
            ]
        ]);
    }

    /* =====================================================
     * POST /api/courses/{course}/prerequisites
     * ===================================================== */
    public function addPrerequisite(Request $request, $courseId)
    {
        $data = $request->validate([
            'prerequisiteId' => 'required|exists:courses,id',
        ]);

        if ($courseId == $data['prerequisiteId']) {
            return response()->json(['error' => 'Cannot self-reference'], 400);
        }

        if (
            CurriculumCoursePrerequisite::where('course_id', $courseId)
                ->where('prerequisite_id', $data['prerequisiteId'])
                ->exists()
        ) {
            return response()->json(['error' => 'Already exists'], 409);
        }

        CurriculumCoursePrerequisite::create([
            'course_id' => $courseId,
            'prerequisite_id' => $data['prerequisiteId'],
        ]);

        return response()->json(['success' => true]);
    }

    /* =====================================================
     * DELETE /api/courses/{course}/prerequisites/{relation}
     * ===================================================== */
    public function removePrerequisite($courseId, $relationId)
    {
        $rel = CurriculumCoursePrerequisite::findOrFail($relationId);

        if ($rel->course_id != $courseId) {
            return response()->json(['error' => 'Invalid relation'], 400);
        }

        $rel->delete();
        return response()->json(['success' => true]);
    }

    /* =====================================================
     * POST /api/courses/{course}/corequisites
     * ===================================================== */
    public function addCorequisite(Request $request, $courseId)
    {
        $data = $request->validate([
            'corequisiteId' => 'required|exists:courses,id',
        ]);

        if ($courseId == $data['corequisiteId']) {
            return response()->json(['error' => 'Cannot self-reference'], 400);
        }

        DB::transaction(function () use ($courseId, $data) {
            CurriculumCourseCorequisite::create([
                'course_id' => $courseId,
                'corequisite_course_id' => $data['corequisiteId'],
            ]);

            CurriculumCourseCorequisite::create([
                'course_id' => $data['corequisiteId'],
                'corequisite_course_id' => $courseId,
            ]);
        });

        return response()->json(['success' => true]);
    }

    /* =====================================================
     * DELETE /api/courses/{course}/corequisites/{relation}
     * ===================================================== */
    public function removeCorequisite($courseId, $relationId)
    {
        $rel = CurriculumCourseCorequisite::findOrFail($relationId);

        DB::transaction(function () use ($rel) {
            CurriculumCourseCorequisite::where('course_id', $rel->corequisite_course_id)
                ->where('corequisite_course_id', $rel->course_id)
                ->delete();

            $rel->delete();
        });

        return response()->json(['success' => true]);
    }
}
