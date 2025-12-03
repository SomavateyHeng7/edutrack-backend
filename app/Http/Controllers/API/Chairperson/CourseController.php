<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Course;
use App\Models\Prerequisite;
use App\Models\Corequisite;
use App\Models\CourseConstraint;

class CourseController extends Controller
{
    // GET /api/courses
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user) return response()->json(['error' => 'Authentication required'], 401);

        $query = Course::query()->where('isActive', true);

        if ($request->has('departmentId')) $query->where('departmentId', $request->input('departmentId'));
        if ($request->has('code')) $query->where('code', 'like', '%' . $request->input('code') . '%');
        if ($request->has('name')) $query->where('name', 'like', '%' . $request->input('name') . '%');

        $courses = $query->orderBy('code')->get();

        return response()->json(['courses' => $courses]);
    }

    // POST /api/courses
    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') return response()->json(['error' => 'Chairperson access required'], 403);

        $validated = $request->validate([
            'code' => 'required|string|unique:courses,code',
            'name' => 'required|string',
            'credits' => 'required|integer',
            'creditHours' => 'nullable|string',
            'description' => 'nullable|string',
            'departmentId' => 'required|exists:departments,id',
        ]);

        $course = Course::create($validated);

        return response()->json(['course' => $course], 201);
    }

    // GET /api/courses/{courseId}
    public function show($courseId)
    {
        $user = Auth::user();
        if (!$user) return response()->json(['error' => 'Authentication required'], 401);

        $course = Course::find($courseId);
        if (!$course) return response()->json(['error' => 'Course not found'], 404);

        return response()->json(['course' => $course]);
    }

    // PUT /api/courses/{courseId}
    public function update(Request $request, $courseId)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') return response()->json(['error' => 'Chairperson access required'], 403);

        $course = Course::find($courseId);
        if (!$course) return response()->json(['error' => 'Course not found'], 404);

        $validated = $request->validate([
            'code' => 'sometimes|string|unique:courses,code,' . $courseId,
            'name' => 'sometimes|string',
            'credits' => 'sometimes|integer',
            'creditHours' => 'nullable|string',
            'description' => 'nullable|string',
            'departmentId' => 'sometimes|exists:departments,id',
        ]);

        $course->update($validated);

        return response()->json(['course' => $course]);
    }

    // DELETE /api/courses/{courseId}
    public function destroy($courseId)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') return response()->json(['error' => 'Chairperson access required'], 403);

        $course = Course::find($courseId);
        if (!$course) return response()->json(['error' => 'Course not found'], 404);

        $course->delete();

        return response()->json(['message' => 'Course deleted']);
    }

    // GET /api/courses/search?q=xxx&limit=10&exclude=1,2,3
    public function search(Request $request)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') return response()->json(['error' => 'Chairperson access required'], 403);

        $query = $request->input('q', '');
        $limit = intval($request->input('limit', 10));
        $excludeIds = $request->has('exclude') ? explode(',', $request->input('exclude')) : [];

        $coursesQuery = Course::where('isActive', true);

        if (!empty($excludeIds)) $coursesQuery->whereNotIn('id', $excludeIds);

        if (trim($query)) {
            $coursesQuery->where(function ($q) use ($query) {
                $q->where('code', 'like', '%' . $query . '%')
                  ->orWhere('name', 'like', '%' . $query . '%');
            });
        }

        $courses = $coursesQuery->orderBy('code')->limit($limit)->get([
            'id', 'code', 'name', 'credits', 'creditHours', 'description',
            'requiresPermission', 'summerOnly', 'requiresSeniorStanding', 'minCreditThreshold'
        ]);

        $transformedCourses = $courses->map(function ($course) {
            return [
                'id' => $course->id,
                'code' => $course->code,
                'name' => $course->name,
                'credits' => $course->credits,
                'creditHours' => $course->creditHours,
                'description' => $course->description,
                'requiresPermission' => $course->requiresPermission,
                'summerOnly' => $course->summerOnly,
                'requiresSeniorStanding' => $course->requiresSeniorStanding,
                'minCreditThreshold' => $course->minCreditThreshold,
                'displayName' => "{$course->code}: {$course->name}",
                'searchableText' => strtolower("{$course->code} {$course->name} {$course->description}"),
            ];
        });

        return response()->json([
            'courses' => $transformedCourses,
            'total' => $transformedCourses->count(),
        ]);
    }

    // POST /api/courses/bulk-create
    public function bulkCreate(Request $request)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') return response()->json(['error' => 'Chairperson access required'], 403);

        $courses = $request->input('courses');
        if (!is_array($courses)) return response()->json(['error' => 'Courses must be an array'], 400);

        $created = [];
        $skipped = [];
        foreach ($courses as $courseData) {
            if (!isset($courseData['code'], $courseData['name'])) {
                $skipped[] = $courseData;
                continue;
            }
            $existing = Course::where('code', $courseData['code'])->first();
            if ($existing) {
                $skipped[] = $courseData;
                continue;
            }
            $created[] = Course::create([
                'code' => $courseData['code'],
                'name' => $courseData['name'],
                'credits' => $courseData['credits'] ?? 3,
                'creditHours' => $courseData['creditHours'] ?? null,
                'description' => $courseData['description'] ?? null,
                'departmentId' => $courseData['departmentId'] ?? null,
            ]);
        }

        return response()->json([
            'created' => $created,
            'skipped' => $skipped,
        ]);
    }

    // GET /api/courses/{courseId}/constraints
    public function constraints($courseId)
    {
        $user = Auth::user();
        if (!$user) return response()->json(['error' => 'Authentication required'], 401);

        $constraints = CourseConstraint::where('course_id', $courseId)->get();
        return response()->json(['constraints' => $constraints]);
    }

    // GET /api/courses/{courseId}/prerequisites
    public function prerequisites($courseId)
    {
        $user = Auth::user();
        if (!$user) return response()->json(['error' => 'Authentication required'], 401);

        $prerequisites = Prerequisite::where('course_id', $courseId)->with('prerequisiteCourse')->get();
        return response()->json(['prerequisites' => $prerequisites]);
    }

    // POST /api/courses/{courseId}/prerequisites
    public function addPrerequisite(Request $request, $courseId)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') return response()->json(['error' => 'Chairperson access required'], 403);

        $validated = $request->validate([
            'prerequisiteCourseId' => 'required|exists:courses,id',
        ]);

        $prerequisite = Prerequisite::create([
            'course_id' => $courseId,
            'prerequisite_course_id' => $validated['prerequisiteCourseId'],
        ]);

        return response()->json(['prerequisite' => $prerequisite], 201);
    }

    // DELETE /api/courses/{courseId}/prerequisites/{prerequisiteRelationId}
    public function removePrerequisite($courseId, $prerequisiteRelationId)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') return response()->json(['error' => 'Chairperson access required'], 403);

        $prerequisite = Prerequisite::where('course_id', $courseId)->where('id', $prerequisiteRelationId)->first();
        if (!$prerequisite) return response()->json(['error' => 'Prerequisite not found'], 404);

        $prerequisite->delete();
        return response()->json(['message' => 'Prerequisite removed']);
    }

    // GET /api/courses/{courseId}/corequisites
    public function corequisites($courseId)
    {
        $user = Auth::user();
        if (!$user) return response()->json(['error' => 'Authentication required'], 401);

        $corequisites = Corequisite::where('course_id', $courseId)->with('corequisiteCourse')->get();
        return response()->json(['corequisites' => $corequisites]);
    }

    // POST /api/courses/{courseId}/corequisites
    public function addCorequisite(Request $request, $courseId)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') return response()->json(['error' => 'Chairperson access required'], 403);

        $validated = $request->validate([
            'corequisiteCourseId' => 'required|exists:courses,id',
        ]);

        $corequisite = Corequisite::create([
            'course_id' => $courseId,
            'corequisite_course_id' => $validated['corequisiteCourseId'],
        ]);

        return response()->json(['corequisite' => $corequisite], 201);
    }

    // DELETE /api/courses/{courseId}/corequisites/{corequisiteRelationId}
    public function removeCorequisite($courseId, $corequisiteRelationId)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') return response()->json(['error' => 'Chairperson access required'], 403);

        $corequisite = Corequisite::where('course_id', $courseId)->where('id', $corequisiteRelationId)->first();
        if (!$corequisite) return response()->json(['error' => 'Corequisite not found'], 404);

        $corequisite->delete();
        return response()->json(['message' => 'Corequisite removed']);
    }
}