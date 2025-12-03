<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\{CourseType, DepartmentCourseType, Course, Department};

class CourseTypeController extends Controller
{
    // GET /api/course-types
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user || !in_array($user->role, ['CHAIRPERSON', 'SUPER_ADMIN'])) {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        $courseTypes = CourseType::orderBy('name')->get();
        return response()->json(['courseTypes' => $courseTypes]);
    }

    // POST /api/course-types
    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'SUPER_ADMIN') {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        $validated = $request->validate([
            'name' => 'required|string|unique:course_types,name',
            'description' => 'nullable|string',
        ]);
        $courseType = CourseType::create($validated);
        return response()->json(['courseType' => $courseType], 201);
    }

    // GET /api/course-types/{id}
    public function show($id)
    {
        $user = Auth::user();
        if (!$user || !in_array($user->role, ['CHAIRPERSON', 'SUPER_ADMIN'])) {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        $courseType = CourseType::find($id);
        if (!$courseType) {
            return response()->json(['error' => 'Not found'], 404);
        }
        return response()->json(['courseType' => $courseType]);
    }

    // PUT /api/course-types/{id}
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'SUPER_ADMIN') {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        $courseType = CourseType::find($id);
        if (!$courseType) {
            return response()->json(['error' => 'Not found'], 404);
        }
        $validated = $request->validate([
            'name' => 'required|string|unique:course_types,name,' . $id,
            'description' => 'nullable|string',
        ]);
        $courseType->update($validated);
        return response()->json(['courseType' => $courseType]);
    }

    // DELETE /api/course-types/{id}
    public function destroy($id)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'SUPER_ADMIN') {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        $courseType = CourseType::find($id);
        if (!$courseType) {
            return response()->json(['error' => 'Not found'], 404);
        }
        $courseType->delete();
        return response()->json(['message' => 'Deleted']);
    }

    // POST /api/course-types/assign
    public function assign(Request $request)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        $validated = $request->validate([
            'courseId' => 'required|exists:courses,id',
            'departmentId' => 'required|exists:departments,id',
            'courseTypeId' => 'required|exists:course_types,id',
        ]);

        // Assign course type to course for department
        $assignment = DepartmentCourseType::updateOrCreate(
            [
                'course_id' => $validated['courseId'],
                'department_id' => $validated['departmentId'],
            ],
            [
                'course_type_id' => $validated['courseTypeId'],
            ]
        );

        return response()->json(['assignment' => $assignment]);
    }
}