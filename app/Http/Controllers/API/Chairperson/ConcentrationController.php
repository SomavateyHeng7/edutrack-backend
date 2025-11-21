<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Models\Concentration;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

class ConcentrationController extends Controller
{
    public function index(): JsonResponse
    {
        $concentrations = Concentration::with(['department', 'createdBy'])->get();
        return response()->json($concentrations);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'department_id' => 'required|string|exists:departments,id',
            'created_by_id' => 'required|string|exists:users,id',
        ]);

        $concentration = Concentration::create($validated);
        return response()->json($concentration, 201);
    }

    public function show(Concentration $concentration): JsonResponse
    {
        $concentration->load(['department', 'createdBy', 'courses.course']);
        return response()->json($concentration);
    }

    public function update(Request $request, Concentration $concentration): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'string|max:255',
            'description' => 'nullable|string',
            'department_id' => 'string|exists:departments,id',
        ]);

        $concentration->update($validated);
        return response()->json($concentration);
    }
    public function destroy(Concentration $concentration): JsonResponse
    {
        $concentration->delete();
        return response()->json(['message' => 'Concentration deleted successfully']);
    }
}