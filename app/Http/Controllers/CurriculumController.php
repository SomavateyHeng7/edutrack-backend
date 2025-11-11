<?php

namespace App\Http\Controllers;

use App\Models\Curriculum;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CurriculumController extends Controller
{
    public function index(): JsonResponse
    {
        $curricula = Curriculum::with(['department', 'faculty', 'createdBy'])->get();
        return response()->json($curricula);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'year' => 'required|string|max:255',
            'version' => 'string|max:255',
            'description' => 'nullable|string',
            'start_id' => 'required|string|max:255',
            'end_id' => 'required|string|max:255',
            'is_active' => 'boolean',
            'department_id' => 'required|string|exists:departments,id',
            'faculty_id' => 'required|string|exists:faculties,id',
            'created_by_id' => 'required|string|exists:users,id',
        ]);

        $curriculum = Curriculum::create($validated);
        return response()->json($curriculum, 201);
    }
     public function show(Curriculum $curriculum): JsonResponse
    {
        $curriculum->load([
            'department', 
            'faculty', 
            'createdBy', 
            'curriculumCourses.course',
            'electiveRules',
            'curriculumConcentrations.concentration',
            'curriculumBlacklists.blacklist',
            'curriculumConstraints'
        ]);
        return response()->json($curriculum);
    }

    public function update(Request $request, Curriculum $curriculum): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'string|max:255',
            'year' => 'string|max:255',
            'version' => 'string|max:255',
            'description' => 'nullable|string',
            'start_id' => 'string|max:255',
            'end_id' => 'string|max:255',
            'is_active' => 'boolean',
            'department_id' => 'string|exists:departments,id',
            'faculty_id' => 'string|exists:faculties,id',
        ]);
        $curriculum->update($validated);
        return response()->json($curriculum);
    }

    public function destroy(Curriculum $curriculum): JsonResponse
    {
        $curriculum->delete();
        return response()->json(['message' => 'Curriculum deleted successfully']);
    }
}