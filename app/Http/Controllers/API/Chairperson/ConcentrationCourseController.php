<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\{Concentration, ConcentrationCourse, Course, Faculty};

class ConcentrationCourseController extends Controller
{
    // GET /api/concentrations
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user || !in_array($user->role, ['CHAIRPERSON', 'SUPER_ADMIN'])) {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        $concentrations = Concentration::with('department')->orderBy('name')->get();
        return response()->json(['concentrations' => $concentrations]);
    }

    // POST /api/concentrations
    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        $validated = $request->validate([
            'name' => 'required|string',
            'departmentId' => 'required|exists:departments,id',
        ]);
        $concentration = Concentration::create($validated);
        return response()->json(['concentration' => $concentration], 201);
    }

    // GET /api/concentrations/{id}
    public function show($id)
    {
        $user = Auth::user();
        if (!$user || !in_array($user->role, ['CHAIRPERSON', 'SUPER_ADMIN'])) {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        $concentration = Concentration::with('department')->find($id);
        if (!$concentration) {
            return response()->json(['error' => 'Not found'], 404);
        }
        return response()->json(['concentration' => $concentration]);
    }

    // PUT /api/concentrations/{id}
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        $concentration = Concentration::find($id);
        if (!$concentration) {
            return response()->json(['error' => 'Not found'], 404);
        }
        $concentration->update($request->only(['name', 'departmentId']));
        return response()->json(['concentration' => $concentration]);
    }

    // DELETE /api/concentrations/{id}
    public function destroy($id)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        $concentration = Concentration::find($id);
        if (!$concentration) {
            return response()->json(['error' => 'Not found'], 404);
        }
        $concentration->delete();
        return response()->json(['message' => 'Deleted']);
    }

    // GET /api/concentrations/{id}/courses
    public function coursesIndex(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => 'Chairperson access required'], 403);
        }
        $faculty = Faculty::with('departments')->find($user->facultyId);
        $accessibleDepartmentIds = $faculty ? $faculty->departments->pluck('id')->toArray() : [];
        $concentration = Concentration::where('id', $id)
            ->whereIn('departmentId', $accessibleDepartmentIds)
            ->first();
        if (!$concentration) {
            return response()->json(['error' => 'Concentration not found'], 404);
        }
        $concentrationCourses = ConcentrationCourse::with('course')
            ->where('concentrationId', $id)
            ->orderBy(Course::select('code')->whereColumn('courses.id', 'concentration_courses.course_id'))
            ->get();
        $courses = $concentrationCourses->map(function ($cc) {
            return [
                'id' => $cc->course->id,
                'code' => $cc->course->code,
                'name' => $cc->course->name,
                'credits' => $cc->course->credits,
                'creditHours' => $cc->course->creditHours,
                'description' => $cc->course->description,
            ];
        });
        return response()->json([
            'concentrationId' => $id,
            'concentrationName' => $concentration->name,
            'courses' => $courses,
            'totalCourses' => $courses->count(),
        ]);
    }

    // POST /api/concentrations/{id}/courses
    public function coursesStore(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => 'Chairperson access required'], 403);
        }
        $faculty = Faculty::with('departments')->find($user->facultyId);
        $accessibleDepartmentIds = $faculty ? $faculty->departments->pluck('id')->toArray() : [];
        $concentration = Concentration::where('id', $id)
            ->whereIn('departmentId', $accessibleDepartmentIds)
            ->first();
        if (!$concentration) {
            return response()->json(['error' => 'Concentration not found'], 404);
        }
        $courses = $request->input('courses');
        if (!is_array($courses)) {
            return response()->json(['error' => 'Courses must be an array'], 400);
        }
        $results = ['added' => 0, 'skipped' => 0, 'errors' => []];
        foreach ($courses as $courseData) {
            $code = $courseData['code'] ?? null;
            $name = $courseData['name'] ?? null;
            if (!$code || !$name) {
                $results['errors'][] = "Course missing required fields: " . json_encode($courseData);
                continue;
            }
            $course = Course::firstOrCreate(
                ['code' => trim($code)],
                [
                    'name' => trim($name),
                    'credits' => $courseData['credits'] ?? 3,
                    'creditHours' => $courseData['creditHours'] ?? "3-0-3",
                    'description' => $courseData['description'] ?? null,
                ]
            );
            $exists = ConcentrationCourse::where('concentrationId', $id)
                ->where('courseId', $course->id)
                ->exists();
            if ($exists) {
                $results['skipped']++;
            } else {
                ConcentrationCourse::create([
                    'concentrationId' => $id,
                    'courseId' => $course->id,
                ]);
                $results['added']++;
            }
        }
        return response()->json([
            'message' => 'Courses upload completed',
            'results' => $results,
        ]);
    }

    // DELETE /api/concentrations/{id}/courses?courseId=xxx
    public function coursesDestroy(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => 'Chairperson access required'], 403);
        }
        $faculty = Faculty::with('departments')->find($user->facultyId);
        $accessibleDepartmentIds = $faculty ? $faculty->departments->pluck('id')->toArray() : [];
        $concentration = Concentration::where('id', $id)
            ->whereIn('departmentId', $accessibleDepartmentIds)
            ->first();
        if (!$concentration) {
            return response()->json(['error' => 'Concentration not found'], 404);
        }
        $courseId = $request->query('courseId');
        if (!$courseId) {
            return response()->json(['error' => 'Course ID is required'], 400);
        }
        $deleted = ConcentrationCourse::where('concentrationId', $id)
            ->where('courseId', $courseId)
            ->delete();
        if ($deleted === 0) {
            return response()->json(['error' => 'Course not found in concentration'], 404);
        }
        return response()->json(['message' => 'Course removed from concentration successfully']);
    }
}