// Public endpoints
use App\Http\Controllers\PublicFacultyController;
use App\Http\Controllers\PublicDepartmentController;
use App\Http\Controllers\PublicCurriculumController;
use App\Http\Controllers\PublicConcentrationController;
// Public APIs
Route::get('/public-faculties', [PublicFacultyController::class, 'index']);
Route::get('/public-departments', [PublicDepartmentController::class, 'index']);
Route::get('/public-curricula', [PublicCurriculumController::class, 'index']);
Route::get('/public-curricula/{id}', [PublicCurriculumController::class, 'show']);
Route::get('/public-concentrations', [PublicConcentrationController::class, 'index']);
<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Auth
use App\Http\Controllers\API\Auth\AuthController;

// Admin
use App\Http\Controllers\FacultyController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\DashboardStatsController;

// Chairperson
use App\Http\Controllers\CurriculaController;
use App\Http\Controllers\CurriculumController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\CourseTypeController;
use App\Http\Controllers\ConcentrationCourseController;
use App\Http\Controllers\AvailableCourseController;
use App\Http\Controllers\API\Chairperson\BlacklistController;

// Student
use App\Http\Controllers\CompletedCourseController;

// System Setting
use App\Http\Controllers\API\SystemSettingController;

// Download
use App\Http\Controllers\DownloadController;

// Authentication routes
Route::post('/login', [AuthController::class, 'login']);

// Example public endpoints (uncomment if implemented)
// Route::get('public-departments', [DepartmentController::class, 'publicIndex']);
// Route::get('public-curricula', [CurriculaController::class, 'publicIndex']);

Route::middleware(['auth:sanctum'])->group(function () {
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return response()->json($request->user()->load(['faculty', 'department']));
    });

    // Dashboard stats
    Route::get('/dashboard-stats', [DashboardStatsController::class, 'index']);

    // Faculties
    Route::get('/faculties', [FacultyController::class, 'index']);
    Route::post('/faculties', [FacultyController::class, 'store']);
    Route::put('/faculties/{id}', [FacultyController::class, 'update']);
    Route::delete('/faculties/{id}', [FacultyController::class, 'destroy']);

    // Departments
    Route::get('/departments', [DepartmentController::class, 'index']);
    Route::post('/departments', [DepartmentController::class, 'store']);
    Route::put('/departments/{id}', [DepartmentController::class, 'update']);
    Route::delete('/departments/{id}', [DepartmentController::class, 'destroy']);

    // Users
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);

    // Curricula
    Route::get('/curricula', [CurriculaController::class, 'index']);
    Route::post('/curricula', [CurriculaController::class, 'store']);
    Route::get('/curricula/{id}', [CurriculaController::class, 'show']);
    Route::put('/curricula/{id}', [CurriculaController::class, 'update']);
    Route::delete('/curricula/{id}', [CurriculaController::class, 'destroy']);


    // Courses
    Route::get('/courses', [CourseController::class, 'index']);
    Route::post('/courses', [CourseController::class, 'store']);
    Route::get('/courses/{id}', [CourseController::class, 'show']);
    Route::put('/courses/{id}', [CourseController::class, 'update']);
    Route::delete('/courses/{id}', [CourseController::class, 'destroy']);

    // Course Types
    Route::get('/course-types', [CourseTypeController::class, 'index']);
    Route::post('/course-types', [CourseTypeController::class, 'store']);
    Route::put('/course-types/{id}', [CourseTypeController::class, 'update']);
    Route::delete('/course-types/{id}', [CourseTypeController::class, 'destroy']);

    // Concentration Courses
    Route::get('/concentration-courses', [ConcentrationCourseController::class, 'index']);
    Route::post('/concentration-courses', [ConcentrationCourseController::class, 'store']);
    Route::get('/concentration-courses/{id}', [ConcentrationCourseController::class, 'show']);
    Route::put('/concentration-courses/{id}', [ConcentrationCourseController::class, 'update']);
    Route::delete('/concentration-courses/{id}', [ConcentrationCourseController::class, 'destroy']);
    // If you have ConcentrationController, add RESTful endpoints for /concentrations
    // use App\Http\Controllers\API\Chairperson\ConcentrationController;
    // Route::get('/concentrations', [ConcentrationController::class, 'index']);
    // Route::get('/concentrations/{id}', [ConcentrationController::class, 'show']);

    // Available Courses
    Route::get('/available-courses', [AvailableCourseController::class, 'index']);

    // Blacklists
    Route::get('/blacklists', [BlacklistController::class, 'index']);
    Route::post('/blacklists', [BlacklistController::class, 'store']);
    Route::get('/blacklists/{id}', [BlacklistController::class, 'show']);
    Route::put('/blacklists/{id}', [BlacklistController::class, 'update']);
    Route::delete('/blacklists/{id}', [BlacklistController::class, 'destroy']);

    // Completed Courses (Student)
    Route::get('/completed-courses', [CompletedCourseController::class, 'index']);

    // System Setting
    Route::get('/system-settings', [SystemSettingController::class, 'index']);

    // Download
    Route::get('/download/sample-xlsx', [DownloadController::class, 'sampleXlsx']);
    Route::get('/download/sample-csv', [DownloadController::class, 'sampleCsv']);
});