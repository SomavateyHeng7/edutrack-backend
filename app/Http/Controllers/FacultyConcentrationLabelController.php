<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Faculty;

class FacultyConcentrationLabelController extends Controller
{
    // GET /api/faculty/concentration-label
    public function show(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->faculty_id) {
            return response()->json([
                'error' => 'User not authenticated or no faculty associated'
            ], 401);
        }

        $faculty = Faculty::find($user->faculty_id);

        if (!$faculty) {
            return response()->json([
                'error' => 'Faculty not found'
            ], 404);
        }

        return response()->json([
            'concentrationLabel' => $faculty->concentrationLabel ?? 'Concentrations'
        ]);
    }

    // PUT /api/faculty/concentration-label
    public function update(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->faculty_id) {
            return response()->json([
                'error' => 'User not authenticated or no faculty associated'
            ], 401);
        }

        $label = $request->input('label');

        if (!is_string($label) || trim($label) === '') {
            return response()->json([
                'error' => 'Label is required and must be a non-empty string'
            ], 400);
        }

        if (mb_strlen(trim($label)) > 50) {
            return response()->json([
                'error' => 'Label must be 50 characters or less'
            ], 400);
        }

        $faculty = Faculty::find($user->faculty_id);

        if (!$faculty) {
            return response()->json([
                'error' => 'Faculty not found'
            ], 404);
        }

        $faculty->concentrationLabel = trim($label);
        $faculty->save();

        return response()->json([
            'concentrationLabel' => $faculty->concentrationLabel
        ]);
    }
}