<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DepartmentController extends Controller
{
    public function index(): JsonResponse
    {
        $departments = Department::with(['faculty'])->get();
        return response()->json($departments);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255',
            'faculty_id' => 'required|string|exists:faculties,id',
        ]);

        $department = Department::create($validated);
        return response()->json($department, 201);
    }

    public function show(Department $department): JsonResponse
    {
        $department->load(['faculty', 'users', 'curricula', 'courseTypes', 'concentrations', 'blacklists']);
        return response()->json($department);
    }
    public function update(Request $request, Department $department): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'string|max:255',
            'code' => 'string|max:255',
            'faculty_id' => 'string|exists:faculties,id',
        ]);

        $department->update($validated);
        return response()->json($department);
    }

    public function destroy(Department $department): JsonResponse
    {
        $department->delete();
        return response()->json(['message' => 'Department deleted successfully']);
    }
}