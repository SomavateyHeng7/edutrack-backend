<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\{
    Curriculum,
    CurriculumCourse,
    Course,
    Prerequisite,
    Corequisite,
    Concentration,
    Blacklist,
    Constraint,
    ElectiveRule
};

class CurriculumController extends Controller
{
    // GET /api/curriculum
    public function index(Request $request)
    {
        $curricula = Curriculum::with(['department:id,name,code'])->orderBy('name')->get();
        return response()->json(['curricula' => $curricula]);
    }

    // POST /api/curriculum
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

    // GET /api/curriculum/{id}
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

    // PUT /api/curriculum/{id}
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

    // DELETE /api/curriculum/{id}
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

    // GET /api/curriculum/{id}/courses
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

    // POST /api/curriculum/{id}/courses
    public function addCourse(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => 'Chairperson access required'], 403);
        }

        $validated = $request->validate([
            'courseId' => 'required|exists:courses,id',
        ]);

        $curriculumCourse = CurriculumCourse::create([
            'curriculum_id' => $id,
            'course_id' => $validated['courseId'],
        ]);

        return response()->json(['curriculumCourse' => $curriculumCourse], 201);
    }

    // DELETE /api/curriculum/{id}/courses/{courseId}
    public function removeCourse($id, $courseId)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => 'Chairperson access required'], 403);
        }

        $curriculumCourse = CurriculumCourse::where('curriculum_id', $id)
            ->where('course_id', $courseId)
            ->first();

        if (!$curriculumCourse) {
            return response()->json(['error' => 'Course not found in curriculum'], 404);
        }

        $curriculumCourse->delete();
        return response()->json(['message' => 'Course removed from curriculum']);
    }

    // GET /api/curriculum/{id}/concentrations
    public function concentrations($id)
    {
        $curriculum = Curriculum::find($id);
        if (!$curriculum) {
            return response()->json(['error' => 'Curriculum not found'], 404);
        }

        $concentrations = $curriculum->concentrations()->with('courses.course')->get();

        return response()->json(['concentrations' => $concentrations]);
    }

    // GET /api/curriculum/{id}/blacklists
    public function blacklists($id)
    {
        $curriculum = Curriculum::find($id);
        if (!$curriculum) {
            return response()->json(['error' => 'Curriculum not found'], 404);
        }

        $blacklists = $curriculum->blacklists()->with('course')->get();

        return response()->json(['blacklists' => $blacklists]);
    }

    // GET /api/curriculum/{id}/constraints
    public function constraints($id)
    {
        $curriculum = Curriculum::find($id);
        if (!$curriculum) {
            return response()->json(['error' => 'Curriculum not found'], 404);
        }

        $constraints = $curriculum->constraints()->with('course')->get();

        return response()->json(['constraints' => $constraints]);
    }

    // GET /api/curriculum/{id}/elective-rules
    public function electiveRules($id)
    {
        $curriculum = Curriculum::find($id);
        if (!$curriculum) {
            return response()->json(['error' => 'Curriculum not found'], 404);
        }

        $electiveRules = $curriculum->electiveRules()->get();

        return response()->json(['electiveRules' => $electiveRules]);
    }

    // GET /api/curriculum/{id}/courses/{courseId}/prerequisites
    public function prerequisites($id, $courseId)
    {
        $prerequisites = Prerequisite::where('curriculum_id', $id)
            ->where('course_id', $courseId)
            ->with('prerequisiteCourse')
            ->get();

        return response()->json(['prerequisites' => $prerequisites]);
    }

    // POST /api/curriculum/{id}/courses/{courseId}/prerequisites
    public function addPrerequisite(Request $request, $id, $courseId)
    {
        $validated = $request->validate([
            'prerequisiteCourseId' => 'required|exists:courses,id',
        ]);

        $prerequisite = Prerequisite::create([
            'curriculum_id' => $id,
            'course_id' => $courseId,
            'prerequisite_course_id' => $validated['prerequisiteCourseId'],
        ]);

        return response()->json(['prerequisite' => $prerequisite], 201);
    }

    // DELETE /api/curriculum/{id}/courses/{courseId}/prerequisites/{prerequisiteId}
    public function removePrerequisite($id, $courseId, $prerequisiteId)
    {
        $prerequisite = Prerequisite::where('curriculum_id', $id)
            ->where('course_id', $courseId)
            ->where('id', $prerequisiteId)
            ->first();

        if (!$prerequisite) {
            return response()->json(['error' => 'Prerequisite not found'], 404);
        }

        $prerequisite->delete();
        return response()->json(['message' => 'Prerequisite removed']);
    }

    // GET /api/curriculum/{id}/courses/{courseId}/corequisites
    public function corequisites($id, $courseId)
    {
        $corequisites = Corequisite::where('curriculum_id', $id)
            ->where('course_id', $courseId)
            ->with('corequisiteCourse')
            ->get();

        return response()->json(['corequisites' => $corequisites]);
    }

    // POST /api/curriculum/{id}/courses/{courseId}/corequisites
    public function addCorequisite(Request $request, $id, $courseId)
    {
        $validated = $request->validate([
            'corequisiteCourseId' => 'required|exists:courses,id',
        ]);

        $corequisite = Corequisite::create([
            'curriculum_id' => $id,
            'course_id' => $courseId,
            'corequisite_course_id' => $validated['corequisiteCourseId'],
        ]);

        return response()->json(['corequisite' => $corequisite], 201);
    }

    // DELETE /api/curriculum/{id}/courses/{courseId}/corequisites/{corequisiteId}
    public function removeCorequisite($id, $courseId, $corequisiteId)
    {
        $corequisite = Corequisite::where('curriculum_id', $id)
            ->where('course_id', $courseId)
            ->where('id', $corequisiteId)
            ->first();

        if (!$corequisite) {
            return response()->json(['error' => 'Corequisite not found'], 404);
        }

        $corequisite->delete();
        return response()->json(['message' => 'Corequisite removed']);
    }

    // GET /api/curriculum/bscs2022
    public function bscs2022()
    {
        $curriculum = Curriculum::where('code', 'BSCS2022')
            ->with(['courses.course', 'department:id,name,code'])
            ->first();

        if (!$curriculum) {
            return response()->json(['error' => 'BSCS2022 curriculum not found'], 404);
        }

        return response()->json(['curriculum' => $curriculum]);
    }

    // GET /api/curriculum/template
    public function template()
    {
        $template = [
            'name' => 'Curriculum Template',
            'fields' => [
                'name', 'departmentId', 'description', 'courses', 'concentrations'
            ]
        ];

        return response()->json(['template' => $template]);
    }

    // POST /api/curriculum/upload
    public function upload(Request $request)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => 'Chairperson access required'], 403);
        }

        if (!$request->hasFile('file')) {
            return response()->json(['error' => 'No file uploaded'], 400);
        }

        $file = $request->file('file');
        // Process file (CSV/XLSX parsing logic goes here)
        return response()->json([
            'filename' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime' => $file->getMimeType()
        ]);
    }
}