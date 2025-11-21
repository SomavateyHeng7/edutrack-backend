<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Faculty;
use App\Models\Department;
use App\Models\Course;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DashboardStatsController extends Controller
{
    public function getStats(Request $request)
    {
        try {
            // User authentication and role check (assuming Sanctum or Passport)
            $user = Auth::user();

            if (!$user || !in_array($user->role, ['SUPER_ADMIN', 'CHAIRPERSON'])) {
                return response()->json([
                    'error' => 'Unauthorized - Admin access required'
                ], 401);
            }

            // Date calculations
            $currentDate = Carbon::now();
            $lastMonth = $currentDate->copy()->subMonth();
            $sixMonthsAgo = $currentDate->copy()->subMonths(6);

            // User statistics
            $totalUsers = User::count();
            $usersLastMonth = User::whereBetween('created_at', [$lastMonth, $currentDate])->count();
            $previousMonthUsers = User::where('created_at', '<', $lastMonth)->count();
            $userGrowthPercentage = $previousMonthUsers > 0
                ? round(($usersLastMonth / $previousMonthUsers) * 100)
                : 0;

            // Faculty, department, course statistics
            $totalFaculties = Faculty::count();
            $totalDepartments = Department::count();
            $totalCourses = Course::count();
            $newCoursesThisMonth = Course::where('created_at', '>=', $lastMonth)->count();

            // User role distribution
            $usersByRole = User::select('role', DB::raw('count(*) as count'))
                ->groupBy('role')
                ->get()
                ->pluck('count', 'role');

            // Faculty distribution with user counts
            $facultyDistribution = Faculty::withCount(['users', 'departments'])
                ->orderByDesc('users_count')
                ->get();

            $facultyChartData = [];
            $colors = ['#8884d8', '#82ca9d', '#ffc658', '#ff7300', '#00ff00'];
            $totalFacultyUsers = $facultyDistribution->sum('users_count');
            foreach ($facultyDistribution as $index => $faculty) {
                $facultyChartData[] = [
                    'name' => $faculty->name,
                    'value' => $totalFacultyUsers > 0 ? round(($faculty->users_count / $totalFacultyUsers) * 100) : 0,
                    'count' => $faculty->users_count,
                    'color' => $colors[$index % count($colors)],
                ];
            }

            // Monthly enrollment data (last 6 months)
            $monthlyData = [];
            for ($i = 5; $i >= 0; $i--) {
                $date = $currentDate->copy()->subMonths($i);
                $monthName = $date->format('M');
                $monthStart = $date->copy()->startOfMonth();
                $monthEnd = $date->copy()->endOfMonth();

                $usersInMonth = User::whereBetween('created_at', [$monthStart, $monthEnd])->count();
                $coursesInMonth = Course::whereBetween('created_at', [$monthStart, $monthEnd])->count();

                $monthlyData[] = [
                    'month' => $monthName,
                    'students' => $usersInMonth,
                    'courses' => $coursesInMonth,
                ];
            }

            // Program completion rates (mock)
            $programCompletionRates = [
                ['program' => 'BSCS', 'completed' => 87, 'inProgress' => 13],
                ['program' => 'BSIT', 'completed' => 92, 'inProgress' => 8],
                ['program' => 'BBA', 'completed' => 89, 'inProgress' => 11],
                ['program' => 'MBA', 'completed' => 94, 'inProgress' => 6],
            ];

            // Compose response
            $dashboardStats = [
                'overview' => [
                    'totalUsers' => $totalUsers,
                    'userGrowth' => $userGrowthPercentage,
                    'totalFaculties' => $totalFaculties,
                    'totalDepartments' => $totalDepartments,
                    'totalCourses' => $totalCourses,
                    'newCourses' => $newCoursesThisMonth,
                ],
                'usersByRole' => $usersByRole,
                'monthlyEnrollment' => $monthlyData,
                'facultyDistribution' => $facultyChartData,
                'programCompletion' => $programCompletionRates,
                'lastUpdated' => $currentDate->toIso8601String(),
            ];

            return response()->json($dashboardStats);

        } catch (\Exception $e) {
            // Error handling
            Log::error('Error fetching dashboard stats: '.$e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch dashboard statistics'
            ], 500);
        }
    }
}
