<?php

namespace App\Http\Controllers;

use App\Models\Curriculum;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\GraduationPortal;
use App\Models\GraduationPortalLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class PublicGraduationPortalController extends Controller
{
    /**
     * List active portals (for students)
     * 
     * Public endpoint - no authentication required
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $portals = GraduationPortal::active()
                ->with(['curriculum:id,name,year', 'department:id,name'])
                ->select([
                    'id',
                    'name',
                    'description',
                    'batch',
                    'curriculum_id',
                    'department_id',
                    'deadline',
                    'accepted_formats',
                    'max_file_size_mb',
                ])
                ->orderBy('deadline', 'asc')
                ->get();

            // Format for public display (hide sensitive info)
            $formattedPortals = $portals->map(function ($portal) {
                $gracePeriodEnd = $portal->getGracePeriodEnd();
                $isInGracePeriod = $portal->isInGracePeriod();

                return [
                    'id' => (string) $portal->id,
                    'name' => $portal->name,
                    'description' => $portal->description,
                    'batch' => $portal->batch,
                    'deadline' => $portal->deadline?->format('Y-m-d'),
                    'daysRemaining' => $portal->deadline ? now()->diffInDays($portal->deadline, false) : null,
                    'grace_period_end' => $gracePeriodEnd?->toIso8601String(),
                    'is_in_grace_period' => $isInGracePeriod,
                    'acceptedFormats' => $portal->accepted_formats ?? ['.xlsx', '.xls', '.csv'],
                    'maxFileSizeMb' => $portal->max_file_size_mb ?? 5,
                    'curriculum' => $portal->curriculum ? [
                        'id' => (string) $portal->curriculum->id,
                        'name' => $portal->curriculum->name,
                        'year' => $portal->curriculum->year,
                    ] : null,
                    'department' => $portal->department ? [
                        'id' => (string) $portal->department->id,
                        'name' => $portal->department->name,
                    ] : null,
                ];
            });

            return response()->json([
                'portals' => $formattedPortals,
                'total' => $formattedPortals->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching public portals: ' . $e->getMessage());
            
            return response()->json([
                'error' => ['message' => 'Failed to fetch portals']
            ], 500);
        }
    }

    /**
     * Show single portal (public info only)
     */
    public function show(GraduationPortal $portal): JsonResponse
    {
        if (!$portal->isActive()) {
            return response()->json([
                'error' => [
                    'message' => 'This portal is no longer active',
                    'code' => 'PORTAL_INACTIVE'
                ]
            ], 410);
        }

        $gracePeriodEnd = $portal->getGracePeriodEnd();
        $isInGracePeriod = $portal->isInGracePeriod();

        return response()->json([
            'portal' => [
                'id' => (string) $portal->id,
                'name' => $portal->name,
                'description' => $portal->description,
                'batch' => $portal->batch,
                'deadline' => $portal->deadline?->format('Y-m-d'),
                'daysRemaining' => $portal->deadline ? now()->diffInDays($portal->deadline, false) : null,
                'grace_period_end' => $gracePeriodEnd?->toIso8601String(),
                'is_in_grace_period' => $isInGracePeriod,
                'acceptedFormats' => $portal->accepted_formats ?? ['.xlsx', '.xls', '.csv'],
                'maxFileSizeMb' => $portal->max_file_size_mb ?? 5,
                'curriculum' => $portal->curriculum ? [
                    'id' => (string) $portal->curriculum->id,
                    'name' => $portal->curriculum->name,
                    'year' => $portal->curriculum->year,
                ] : null,
                'department' => $portal->department ? [
                    'id' => (string) $portal->department->id,
                    'name' => $portal->department->name,
                ] : null,
                'requiresPin' => true,
            ],
        ]);
    }

    /**
     * Verify PIN and create session token
     */
    public function verifyPin(Request $request, GraduationPortal $portal): JsonResponse
    {
        $request->validate([
            'pin' => 'required|string|min:4|max:20',
        ]);

        // Rate limiting
        $rateLimitKey = "graduation_pin:{$portal->id}:{$request->ip()}";
        $maxAttempts = config('graduation.max_pin_attempts', 5);
        $decayMinutes = config('graduation.pin_attempt_decay_minutes', 15);

        if (RateLimiter::tooManyAttempts($rateLimitKey, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            
            return response()->json([
                'error' => [
                    'message' => "Too many failed attempts. Please try again in " . ceil($seconds / 60) . " minutes.",
                    'code' => 'RATE_LIMITED',
                    'retry_after' => $seconds,
                ]
            ], 429);
        }

        // Check if portal is active
        if (!$portal->isActive()) {
            return response()->json([
                'error' => [
                    'message' => 'This portal is no longer active',
                    'code' => 'PORTAL_INACTIVE'
                ]
            ], 410);
        }

        // Verify PIN
        if (!$portal->verifyPin($request->input('pin'))) {
            RateLimiter::hit($rateLimitKey, $decayMinutes * 60);
            
            // Log failed attempt
            GraduationPortalLog::log(
                $portal->id,
                GraduationPortalLog::ACTION_PIN_FAILED,
                null,
                ['ip' => $request->ip()]
            );
            
            $attemptsLeft = $maxAttempts - RateLimiter::attempts($rateLimitKey);
            
            return response()->json([
                'error' => [
                    'message' => 'Invalid PIN',
                    'code' => 'INVALID_PIN',
                    'attempts_remaining' => max(0, $attemptsLeft),
                ]
            ], 401);
        }

        // Clear rate limiter on success
        RateLimiter::clear($rateLimitKey);

        // Generate session token
        $sessionToken = Str::random(64);
        $cacheStore = config('graduation.cache_store', 'file');
        $ttlMinutes = config('graduation.session_ttl_minutes', 15);

        Cache::store($cacheStore)->put(
            "graduation_session:{$sessionToken}",
            [
                'portal_id' => $portal->id,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now()->toIso8601String(),
            ],
            now()->addMinutes($ttlMinutes)
        );

        // Log successful verification
        GraduationPortalLog::log(
            $portal->id,
            GraduationPortalLog::ACTION_PIN_VERIFIED,
            null,
            ['ip' => $request->ip()]
        );

        return response()->json([
            'message' => 'PIN verified successfully',
            'session' => [
                'token' => $sessionToken,
                'expires_in_minutes' => $ttlMinutes,
                'expires_at' => now()->addMinutes($ttlMinutes)->toIso8601String(),
            ],
            'portal' => [
                'id' => (string) $portal->id,
                'name' => $portal->name,
                'curriculum_id' => $portal->curriculum_id,
                'accepted_formats' => $portal->accepted_formats,
                'max_file_size_mb' => $portal->max_file_size_mb,
            ],
        ]);
    }

    /**
     * Get available curricula for portal (for curriculum selection)
     * 
     * Returns all active curricula in the same department as the portal,
     * along with the portal's assigned curriculum as default.
     */
    public function getCurricula(Request $request, GraduationPortal $portal): JsonResponse
    {
        // Build query for curricula
        $query = Curriculum::query()
            ->where('is_active', true)
            ->with(['department:id,name', 'faculty:id,name']);

        // If portal has a department, filter by it
        if ($portal->department_id) {
            $query->where('department_id', $portal->department_id);
        }
        
        // Optional: Allow filtering by faculty_id or department_id from query params
        // This is useful if portal doesn't have a department set
        if ($request->has('faculty_id')) {
            $query->where('faculty_id', $request->input('faculty_id'));
        }
        
        if ($request->has('department_id')) {
            $query->where('department_id', $request->input('department_id'));
        }

        $curricula = $query
            ->select(['id', 'name', 'year', 'version', 'description', 'total_credits_required', 'department_id', 'faculty_id'])
            ->orderBy('year', 'desc')
            ->orderBy('name', 'asc')
            ->get();

        $formattedCurricula = $curricula->map(function ($curriculum) use ($portal) {
            return [
                'id' => (string) $curriculum->id,
                'name' => $curriculum->name,
                'year' => $curriculum->year,
                'version' => $curriculum->version,
                'description' => $curriculum->description,
                'total_credits_required' => $curriculum->total_credits_required,
                'is_default' => $portal->curriculum_id === $curriculum->id,
                'department' => $curriculum->department ? [
                    'id' => (string) $curriculum->department->id,
                    'name' => $curriculum->department->name,
                ] : null,
                'faculty' => $curriculum->faculty ? [
                    'id' => (string) $curriculum->faculty->id,
                    'name' => $curriculum->faculty->name,
                ] : null,
            ];
        });

        return response()->json([
            'curricula' => $formattedCurricula,
            'default_curriculum_id' => $portal->curriculum_id ? (string) $portal->curriculum_id : null,
            'portal_department_id' => $portal->department_id ? (string) $portal->department_id : null,
            'total' => $formattedCurricula->count(),
        ]);
    }

    /**
     * Get faculties for curriculum selection (when portal has no department)
     */
    public function getFaculties(): JsonResponse
    {
        $faculties = Faculty::select(['id', 'name'])
            ->orderBy('name')
            ->get()
            ->map(fn ($f) => [
                'id' => (string) $f->id,
                'name' => $f->name,
            ]);

        return response()->json([
            'faculties' => $faculties,
        ]);
    }

    /**
     * Get departments for a faculty (for curriculum selection)
     */
    public function getDepartments(Request $request): JsonResponse
    {
        $query = Department::query()->select(['id', 'name', 'faculty_id']);
        
        if ($request->has('faculty_id')) {
            $query->where('faculty_id', $request->input('faculty_id'));
        }

        $departments = $query
            ->orderBy('name')
            ->get()
            ->map(fn ($d) => [
                'id' => (string) $d->id,
                'name' => $d->name,
                'faculty_id' => (string) $d->faculty_id,
            ]);

        return response()->json([
            'departments' => $departments,
        ]);
    }
}
