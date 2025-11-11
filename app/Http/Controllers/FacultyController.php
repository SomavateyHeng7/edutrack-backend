<?php

namespace App\Http\Controllers;

use App\Models\Faculty;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class FacultyController extends Controller
{
    public function index(): JsonResponse
    {
        $faculties = Faculty::with(['departments'])->get();
        return response()->json($faculties);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:faculties,code|max:255',
            'concentration_label' => 'string|max:255',
        ]);

        $faculty = Faculty::create($validated);
        return response()->json($faculty, 201);
    }

    public function show(Faculty $faculty): JsonResponse
    {
        $faculty->load(['departments', 'users', 'curricula']);
        return response()->json($faculty);
    }
    public function update(Request $request, Faculty $faculty): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'string|max:255',
            'code' => 'string|unique:faculties,code,' . $faculty->id . '|max:255',
            'concentration_label' => 'string|max:255',
        ]);

        $faculty->update($validated);
        return response()->json($faculty);
    }

    public function destroy(Faculty $faculty): JsonResponse
    {
        $faculty->delete();
        return response()->json(['message' => 'Faculty deleted successfully']);
    }
}