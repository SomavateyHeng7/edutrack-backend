<?php
namespace App\Http\Controllers\API\Chairperson;

use App\Http\Controllers\Controller;
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
        $concentrations = Concentration::with(['department', 'courses.course'])->orderBy('name')->get();
        return response()->json(['concentrations' => $concentrations]);
    }

    // POST /api/concentrations
    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        // Get user's faculty and departments
        $faculty = $user->faculty?->load('departments');
        if (!$faculty || $faculty->departments->isEmpty()) {
            return response()->json(['error' => 'User faculty or department not found'], 404);
        }

        $departmentIds = $faculty->departments->pluck('id')->toArray();

        $validated = $request->validate([
            'name' => 'required|string',
            'departmentId' => 'nullable|exists:departments,id',
            'description' => 'nullable|string',
        ]);

        // Auto-assign department if not provided
        $departmentId = $validated['departmentId'] ?? $departmentIds[0];

        // Validate departmentId is in user's allowed departments
        if (!in_array($departmentId, $departmentIds)) {
            return response()->json(['error' => 'Invalid department'], 403);
        }

        $concentration = Concentration::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'department_id' => $departmentId,
            'created_by_id' => $user->id,
        ]);

        // Load relationships
        $concentration->load('department', 'courses.course');

        return response()->json(['concentration' => $concentration], 201);
    }

    // GET /api/concentrations/{id}
    public function show($id)
    {
        $user = Auth::user();
        if (!$user || !in_array($user->role, ['CHAIRPERSON', 'SUPER_ADMIN'])) {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        $concentration = Concentration::with(['department', 'courses.course'])->find($id);
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