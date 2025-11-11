<?php

namespace App\Http\Controllers;
use App\Models\Blacklist;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BlacklistController extends Controller
{
    public function index(): JsonResponse
    {
        $blacklists = Blacklist::with(['department', 'createdBy'])->get();
        return response()->json($blacklists);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'department_id' => 'required|string|exists:departments,id',
            'created_by_id' => 'required|string|exists:users,id',
        ]);

        $blacklist = Blacklist::create($validated);
        return response()->json($blacklist, 201);
    }

    public function show(Blacklist $blacklist): JsonResponse
    {
        $blacklist->load(['department', 'createdBy', 'courses.course']);
        return response()->json($blacklist);
    }

    public function update(Request $request, Blacklist $blacklist): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'string|max:255',
            'description' => 'nullable|string',
            'department_id' => 'string|exists:departments,id',
        ]);
        $blacklist->update($validated);
        return response()->json($blacklist);
    }

    public function destroy(Blacklist $blacklist): JsonResponse
    {
        $blacklist->delete();
        return response()->json(['message' => 'Blacklist deleted successfully']);
    }
}