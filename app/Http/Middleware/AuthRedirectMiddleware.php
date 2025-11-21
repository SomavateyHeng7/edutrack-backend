<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthRedirectMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $path = $request->path();
        $user = Auth::user();
        $isAuthenticated = Auth::check();

        // Skip authentication for static assets
        if (preg_match('/\.(pdf|jpg|jpeg|png|gif|svg|ico|css|js)$/i', $path)) {
            return $next($request);
        }

        // Public paths
        $publicPaths = [
            '/', 'auth', 'auth/error',
            'student', 'student/management', 'student/allCurricula',
            'student/management/data-entry', 'student/management/progress',
            'student/management/course-planning',
            'student/FutureCourses', 'student/SemesterCourse'
        ];

        // Public path detection
        $isPublicPath =
            in_array($path, $publicPaths) ||
            str_starts_with($path, 'student/management/data-entry') ||
            str_starts_with($path, 'student/management/progress') ||
            str_starts_with($path, 'student/management/course-planning') ||
            str_starts_with($path, 'student/allCurricula');

        // Redirect authenticated user away from /auth
        if ($isAuthenticated && $path === 'auth') {
            $role = $user->role;

            return match ($role) {
                'SUPER_ADMIN' => redirect('/admin'),
                'CHAIRPERSON' => redirect('/chairperson'),
                default => redirect('/student')
            };
        }

        // Redirect unauthenticated users to /auth except public routes
        if (!$isAuthenticated && !$isPublicPath) {
            return redirect('/auth');
        }

        // Role-based access control
        if ($isAuthenticated && $user->role) {
            $role = $user->role;

            // Admin routes
            if (str_starts_with($path, 'admin')) {
                if (!in_array($role, ['SUPER_ADMIN', 'CHAIRPERSON'])) {
                    return redirect('/management');
                }
            }

            // Chairperson routes
            if ($role === 'CHAIRPERSON') {
                $isChair = str_starts_with($path, 'chairperson');
                $isAdmin = str_starts_with($path, 'admin');
                $isProfile = $path === 'profile';
                $isHome = $path === 'home';

                $allowed = $isChair || $isAdmin || $isProfile || $isPublicPath;

                if ($isHome) {
                    return redirect('/chairperson');
                }

                if (!$allowed) {
                    return redirect('/chairperson');
                }
            }

            // SUPER_ADMIN route behavior
            if ($role === 'SUPER_ADMIN') {
                if ($path === 'home') {
                    return redirect('/admin');
                }
            }

            // Advisor routes
            if (str_starts_with($path, 'advisor') && $role !== 'ADVISOR') {
                return match ($role) {
                    'CHAIRPERSON' => redirect('/chairperson'),
                    'SUPER_ADMIN' => redirect('/admin'),
                    default => redirect('/management')
                };
            }

            // Protect chairperson routes
            if (str_starts_with($path, 'chairperson') && !in_array($role, ['CHAIRPERSON', 'SUPER_ADMIN'])) {
                return redirect('/management');
            }
        }

        return $next($request);
    }
}