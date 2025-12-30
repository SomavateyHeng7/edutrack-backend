<?php

namespace App\Http\Controllers\API\Chairperson;

use App\Http\Controllers\Controller;
use App\Models\Curriculum;
use App\Models\CurriculumConstraint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CurriculumConstraintsController extends Controller
{
    /**
     * List all constraints for a curriculum
     * 
     * GET /api/curricula/{id}/constraints
     */
    public function index($curriculumId)
    {
        $curriculum = Curriculum::findOrFail($curriculumId);
        
        $constraints = $curriculum->curriculumConstraints()
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'constraints' => $constraints,
        ]);
    }

    /**
     * Create a new curriculum constraint
     * 
     * POST /api/curricula/{id}/constraints
     * 
     * Body:
     * {
     *   "type": "CUSTOM",
     *   "name": "Banned: CSX 1001 + CSX 2005",
     *   "description": "Students cannot take CSX 1001 and CSX 2005 together",
     *   "isRequired": true,
     *   "config": {
     *     "type": "banned_combination",
     *     "courses": [
     *       {"id": "course-id-1", "code": "CSX 1001", "name": "Course Name 1"},
     *       {"id": "course-id-2", "code": "CSX 2005", "name": "Course Name 2"}
     *     ]
     *   }
     * }
     */
    public function store(Request $request, $curriculumId)
    {
        $curriculum = Curriculum::findOrFail($curriculumId);

        $validator = Validator::make($request->all(), [
            'type' => 'required|in:MINIMUM_GPA,SENIOR_STANDING,TOTAL_CREDITS,CATEGORY_CREDITS,CUSTOM',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'isRequired' => 'boolean',
            'config' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Check for duplicate constraint name within the same curriculum
        $existingConstraint = $curriculum->curriculumConstraints()
            ->where('type', $request->type)
            ->where('name', $request->name)
            ->first();

        if ($existingConstraint) {
            return response()->json([
                'success' => false,
                'message' => 'A constraint with this type and name already exists for this curriculum',
            ], 409);
        }

        $constraint = new CurriculumConstraint([
            'curriculum_id' => $curriculumId,
            'type' => $request->type,
            'name' => $request->name,
            'description' => $request->description,
            'is_required' => $request->input('isRequired', true),
            'config' => $request->config,
        ]);

        $constraint->save();

        return response()->json([
            'success' => true,
            'message' => 'Constraint created successfully',
            'constraint' => $constraint,
        ], 201);
    }

    /**
     * Delete a curriculum constraint
     * 
     * DELETE /api/curricula/{id}/constraints/{constraintId}
     */
    public function destroy($curriculumId, $constraintId)
    {
        $curriculum = Curriculum::findOrFail($curriculumId);
        
        $constraint = $curriculum->curriculumConstraints()
            ->where('id', $constraintId)
            ->firstOrFail();

        $constraint->delete();

        return response()->json([
            'success' => true,
            'message' => 'Constraint deleted successfully',
        ]);
    }
}
