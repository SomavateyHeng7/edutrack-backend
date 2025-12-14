<?php
namespace App\Http\Controllers\API\Chairperson;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FacultyLabelController extends Controller
{
    // GET /api/faculty/concentration-label
    public function getConcentrationLabel(Request $request)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => ['code' => 'FORBIDDEN', 'message' => 'Chairperson access required']], 403);
        }

        $faculty = $user->faculty;
        if (!$faculty) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Faculty not found']], 404);
        }

        return response()->json([
            'facultyId' => $faculty->id,
            'facultyName' => $faculty->name,
            'concentrationLabel' => $faculty->concentration_label ?? 'Concentrations'
        ]);
    }

    // PUT /api/faculty/concentration-label
    public function updateConcentrationLabel(Request $request)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'CHAIRPERSON') {
            return response()->json(['error' => ['code' => 'FORBIDDEN', 'message' => 'Chairperson access required']], 403);
        }

        $faculty = $user->faculty;
        if (!$faculty) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Faculty not found']], 404);
        }

        $validated = $request->validate([
            'label' => 'required|string|max:255',
        ]);

        $faculty->concentration_label = $validated['label'];
        $faculty->save();

        return response()->json([
            'success' => true,
            'faculty' => [
                'id' => $faculty->id,
                'name' => $faculty->name,
                'concentrationLabel' => $faculty->concentration_label
            ],
            'message' => 'Concentration label updated successfully'
        ]);
    }
}
