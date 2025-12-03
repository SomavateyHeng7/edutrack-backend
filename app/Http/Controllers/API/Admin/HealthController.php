<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Faculty;
use App\Models\Department;
use App\Models\Course;
use App\Models\Curriculum;

class HealthController extends Controller
{
    /**
     * GET /api/admin/health
     */
    public function index()
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'SUPER_ADMIN') {
            return response()->json(['error' => 'Unauthorized - Super Admin access required'], 401);
        }

        $dbConnected = true;

        try {
            DB::connection()->getPdo();
        } catch (\Exception $e) {
            $dbConnected = false;
        }

        $startTime = Cache::get('app_start_time', now());
        $health = [
            'status' => $dbConnected ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toIso8601String(),
            'uptime' => now()->diffInSeconds($startTime),

            'database' => [
                'status' => $dbConnected ? 'connected' : 'disconnected',
                'tables' => [
                    'users' => User::count(),
                    'faculties' => Faculty::count(),
                    'departments' => Department::count(),
                    'courses' => Course::count(),
                    'curricula' => Curriculum::count(),
                ],
            ],

            'memory' => [
                'used_mb' => round(memory_get_usage(true) / 1048576, 2),
                'peak_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
            ],

            'environment' => app()->environment(),
        ];

        return response()->json($health);
    }
}
