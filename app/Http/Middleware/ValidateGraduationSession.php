<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class ValidateGraduationSession
{
    /**
     * Handle an incoming request.
     *
     * Validates the graduation session token for student submissions.
     * The token is issued after PIN verification and stored in cache.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Graduation-Session-Token') 
                 ?? $request->input('session_token');
        
        if (!$token) {
            return response()->json([
                'error' => [
                    'message' => 'Graduation session token required',
                    'code' => 'SESSION_TOKEN_MISSING'
                ]
            ], 401);
        }
        
        // Get cache store for graduation data
        $cacheStore = config('graduation.cache_store', 'file');
        $session = Cache::store($cacheStore)->get("graduation_session:{$token}");
        
        if (!$session) {
            return response()->json([
                'error' => [
                    'message' => 'Session expired or invalid. Please verify PIN again.',
                    'code' => 'SESSION_EXPIRED'
                ]
            ], 401);
        }
        
        // Validate IP if configured
        if (config('graduation.validate_ip', true)) {
            if ($session['ip'] !== $request->ip()) {
                return response()->json([
                    'error' => [
                        'message' => 'Session IP mismatch. Please verify PIN again.',
                        'code' => 'SESSION_IP_MISMATCH'
                    ]
                ], 401);
            }
        }
        
        // Validate portal ID matches the route parameter
        $portalId = $request->route('portal');
        if ($portalId) {
            // Handle both model binding and ID
            $requestedPortalId = is_object($portalId) ? $portalId->id : $portalId;
            
            if ((string)$session['portal_id'] !== (string)$requestedPortalId) {
                return response()->json([
                    'error' => [
                        'message' => 'Session not valid for this portal',
                        'code' => 'SESSION_PORTAL_MISMATCH'
                    ]
                ], 403);
            }
        }
        
        // Check if session is still within TTL
        $createdAt = \Carbon\Carbon::parse($session['created_at']);
        $ttlMinutes = config('graduation.session_ttl_minutes', 15);
        
        if ($createdAt->addMinutes($ttlMinutes)->isPast()) {
            // Clean up expired session
            Cache::store($cacheStore)->forget("graduation_session:{$token}");
            
            return response()->json([
                'error' => [
                    'message' => 'Session has expired. Please verify PIN again.',
                    'code' => 'SESSION_EXPIRED'
                ]
            ], 401);
        }
        
        // Attach session data to request for use in controllers
        $request->merge(['graduation_session' => $session]);
        
        return $next($request);
    }
}
