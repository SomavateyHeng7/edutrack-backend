<?php

namespace App\Http\Controllers;


use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CourseController extends Controller
{
    public function index(): JsonResponse
    {
        $courses = Course::with(['prerequisites', 'corequisites'])->get();
        return response()->json($courses);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:courses,code|max:255',
            'name' => 'required|string|max:255',
            'credits' => 'required|integer|min:0',
            'credit_hours' => 'required|string|max:255',
            'description' => 'nullable|string',
            'requires_permission' => 'boolean',
            'summer_only' => 'boolean',
            'requires_senior_standing' => 'boolean',
            'min_credit_threshold' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $course = Course::create($validated);
        return response()->json($course, 201);
    }
     public function show(Course $course): JsonResponse
    {
        $course->load(['prerequisites', 'corequisites', 'curriculumCourses', 'departmentCourseTypes']);
        return response()->json($course);
    }

    public function update(Request $request, Course $course): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'string|unique:courses,code,' . $course->id . '|max:255',
            'name' => 'string|max:255',
            'credits' => 'integer|min:0',
            'credit_hours' => 'string|max:255',
            'description' => 'nullable|string',
            'requires_permission' => 'boolean',
            'summer_only' => 'boolean',
            'requires_senior_standing' => 'boolean',
            'min_credit_threshold' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $course->update($validated);
        return response()->json($course);
    }

    public function destroy(Course $course): JsonResponse
    {
        $course->delete();
        return response()->json(['message' => 'Course deleted successfully']);
    }
}