<?php

namespace App\Http\Controllers;

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
                return [
                    'id' => (string) $portal->id,
                    'name' => $portal->name,
                    'description' => $portal->description,
                    'batch' => $portal->batch,
                    'deadline' => $portal->deadline?->format('Y-m-d'),
                    'daysRemaining' => $portal->deadline ? now()->diffInDays($portal->deadline, false) : null,
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

        return response()->json([
            'portal' => [
                'id' => (string) $portal->id,
                'name' => $portal->name,
                'description' => $portal->description,
                'batch' => $portal->batch,
                'deadline' => $portal->deadline?->format('Y-m-d'),
                'daysRemaining' => $portal->deadline ? now()->diffInDays($portal->deadline, false) : null,
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
     */
    public function getCurricula(GraduationPortal $portal): JsonResponse
    {
        // The portal is already associated with a curriculum,
        // but we might want to let students select from available ones
        
        $curricula = [];
        
        if ($portal->curriculum) {
            $curricula[] = [
                'id' => (string) $portal->curriculum->id,
                'name' => $portal->curriculum->name,
                'year' => $portal->curriculum->year,
                'description' => $portal->curriculum->description,
                'total_credits_required' => $portal->curriculum->total_credits_required,
            ];
        }

        return response()->json([
            'curricula' => $curricula,
            'default_curriculum_id' => $portal->curriculum_id,
        ]);
    }
}
