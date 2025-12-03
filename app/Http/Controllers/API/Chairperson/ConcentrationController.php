<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Concentration;

class ConcentrationController extends Controller
{
    // GET /api/concentrations
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user || !in_array($user->role, ['CHAIRPERSON', 'SUPER_ADMIN'])) {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        // Fetch accessible concentrations (add department/faculty logic as needed)
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
            'description' => 'nullable|string',
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
        $concentration->update($request->only(['name', 'departmentId', 'description']));
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
}