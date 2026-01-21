<?php

namespace App\Http\Controllers\API\Chairperson;

use App\Http\Controllers\Controller;
use App\Models\GraduationPortal;
use App\Models\GraduationSubmission;
use App\Models\Curriculum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GraduationPortalController extends Controller
{
    /**
     * Get list of graduation portals
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            
            // Build query
            $query = GraduationPortal::with(['curriculum', 'submissions']);
            
            // Filter by chairperson's department
            if ($user->role === 'CHAIRPERSON' && $user->department_id) {
                $query->where('department_id', $user->department_id);
            }
            
            // Search filter
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('batch', 'like', "%{$search}%")
                      ->orWhere('curriculum', 'like', "%{$search}%");
                });
            }
            
            // Status filter
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
            
            // Pagination
            $perPage = $request->get('limit', 20);
            $portals = $query->orderBy('created_at', 'desc')->paginate($perPage);
            
            // Format portal data
            $formattedPortals = $portals->map(function ($portal) {
                return $this->formatPortalResponse($portal);
            });
            
            return response()->json([
                'portals' => $formattedPortals,
                'pagination' => [
                    'total' => $portals->total(),
                    'page' => $portals->currentPage(),
                    'limit' => $portals->perPage(),
                    'totalPages' => $portals->lastPage(),
                ],
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error fetching graduation portals: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => [
                    'message' => 'Failed to fetch graduation portals',
                    'details' => $e->getMessage()
                ]
            ], 500);
        }
    }
    
    /**
     * Create a new graduation portal
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'batch' => 'nullable|string|max:100',
                'curriculum' => 'nullable|string|max:255',
                'curriculumId' => 'nullable|exists:curricula,id',
                'deadline' => 'required|date',
                'status' => 'nullable|in:active,closed',
                'acceptedFormats' => 'nullable|array',
            ]);
            
            $user = $request->user();
            
            DB::beginTransaction();
            
            // Generate unique PIN
            $pin = $this->generateUniquePin();
            
            // Create portal
            $portal = GraduationPortal::create([
                'name' => $request->name,
                'description' => $request->description,
                'batch' => $request->batch,
                'curriculum' => $request->curriculum,
                'curriculum_id' => $request->curriculumId,
                'deadline' => $request->deadline,
                'status' => $request->status ?? 'active',
                'pin' => $pin,
                'accepted_formats' => $request->acceptedFormats ?? ['.xlsx', '.xls', '.csv'],
                'created_by' => $user->id,
                'department_id' => $user->department_id,
            ]);
            
            DB::commit();
            
            // Load relationships
            $portal->load(['curriculum', 'submissions']);
            
            return response()->json([
                'message' => 'Graduation portal created successfully',
                'portal' => $this->formatPortalResponse($portal),
            ], 201);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'error' => [
                    'message' => 'Validation error',
                    'details' => $e->errors()
                ]
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating graduation portal: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => [
                    'message' => 'Failed to create graduation portal',
                    'details' => $e->getMessage()
                ]
            ], 500);
        }
    }
    
    /**
     * Get a specific graduation portal
     */
    public function show(Request $request, $id)
    {
        try {
            $portal = GraduationPortal::with(['curriculum', 'submissions.student'])
                ->findOrFail($id);
            
            // Verify access
            $user = $request->user();
            if ($user->role === 'CHAIRPERSON' && 
                $user->department_id !== $portal->department_id) {
                return response()->json([
                    'error' => ['message' => 'Access denied']
                ], 403);
            }
            
            return response()->json([
                'portal' => $this->formatPortalResponse($portal, true),
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => ['message' => 'Portal not found']
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error fetching portal: ' . $e->getMessage(), [
                'portalId' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => [
                    'message' => 'Failed to fetch portal',
                    'details' => $e->getMessage()
                ]
            ], 500);
        }
    }
    
    /**
     * Update a graduation portal
     */
    public function update(Request $request, $id)
    {
        try {
            $portal = GraduationPortal::findOrFail($id);
            
            // Verify access
            $user = $request->user();
            if ($user->role === 'CHAIRPERSON' && 
                $user->department_id !== $portal->department_id) {
                return response()->json([
                    'error' => ['message' => 'Access denied']
                ], 403);
            }
            
            $request->validate([
                'name' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'batch' => 'nullable|string|max:100',
                'curriculum' => 'nullable|string|max:255',
                'curriculumId' => 'nullable|exists:curricula,id',
                'deadline' => 'sometimes|date',
                'status' => 'sometimes|in:active,closed',
                'acceptedFormats' => 'nullable|array',
            ]);
            
            DB::beginTransaction();
            
            $portal->update($request->only([
                'name',
                'description',
                'batch',
                'curriculum',
                'curriculum_id',
                'deadline',
                'status',
                'accepted_formats',
            ]));
            
            DB::commit();
            
            // Load relationships
            $portal->load(['curriculum', 'submissions']);
            
            return response()->json([
                'message' => 'Portal updated successfully',
                'portal' => $this->formatPortalResponse($portal),
            ]);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'error' => [
                    'message' => 'Validation error',
                    'details' => $e->errors()
                ]
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'error' => ['message' => 'Portal not found']
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating portal: ' . $e->getMessage(), [
                'portalId' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => [
                    'message' => 'Failed to update portal',
                    'details' => $e->getMessage()
                ]
            ], 500);
        }
    }
    
    /**
     * Delete a graduation portal
     */
    public function destroy(Request $request, $id)
    {
        try {
            $portal = GraduationPortal::findOrFail($id);
            
            // Verify access
            $user = $request->user();
            if ($user->role === 'CHAIRPERSON' && 
                $user->department_id !== $portal->department_id) {
                return response()->json([
                    'error' => ['message' => 'Access denied']
                ], 403);
            }
            
            DB::beginTransaction();
            
            $portal->delete();
            
            DB::commit();
            
            return response()->json([
                'message' => 'Portal deleted successfully',
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'error' => ['message' => 'Portal not found']
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting portal: ' . $e->getMessage(), [
                'portalId' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => [
                    'message' => 'Failed to delete portal',
                    'details' => $e->getMessage()
                ]
            ], 500);
        }
    }
    
    /**
     * Get submissions for a specific portal
     */
    public function submissions(Request $request, $id)
    {
        try {
            $portal = GraduationPortal::findOrFail($id);
            
            // Verify access
            $user = $request->user();
            if ($user->role === 'CHAIRPERSON' && 
                $user->department_id !== $portal->department_id) {
                return response()->json([
                    'error' => ['message' => 'Access denied']
                ], 403);
            }
            
            // Build query
            $query = GraduationSubmission::where('portal_id', $id)
                ->with(['student', 'reviewer']);
            
            // Status filter
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }
            
            // Search filter
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('file_name', 'like', "%{$search}%")
                      ->orWhereHas('student', function($sq) use ($search) {
                          $sq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('student_id', 'like', "%{$search}%");
                      });
                });
            }
            
            $submissions = $query->orderBy('created_at', 'desc')->get();
            
            // Format submissions
            $formattedSubmissions = $submissions->map(function ($submission) {
                return $this->formatSubmissionResponse($submission);
            });
            
            return response()->json([
                'submissions' => $formattedSubmissions,
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => ['message' => 'Portal not found']
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error fetching submissions: ' . $e->getMessage(), [
                'portalId' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => [
                    'message' => 'Failed to fetch submissions',
                    'details' => $e->getMessage()
                ]
            ], 500);
        }
    }
    
    /**
     * Process/validate a specific submission
     */
    public function processSubmission(Request $request, $portalId, $submissionId)
    {
        try {
            $submission = GraduationSubmission::findOrFail($submissionId);
            
            // Verify submission belongs to portal
            if ($submission->portal_id != $portalId) {
                return response()->json([
                    'error' => ['message' => 'Submission does not belong to this portal']
                ], 400);
            }
            
            // Verify access
            $user = $request->user();
            $portal = $submission->portal;
            if ($user->role === 'CHAIRPERSON' && 
                $user->department_id !== $portal->department_id) {
                return response()->json([
                    'error' => ['message' => 'Access denied']
                ], 403);
            }
            
            DB::beginTransaction();
            
            // Update status to processing
            $submission->update([
                'status' => 'processing',
            ]);
            
            DB::commit();
            
            // TODO: Implement actual validation logic here
            // For now, return a mock validation result
            $validationResult = [
                'canGraduate' => rand(0, 1) === 1,
                'totalCredits' => rand(130, 150),
                'requiredCredits' => 140,
                'completedCourses' => rand(40, 50),
                'totalCourses' => 50,
                'missingCourses' => ['CS 499', 'CS 498'],
                'issues' => [],
                'warnings' => [],
            ];
            
            DB::beginTransaction();
            
            $submission->update([
                'status' => $validationResult['canGraduate'] ? 'validated' : 'has_issues',
                'validation_result' => $validationResult,
            ]);
            
            DB::commit();
            
            return response()->json([
                'message' => 'Submission processed successfully',
                'submission' => $this->formatSubmissionResponse($submission->fresh()),
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'error' => ['message' => 'Submission not found']
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error processing submission: ' . $e->getMessage(), [
                'submissionId' => $submissionId,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => [
                    'message' => 'Failed to process submission',
                    'details' => $e->getMessage()
                ]
            ], 500);
        }
    }
    
    /**
     * Approve a submission
     */
    public function approveSubmission(Request $request, $portalId, $submissionId)
    {
        try {
            $submission = GraduationSubmission::findOrFail($submissionId);
            
            // Verify submission belongs to portal
            if ($submission->portal_id != $portalId) {
                return response()->json([
                    'error' => ['message' => 'Submission does not belong to this portal']
                ], 400);
            }
            
            // Verify access
            $user = $request->user();
            $portal = $submission->portal;
            if ($user->role === 'CHAIRPERSON' && 
                $user->department_id !== $portal->department_id) {
                return response()->json([
                    'error' => ['message' => 'Access denied']
                ], 403);
            }
            
            $request->validate([
                'notes' => 'nullable|string',
            ]);
            
            DB::beginTransaction();
            
            $submission->update([
                'status' => 'approved',
                'notes' => $request->notes,
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
            ]);
            
            DB::commit();
            
            return response()->json([
                'message' => 'Submission approved successfully',
                'submission' => $this->formatSubmissionResponse($submission->fresh()),
            ]);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'error' => [
                    'message' => 'Validation error',
                    'details' => $e->errors()
                ]
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'error' => ['message' => 'Submission not found']
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error approving submission: ' . $e->getMessage(), [
                'submissionId' => $submissionId,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => [
                    'message' => 'Failed to approve submission',
                    'details' => $e->getMessage()
                ]
            ], 500);
        }
    }
    
    /**
     * Reject a submission
     */
    public function rejectSubmission(Request $request, $portalId, $submissionId)
    {
        try {
            $submission = GraduationSubmission::findOrFail($submissionId);
            
            // Verify submission belongs to portal
            if ($submission->portal_id != $portalId) {
                return response()->json([
                    'error' => ['message' => 'Submission does not belong to this portal']
                ], 400);
            }
            
            // Verify access
            $user = $request->user();
            $portal = $submission->portal;
            if ($user->role === 'CHAIRPERSON' && 
                $user->department_id !== $portal->department_id) {
                return response()->json([
                    'error' => ['message' => 'Access denied']
                ], 403);
            }
            
            $request->validate([
                'notes' => 'required|string',
            ]);
            
            DB::beginTransaction();
            
            $submission->update([
                'status' => 'rejected',
                'notes' => $request->notes,
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
            ]);
            
            DB::commit();
            
            return response()->json([
                'message' => 'Submission rejected successfully',
                'submission' => $this->formatSubmissionResponse($submission->fresh()),
            ]);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'error' => [
                    'message' => 'Validation error',
                    'details' => $e->errors()
                ]
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'error' => ['message' => 'Submission not found']
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error rejecting submission: ' . $e->getMessage(), [
                'submissionId' => $submissionId,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => [
                    'message' => 'Failed to reject submission',
                    'details' => $e->getMessage()
                ]
            ], 500);
        }
    }
    
    /**
     * Format portal response
     */
    private function formatPortalResponse($portal, $detailed = false)
    {
        $data = [
            'id' => (string) $portal->id,
            'name' => $portal->name,
            'description' => $portal->description,
            'batch' => $portal->batch,
            'curriculum' => $portal->curriculum,
            'curriculumId' => $portal->curriculum_id ? (string) $portal->curriculum_id : null,
            'deadline' => $portal->deadline->format('Y-m-d'),
            'status' => $portal->status,
            'pin' => $portal->pin,
            'acceptedFormats' => $portal->accepted_formats ?? ['.xlsx', '.xls', '.csv'],
            'submissionsCount' => $portal->submissions->count(),
            'createdAt' => $portal->created_at->toISOString(),
            'updatedAt' => $portal->updated_at->toISOString(),
        ];
        
        if ($portal->curriculum) {
            $data['curriculum'] = [
                'id' => (string) $portal->curriculum->id,
                'name' => $portal->curriculum->name,
                'year' => $portal->curriculum->year,
            ];
        }
        
        return $data;
    }
    
    /**
     * Format submission response
     */
    private function formatSubmissionResponse($submission)
    {
        $data = [
            'id' => (string) $submission->id,
            'portalId' => (string) $submission->portal_id,
            'studentId' => (string) $submission->student_id,
            'fileName' => $submission->file_name,
            'fileSize' => $submission->file_size,
            'submittedAt' => $submission->created_at->toISOString(),
            'status' => $submission->status,
            'notes' => $submission->notes,
        ];
        
        if ($submission->student) {
            $data['student'] = [
                'id' => (string) $submission->student->id,
                'name' => $submission->student->name,
                'email' => $submission->student->email,
                'studentId' => $submission->student->student_id,
            ];
        }
        
        if ($submission->validation_result) {
            $data['validationResult'] = $submission->validation_result;
        }
        
        if ($submission->reviewer) {
            $data['reviewer'] = [
                'id' => (string) $submission->reviewer->id,
                'name' => $submission->reviewer->name,
            ];
            $data['reviewedAt'] = $submission->reviewed_at->toISOString();
        }
        
        return $data;
    }
    
    /**
     * Generate unique PIN for portal
     */
    private function generateUniquePin()
    {
        do {
            $pin = 'GRAD' . strtoupper(Str::random(6));
        } while (GraduationPortal::where('pin', $pin)->exists());
        
        return $pin;
    }
    
    /**
     * Close a graduation portal
     */
    public function close(Request $request, $id)
    {
        try {
            $portal = GraduationPortal::findOrFail($id);
            
            // Verify access
            $user = $request->user();
            if ($user->role === 'CHAIRPERSON' && 
                $user->department_id !== $portal->department_id) {
                return response()->json([
                    'error' => ['message' => 'Access denied']
                ], 403);
            }
            
            $portal->close();
            
            // Log the action
            \App\Models\GraduationPortalLog::log(
                $portal->id,
                \App\Models\GraduationPortalLog::ACTION_CLOSED,
                $user->id,
                ['closed_at' => $portal->closed_at]
            );
            
            return response()->json([
                'message' => 'Portal closed successfully',
                'portal' => $this->formatPortalResponse($portal->fresh()),
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => ['message' => 'Portal not found']
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error closing portal: ' . $e->getMessage(), [
                'portalId' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => [
                    'message' => 'Failed to close portal',
                    'details' => $e->getMessage()
                ]
            ], 500);
        }
    }
    
    /**
     * Regenerate PIN for a graduation portal
     */
    public function regeneratePin(Request $request, $id)
    {
        try {
            $portal = GraduationPortal::findOrFail($id);
            
            // Verify access
            $user = $request->user();
            if ($user->role === 'CHAIRPERSON' && 
                $user->department_id !== $portal->department_id) {
                return response()->json([
                    'error' => ['message' => 'Access denied']
                ], 403);
            }
            
            // Generate new PIN
            $newPin = $portal->setPin();
            
            // Log the action
            \App\Models\GraduationPortalLog::log(
                $portal->id,
                \App\Models\GraduationPortalLog::ACTION_PIN_REGENERATED,
                $user->id,
                ['regenerated_at' => now()->toIso8601String()]
            );
            
            return response()->json([
                'message' => 'PIN regenerated successfully',
                'pin' => $newPin,
                'portal' => $this->formatPortalResponse($portal->fresh()),
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => ['message' => 'Portal not found']
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error regenerating PIN: ' . $e->getMessage(), [
                'portalId' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => [
                    'message' => 'Failed to regenerate PIN',
                    'details' => $e->getMessage()
                ]
            ], 500);
        }
    }
}
