<?php

namespace App\Http\Controllers;

use App\Events\NewGraduationSubmission;
use App\Events\SubmissionValidated;
use App\Http\Requests\StoreGraduationSubmissionRequest;
use App\Models\GraduationNotification;
use App\Models\GraduationPortal;
use App\Models\GraduationPortalLog;
use App\Services\GraduationValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class GraduationSubmissionController extends Controller
{
    public function __construct(
        private GraduationValidationService $validationService
    ) {}

    /**
     * Submit course data for validation
     * 
     * @OA\Post(
     *     path="/api/graduation-portals/{portal}/submit",
     *     summary="Submit graduation data",
     *     tags={"Graduation Submissions"}
     * )
     */
    public function store(StoreGraduationSubmissionRequest $request, GraduationPortal $portal): JsonResponse
    {
        try {
            $session = $request->input('graduation_session');
            $cacheStore = config('graduation.cache_store', 'file');
            $retentionDays = config('graduation.submission_retention_days', 7);
            $gracePeriodDays = config('graduation.grace_period_days', 7);

            // Check grace period: accept submissions up to deadline + grace_period_days
            if ($portal->deadline) {
                $gracePeriodEnd = $portal->deadline->copy()->addDays($gracePeriodDays);
                if (now()->greaterThan($gracePeriodEnd)) {
                    return response()->json([
                        'message' => 'The submission window (including grace period) has closed.',
                        'error' => [
                            'message' => 'The submission window (including grace period) has closed.',
                            'code' => 'GRACE_PERIOD_ENDED'
                        ]
                    ], 422);
                }
            }

            // Sanitize inputs
            $studentIdentifier = $this->sanitizeIdentifier($request->input('student_identifier'));
            $courses = $this->sanitizeCourses($request->input('courses'));
            $curriculumId = $request->input('curriculum_id');

            // Generate unique submission ID
            $submissionId = (string) Str::uuid();

            // Calculate expiry: portal deadline + retention days
            $deletionDate = $this->calculateDeletionDate($portal, $retentionDays);
            $expiresAt = $deletionDate->toIso8601String();

            // Prepare submission data
            $submissionData = [
                'id' => $submissionId,
                'portal_id' => $portal->id,
                'student_identifier' => $studentIdentifier,
                'curriculum_id' => $curriculumId,
                'courses' => $courses,
                'status' => 'pending',
                'validation_result' => null,
                'submitted_at' => now()->toIso8601String(),
                'expires_at' => $expiresAt,
                'deletion_date' => $deletionDate->format('Y-m-d'),
                'metadata' => $request->input('metadata', []),
                'ip_address' => $request->ip(),
            ];

            // Store in cache - convert deletion date to TTL in seconds
            $ttlSeconds = $this->calculateCacheTtlSeconds($deletionDate);
            Cache::store($cacheStore)->put(
                "graduation_submission:{$submissionId}",
                $submissionData,
                $ttlSeconds
            );

            // Add to portal's submission list
            $this->addToPortalSubmissionList($portal->id, $submissionId, $cacheStore, $deletionDate);

            // Log the submission
            GraduationPortalLog::log(
                $portal->id,
                GraduationPortalLog::ACTION_SUBMISSION_RECEIVED,
                null, // Anonymous student
                [
                    'submission_id' => $submissionId,
                    'student_identifier' => $studentIdentifier,
                    'course_count' => count($courses),
                ]
            );

            // Broadcast event for real-time notification to CP/Advisors
            try {
                event(new NewGraduationSubmission($portal, $submissionId, [
                    'student_identifier' => $studentIdentifier,
                    'curriculum_id' => $curriculumId,
                    'course_count' => count($courses),
                    'submitted_at' => $submissionData['submitted_at'],
                ]));
            } catch (\Exception $e) {
                // Log but don't fail if broadcasting fails
                Log::warning('Failed to broadcast submission event: ' . $e->getMessage());
            }

            // Create notifications for chairpersons/advisors in the department
            try {
                if ($portal->department_id) {
                    $notificationCount = GraduationNotification::notifyDepartmentStaff(
                        $portal->department_id,
                        $portal->id,
                        $portal->name,
                        $studentIdentifier,
                        $submissionId,
                        count($courses)
                    );
                    Log::info("Created {$notificationCount} notifications for submission {$submissionId}");
                }
            } catch (\Exception $e) {
                // Log but don't fail if notification creation fails
                Log::warning('Failed to create notifications: ' . $e->getMessage());
            }

            return response()->json([
                'message' => 'Submission received successfully',
                'submission' => [
                    'id' => $submissionId,
                    'status' => 'pending',
                    'expires_at' => $submissionData['expires_at'],
                    'deletion_date' => $submissionData['deletion_date'],
                    'course_count' => count($courses),
                ],
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error storing graduation submission: ' . $e->getMessage(), [
                'portal_id' => $portal->id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => [
                    'message' => 'Failed to submit graduation data',
                    'details' => $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * List submissions for a portal
     */
    public function index(Request $request, GraduationPortal $portal): JsonResponse
    {
        try {
            $this->authorizeAccess($request->user(), $portal);
            
            $cacheStore = config('graduation.cache_store', 'file');
            $submissionIds = Cache::store($cacheStore)->get("portal_submissions:{$portal->id}", []);
            
            $submissions = [];
            $activeIds = [];
            
            foreach ($submissionIds as $submissionId) {
                $submission = Cache::store($cacheStore)->get("graduation_submission:{$submissionId}");
                
                if ($submission) {
                    $submissions[] = [
                        'id' => $submission['id'],
                        'student_identifier' => $submission['student_identifier'],
                        'curriculum_id' => $submission['curriculum_id'],
                        'status' => $submission['status'],
                        'course_count' => count($submission['courses'] ?? []),
                        'submitted_at' => $submission['submitted_at'],
                        'expires_at' => $submission['expires_at'],
                        'deletion_date' => $submission['deletion_date'] ?? null,
                        'has_validation_result' => !empty($submission['validation_result']),
                    ];
                    $activeIds[] = $submissionId;
                }
            }
            
            // Update the list to remove expired submissions
            if (count($activeIds) !== count($submissionIds)) {
                $retentionDays = config('graduation.submission_retention_days', 7);
                $deletionDate = $this->calculateDeletionDate($portal, $retentionDays);
                $ttlSeconds = $this->calculateCacheTtlSeconds($deletionDate);
                Cache::store($cacheStore)->put(
                    "portal_submissions:{$portal->id}",
                    $activeIds,
                    $ttlSeconds
                );
            }
            
            // Sort by submitted_at descending
            usort($submissions, fn($a, $b) => strcmp($b['submitted_at'], $a['submitted_at']));

            $retentionDays = config('graduation.submission_retention_days', 7);
            $deletionDate = $portal->deadline ? $portal->deadline->copy()->addDays($retentionDays)->format('Y-m-d') : 'N/A';
            $isInGracePeriod = $portal->isInGracePeriod();

            return response()->json([
                'submissions' => $submissions,
                'total' => count($submissions),
                'retention_info' => [
                    'portal_deadline' => $portal->deadline?->format('Y-m-d'),
                    'retention_days' => $retentionDays,
                    'deletion_date' => $deletionDate,
                    'is_in_grace_period' => $isInGracePeriod,
                ],
                'note' => "Submissions will be deleted {$retentionDays} days after the portal deadline ({$deletionDate})",
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching submissions: ' . $e->getMessage(), [
                'portal_id' => $portal->id,
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
     * Get single submission details
     */
    public function show(Request $request, GraduationPortal $portal, string $submissionId): JsonResponse
    {
        try {
            $this->authorizeAccess($request->user(), $portal);
            
            $cacheStore = config('graduation.cache_store', 'file');
            $submission = Cache::store($cacheStore)->get("graduation_submission:{$submissionId}");
            
            if (!$submission) {
                return response()->json([
                    'error' => [
                        'message' => 'Submission not found or has expired',
                        'code' => 'SUBMISSION_EXPIRED'
                    ]
                ], 404);
            }
            
            if ((string)$submission['portal_id'] !== (string)$portal->id) {
                return response()->json([
                    'error' => ['message' => 'Submission does not belong to this portal']
                ], 403);
            }

            return response()->json([
                'submission' => $submission,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => [
                    'message' => 'Failed to fetch submission',
                    'details' => $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Validate a submission against curriculum
     */
    public function validate(Request $request, GraduationPortal $portal, string $submissionId): JsonResponse
    {
        try {
            $this->authorizeAccess($request->user(), $portal);
            
            $cacheStore = config('graduation.cache_store', 'file');
            $submission = Cache::store($cacheStore)->get("graduation_submission:{$submissionId}");
            
            if (!$submission) {
                return response()->json([
                    'error' => [
                        'message' => 'Submission not found or has expired',
                        'code' => 'SUBMISSION_EXPIRED'
                    ]
                ], 404);
            }
            
            // Run validation
            $validationResult = $this->validationService->validate(
                $submission['courses'],
                $submission['curriculum_id']
            );
            
            // Update submission with validation result
            $submission['validation_result'] = $validationResult;
            $submission['status'] = $validationResult['canGraduate'] ? 'validated' : 'has_issues';
            $submission['validated_at'] = now()->toIso8601String();
            $submission['validated_by'] = $request->user()->id;
            
            // Store updated submission
            $remainingTtl = $this->calculateRemainingTtlMinutes($submission['expires_at']);
            Cache::store($cacheStore)->put(
                "graduation_submission:{$submissionId}",
                $submission,
                now()->addMinutes($remainingTtl)
            );
            
            // Log the validation
            GraduationPortalLog::log(
                $portal->id,
                GraduationPortalLog::ACTION_SUBMISSION_VALIDATED,
                $request->user()->id,
                [
                    'submission_id' => $submissionId,
                    'can_graduate' => $validationResult['canGraduate'],
                    'error_count' => count($validationResult['errors']),
                ]
            );
            
            // Broadcast validation result
            try {
                event(new SubmissionValidated($portal, $submissionId, $validationResult));
            } catch (\Exception $e) {
                Log::warning('Failed to broadcast validation event: ' . $e->getMessage());
            }

            return response()->json([
                'message' => 'Validation completed',
                'submission' => [
                    'id' => $submissionId,
                    'status' => $submission['status'],
                    'student_identifier' => $submission['student_identifier'],
                    'curriculum_id' => $submission['curriculum_id'],
                    'courses' => $submission['courses'],
                    'submitted_at' => $submission['submitted_at'],
                    'expires_at' => $submission['expires_at'],
                    'deletion_date' => $submission['deletion_date'] ?? null,
                ],
                'validation' => $validationResult,
            ]);

        } catch (\Exception $e) {
            Log::error('Error validating submission: ' . $e->getMessage(), [
                'portal_id' => $portal->id,
                'submission_id' => $submissionId,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => [
                    'message' => 'Failed to validate submission',
                    'details' => $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Batch validate multiple submissions
     */
    public function batchValidate(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'submission_ids' => 'required|array|min:1|max:50',
                'submission_ids.*' => 'required|string|uuid',
            ]);

            $user = $request->user();
            $cacheStore = config('graduation.cache_store', 'file');
            $results = [];
            $successCount = 0;
            $failCount = 0;

            foreach ($request->input('submission_ids') as $submissionId) {
                $submission = Cache::store($cacheStore)->get("graduation_submission:{$submissionId}");
                
                if (!$submission) {
                    $results[] = [
                        'submission_id' => $submissionId,
                        'success' => false,
                        'error' => 'Submission not found or expired',
                    ];
                    $failCount++;
                    continue;
                }

                // Verify access to the portal
                $portal = GraduationPortal::find($submission['portal_id']);
                if (!$portal || !$this->canAccessPortal($user, $portal)) {
                    $results[] = [
                        'submission_id' => $submissionId,
                        'success' => false,
                        'error' => 'Access denied',
                    ];
                    $failCount++;
                    continue;
                }

                // Run validation
                $validationResult = $this->validationService->validate(
                    $submission['courses'],
                    $submission['curriculum_id']
                );

                // Update submission
                $submission['validation_result'] = $validationResult;
                $submission['status'] = $validationResult['canGraduate'] ? 'validated' : 'has_issues';
                $submission['validated_at'] = now()->toIso8601String();
                $submission['validated_by'] = $user->id;

                $remainingTtl = $this->calculateRemainingTtlMinutes($submission['expires_at']);
                Cache::store($cacheStore)->put(
                    "graduation_submission:{$submissionId}",
                    $submission,
                    now()->addMinutes($remainingTtl)
                );

                $results[] = [
                    'submission_id' => $submissionId,
                    'success' => true,
                    'can_graduate' => $validationResult['canGraduate'],
                    'error_count' => count($validationResult['errors']),
                ];
                $successCount++;
            }

            return response()->json([
                'message' => "Batch validation completed: {$successCount} successful, {$failCount} failed",
                'results' => $results,
                'summary' => [
                    'total' => count($request->input('submission_ids')),
                    'success' => $successCount,
                    'failed' => $failCount,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error in batch validation: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => [
                    'message' => 'Batch validation failed',
                    'details' => $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Approve a submission (mark as graduation-ready)
     */
    public function approve(Request $request, GraduationPortal $portal, string $submissionId): JsonResponse
    {
        try {
            $this->authorizeAccess($request->user(), $portal);
            
            $cacheStore = config('graduation.cache_store', 'file');
            $submission = Cache::store($cacheStore)->get("graduation_submission:{$submissionId}");
            
            if (!$submission) {
                return response()->json([
                    'error' => ['message' => 'Submission not found or has expired']
                ], 404);
            }
            
            $submission['status'] = 'approved';
            $submission['approved_at'] = now()->toIso8601String();
            $submission['approved_by'] = $request->user()->id;
            $submission['approval_notes'] = $request->input('notes');
            
            $remainingTtl = $this->calculateRemainingTtlMinutes($submission['expires_at']);
            Cache::store($cacheStore)->put(
                "graduation_submission:{$submissionId}",
                $submission,
                now()->addMinutes($remainingTtl)
            );
            
            GraduationPortalLog::log(
                $portal->id,
                GraduationPortalLog::ACTION_SUBMISSION_APPROVED,
                $request->user()->id,
                [
                    'submission_id' => $submissionId,
                    'student_identifier' => $submission['student_identifier'],
                ]
            );

            return response()->json([
                'message' => 'Submission approved',
                'submission' => [
                    'id' => $submissionId,
                    'status' => 'approved',
                    'approved_at' => $submission['approved_at'],
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => ['message' => 'Failed to approve submission']
            ], 500);
        }
    }

    /**
     * Reject a submission
     */
    public function reject(Request $request, GraduationPortal $portal, string $submissionId): JsonResponse
    {
        try {
            $this->authorizeAccess($request->user(), $portal);
            
            $request->validate([
                'reason' => 'required|string|max:1000',
            ]);
            
            $cacheStore = config('graduation.cache_store', 'file');
            $submission = Cache::store($cacheStore)->get("graduation_submission:{$submissionId}");
            
            if (!$submission) {
                return response()->json([
                    'error' => ['message' => 'Submission not found or has expired']
                ], 404);
            }
            
            $submission['status'] = 'rejected';
            $submission['rejected_at'] = now()->toIso8601String();
            $submission['rejected_by'] = $request->user()->id;
            $submission['rejection_reason'] = $request->input('reason');
            
            $remainingTtl = $this->calculateRemainingTtlMinutes($submission['expires_at']);
            Cache::store($cacheStore)->put(
                "graduation_submission:{$submissionId}",
                $submission,
                now()->addMinutes($remainingTtl)
            );
            
            GraduationPortalLog::log(
                $portal->id,
                GraduationPortalLog::ACTION_SUBMISSION_REJECTED,
                $request->user()->id,
                [
                    'submission_id' => $submissionId,
                    'student_identifier' => $submission['student_identifier'],
                    'reason' => $request->input('reason'),
                ]
            );

            return response()->json([
                'message' => 'Submission rejected',
                'submission' => [
                    'id' => $submissionId,
                    'status' => 'rejected',
                    'rejected_at' => $submission['rejected_at'],
                    'reason' => $submission['rejection_reason'],
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => ['message' => 'Failed to reject submission']
            ], 500);
        }
    }

    /**
     * Download validation report as PDF
     */
    public function downloadReport(Request $request, GraduationPortal $portal, string $submissionId): JsonResponse
    {
        try {
            $this->authorizeAccess($request->user(), $portal);
            
            $cacheStore = config('graduation.cache_store', 'file');
            $submission = Cache::store($cacheStore)->get("graduation_submission:{$submissionId}");
            
            if (!$submission) {
                return response()->json([
                    'error' => ['message' => 'Submission not found or has expired']
                ], 404);
            }
            
            if (!$submission['validation_result']) {
                return response()->json([
                    'error' => ['message' => 'Submission has not been validated yet']
                ], 400);
            }

            // For now, return JSON report data
            // In production, you would generate a PDF here
            return response()->json([
                'report' => [
                    'generated_at' => now()->toIso8601String(),
                    'portal' => [
                        'name' => $portal->name,
                        'deadline' => $portal->deadline,
                    ],
                    'student_identifier' => $submission['student_identifier'],
                    'submitted_at' => $submission['submitted_at'],
                    'validation_result' => $submission['validation_result'],
                ],
                'note' => 'PDF generation can be implemented using dompdf or similar package',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => ['message' => 'Failed to generate report']
            ], 500);
        }
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    private function authorizeAccess($user, GraduationPortal $portal): void
    {
        if (!$this->canAccessPortal($user, $portal)) {
            abort(403, 'Access denied to this portal');
        }
    }

    private function canAccessPortal($user, GraduationPortal $portal): bool
    {
        if (strtoupper($user->role) === 'ADMIN') {
            return true;
        }
        
        if (strtoupper($user->role) === 'CHAIRPERSON') {
            return $user->department_id === $portal->department_id;
        }
        
        if (strtoupper($user->role) === 'ADVISOR') {
            return $user->department_id === $portal->department_id;
        }
        
        return false;
    }

    private function sanitizeIdentifier(string $identifier): string
    {
        return \Illuminate\Support\Str::limit(
            strip_tags(trim($identifier)),
            255,
            ''
        );
    }

    private function sanitizeCourses(array $courses): array
    {
        return collect($courses)->map(function ($course) {
            return [
                'code' => strtoupper(strip_tags(trim($course['code'] ?? ''))),
                'name' => strip_tags(trim($course['name'] ?? '')),
                'credits' => (float) ($course['credits'] ?? 0),
                'grade' => strtoupper(strip_tags(trim($course['grade'] ?? ''))),
                'status' => strtolower(strip_tags(trim($course['status'] ?? 'completed'))),
                'semester' => strip_tags(trim($course['semester'] ?? '')),
                'category' => strip_tags(trim($course['category'] ?? '')),
            ];
        })->toArray();
    }

    private function addToPortalSubmissionList(int $portalId, string $submissionId, string $cacheStore, Carbon $deletionDate): void
    {
        $listKey = "portal_submissions:{$portalId}";
        $list = Cache::store($cacheStore)->get($listKey, []);
        $list[] = $submissionId;
        
        // Keep only the most recent submissions (max 100)
        if (count($list) > 100) {
            $list = array_slice($list, -100);
        }
        
        $ttlSeconds = $this->calculateCacheTtlSeconds($deletionDate);
        Cache::store($cacheStore)->put($listKey, $list, $ttlSeconds);
    }

    /**
     * Calculate the deletion date for a submission based on portal deadline + retention days.
     * If portal has no deadline, defaults to now + retention days.
     */
    private function calculateDeletionDate(GraduationPortal $portal, int $retentionDays): Carbon
    {
        if ($portal->deadline) {
            $deletionDate = $portal->deadline->copy()->addDays($retentionDays);
            // Ensure deletion date is in the future (at least 1 hour from now)
            if ($deletionDate->isPast()) {
                return now()->addDays($retentionDays);
            }
            return $deletionDate;
        }
        
        // Fallback: if no deadline, use now + retention days
        return now()->addDays($retentionDays);
    }

    /**
     * Calculate cache TTL in seconds from a deletion date.
     * Returns seconds until deletion date, with bounds checking.
     */
    private function calculateCacheTtlSeconds(Carbon $deletionDate): int
    {
        $seconds = $deletionDate->diffInSeconds(now(), false);
        
        // Ensure positive TTL (minimum 1 hour = 3600 seconds)
        if ($seconds <= 0) {
            $seconds = 3600; // 1 hour minimum
        }
        
        // Maximum TTL: 365 days (to prevent overflow)
        $maxTtl = 365 * 24 * 60 * 60; // 31,536,000 seconds
        if ($seconds > $maxTtl) {
            $seconds = $maxTtl;
        }
        
        return (int) $seconds;
    }

    /**
     * Calculate remaining TTL in minutes from an expires_at ISO string.
     * Returns minutes with bounds checking to prevent overflow.
     */
    private function calculateRemainingTtlMinutes(string $expiresAt): int
    {
        try {
            $expiresAtDate = Carbon::parse($expiresAt);
            $minutes = now()->diffInMinutes($expiresAtDate, false);
            
            // Minimum 1 minute
            if ($minutes <= 0) {
                return 1;
            }
            
            // Maximum 365 days in minutes (to prevent overflow)
            $maxMinutes = 365 * 24 * 60; // 525,600 minutes
            if ($minutes > $maxMinutes) {
                return $maxMinutes;
            }
            
            return (int) $minutes;
        } catch (\Exception $e) {
            // Fallback to 1 hour if parsing fails
            return 60;
        }
    }
}
