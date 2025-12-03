<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Faculty;

class PublicFacultyController extends Controller
{
    // GET /api/public-faculties
    public function index(Request $request)
    {
        try {
            // Fetch all faculties ordered by name (public endpoint)
            $faculties = Faculty::orderBy('name', 'asc')->get();

            return response()->json(['faculties' => $faculties]);
        } catch (\Exception $error) {
            return response()->json([
                'error' => 'Failed to fetch faculties',
                'details' => [
                    'message' => $error->getMessage(),
                    'stack' => $error->getTraceAsString(),
                    'name' => get_class($error)
                ]
            ], 500);
        }
    }
}