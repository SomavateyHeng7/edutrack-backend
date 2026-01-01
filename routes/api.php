<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Auth
use App\Http\Controllers\API\Auth\AuthController;

// Public endpoints
use App\Http\Controllers\PublicFacultyController;
use App\Http\Controllers\API\Admin\UserController;
use App\Http\Controllers\PublicCurriculumController;
use App\Http\Controllers\PublicDepartmentController;

// Admin
use App\Http\Controllers\API\Admin\FacultyController;
use App\Http\Controllers\API\SystemSettingController;
use App\Http\Controllers\PublicConcentrationController;
use App\Http\Controllers\API\Admin\DepartmentController;

// Chairperson
use App\Http\Controllers\API\Download\DownloadController;
use App\Http\Controllers\API\Chairperson\CourseController;
use App\Http\Controllers\API\Admin\DashboardStatsController;
use App\Http\Controllers\API\Chairperson\BlacklistController;
use App\Http\Controllers\API\Chairperson\CurriculaController;
use App\Http\Controllers\API\Chairperson\CourseTypeController;
use App\Http\Controllers\API\Chairperson\CurriculumController;
use App\Http\Controllers\API\Student\CompletedCourseController;
use App\Http\Controllers\API\Chairperson\ElectiveRuleController;
use App\Http\Controllers\API\Chairperson\AvailableCourseController;
use App\Http\Controllers\API\Chairperson\StudentController;
use App\Http\Controllers\API\Chairperson\TentativeScheduleController;

// Student
use App\Http\Controllers\API\Chairperson\FacultyLabelController;

// System Setting

// Download
use App\Http\Controllers\API\Chairperson\ConcentrationCourseController;
use App\Http\Controllers\API\Chairperson\CurriculumBlacklistController;
use App\Http\Controllers\API\Chairperson\CurriculumConstraintsController;
use App\Http\Controllers\API\Chairperson\CurriculumCourseConstraintsController;

// Public APIs
Route::get('/public-faculties', [PublicFacultyController::class, 'index']);
Route::get('/public-departments', [PublicDepartmentController::class, 'index']);
Route::get('/public-curricula', [PublicCurriculumController::class, 'index']);
Route::get('/public-curricula/{id}', [PublicCurriculumController::class, 'show']);
Route::get('/public-concentrations', [PublicConcentrationController::class, 'index']);
Route::get('/public-curricula/{id}/blacklists', [PublicCurriculumController::class, 'blacklists']);

// Public courses endpoint
Route::get('/public-courses', [CourseController::class, 'index']);

// Available Courses
Route::get('/available-courses', [AvailableCourseController::class, 'index']);

// Authentication routes
Route::post('/login', [AuthController::class, 'login']);
Route::get('/user', function (Request $request) {
    $user = $request->user();
    if ($user) {
        return response()->json($user->load(['faculty', 'department']));
    }
    return response()->json(null);
    });

Route::middleware(['auth:sanctum'])->group(function () {
    
    // Concentrations
    Route::get('/concentrations', [\App\Http\Controllers\API\Chairperson\ConcentrationController::class, 'index']);
    Route::post('/concentrations', [\App\Http\Controllers\API\Chairperson\ConcentrationController::class, 'store']);
    Route::get('/concentrations/{id}', [\App\Http\Controllers\API\Chairperson\ConcentrationController::class, 'show']);
    Route::put('/concentrations/{id}', [\App\Http\Controllers\API\Chairperson\ConcentrationController::class, 'update']);  
    Route::delete('/concentrations/{id}', [\App\Http\Controllers\API\Chairperson\ConcentrationController::class, 'destroy']);
    
    // Concentration Courses (nested routes)
    Route::get('/concentrations/{id}/courses', [ConcentrationCourseController::class, 'coursesIndex']);
    Route::post('/concentrations/{id}/courses', [ConcentrationCourseController::class, 'coursesStore']);
    Route::delete('/concentrations/{id}/courses', [ConcentrationCourseController::class, 'coursesDestroy']);
    
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    

    // Dashboard stats
    Route::get('/dashboard-stats', [DashboardStatsController::class, 'getStats']);

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
    Route::post('/curricula/{id}/duplicate', [CurriculaController::class, 'duplicate']);
    
    // Curriculum sub-resources
    Route::get('/curricula/{id}/elective-rules', [CurriculaController::class, 'electiveRules']);
    Route::get('/curricula/{id}/concentrations', [CurriculaController::class, 'concentrations']);
    Route::get('/curricula/{id}/blacklists', [CurriculaController::class, 'blacklists']);

   Route::prefix('courses')->middleware('auth:sanctum')->group(function () {

    // =========================
    // BASIC CRUD
    // =========================
    Route::get('/', [CourseController::class, 'index']);
    Route::post('/', [CourseController::class, 'store']);

    // =========================
    // SEARCH & BULK
    // =========================
    Route::get('/search', [CourseController::class, 'search']);
    Route::post('/map-codes', [CourseController::class, 'mapCodes']);
    Route::post('/bulk-create', [CourseController::class, 'bulkCreate']);

    // =========================
    // COURSE-SPECIFIC
    // =========================
    Route::get('/{course}', [CourseController::class, 'show']);
    Route::put('/{course}', [CourseController::class, 'update']);
    Route::delete('/{course}', [CourseController::class, 'destroy']);

    // =========================
    // CONSTRAINTS
    // =========================
    Route::get('/{course}/constraints', [CourseController::class, 'constraints']);

    // =========================
    // PREREQUISITES
    // =========================
    Route::get('/{course}/prerequisites', [CourseController::class, 'prerequisites']);
    Route::post('/{course}/prerequisites', [CourseController::class, 'addPrerequisite']);
    Route::delete('/{course}/prerequisites/{relation}', [CourseController::class, 'removePrerequisite']);

    // =========================
    // COREQUISITES
    // =========================
    Route::get('/{course}/corequisites', [CourseController::class, 'corequisites']);
    Route::post('/{course}/corequisites', [CourseController::class, 'addCorequisite']);
    Route::delete('/{course}/corequisites/{relation}', [CourseController::class, 'removeCorequisite']);
});

    // Course Types
    Route::prefix('course-types')->group(function () {
        Route::get('/', [CourseTypeController::class, 'index']);
        Route::post('/', [CourseTypeController::class, 'store']);

        Route::post('/assign', [CourseTypeController::class, 'bulkAssign']);

        Route::get('/{id}', [CourseTypeController::class, 'show']);
        Route::put('/{id}', [CourseTypeController::class, 'update']);
        Route::delete('/{id}', [CourseTypeController::class, 'destroy']);
    });

    // Concentration Courses
    Route::get('/concentration-courses', [ConcentrationCourseController::class, 'index']);
    Route::post('/concentration-courses', [ConcentrationCourseController::class, 'store']);
    Route::get('/concentration-courses/{id}', [ConcentrationCourseController::class, 'show']);
    Route::put('/concentration-courses/{id}', [ConcentrationCourseController::class, 'update']);
    Route::delete('/concentration-courses/{id}', [ConcentrationCourseController::class, 'destroy']);

    // Blacklists
    Route::get('/blacklists', [BlacklistController::class, 'index']);
    Route::post('/blacklists', [BlacklistController::class, 'store']);
    Route::get('/blacklists/{id}', [BlacklistController::class, 'show']);
    Route::put('/blacklists/{id}', [BlacklistController::class, 'update']);
    Route::delete('/blacklists/{id}', [BlacklistController::class, 'destroy']);
    Route::get('/blacklists/courses/search', [BlacklistController::class, 'searchCourses']);

    // Curriculum Blacklists
    Route::get('/curricula/{id}/blacklists', [CurriculumBlacklistController::class, 'index']);
    Route::post('/curricula/{id}/blacklists', [CurriculumBlacklistController::class, 'store']);
    Route::delete('/curricula/{id}/blacklists/{blacklistId}', [CurriculumBlacklistController::class, 'destroy']);

    // Curriculum Constraints
    Route::get('/curricula/{id}/constraints', [CurriculumConstraintsController::class, 'index']);
    Route::post('/curricula/{id}/constraints', [CurriculumConstraintsController::class, 'store']);
    Route::delete('/curricula/{id}/constraints/{constraintId}', [CurriculumConstraintsController::class, 'destroy']);

    // Curriculum Course Constraints (Prerequisites, Corequisites, Flags)
    Route::get('/curricula/{curriculumId}/courses/{curriculumCourseId}/constraints', [CurriculumCourseConstraintsController::class, 'index']);
    Route::put('/curricula/{curriculumId}/courses/{curriculumCourseId}/constraints', [CurriculumCourseConstraintsController::class, 'update']);
    Route::post('/curricula/{curriculumId}/courses/{curriculumCourseId}/prerequisites', [CurriculumCourseConstraintsController::class, 'addPrerequisite']);
    Route::delete('/curricula/{curriculumId}/courses/{curriculumCourseId}/prerequisites/{relationId}', [CurriculumCourseConstraintsController::class, 'removePrerequisite']);
    Route::post('/curricula/{curriculumId}/courses/{curriculumCourseId}/corequisites', [CurriculumCourseConstraintsController::class, 'addCorequisite']);
    Route::delete('/curricula/{curriculumId}/courses/{curriculumCourseId}/corequisites/{relationId}', [CurriculumCourseConstraintsController::class, 'removeCorequisite']);


    // Faculty Label (Concentration Label)
    Route::get('/faculty/concentration-label', [FacultyLabelController::class, 'getConcentrationLabel']);
    Route::put('/faculty/concentration-label', [FacultyLabelController::class, 'updateConcentrationLabel']);

    // Completed Courses (Student)
    Route::get('/completed-courses', [CompletedCourseController::class, 'index']);

    // System Setting
    Route::get('/system-settings', [SystemSettingController::class, 'index']);

    // Config Feature Flags
    Route::get('/config/feature-flags', function () {
        return response()->json([
            'electiveRulesEnabled' => true,
            'constraintsEnabled' => true,
            'blacklistsEnabled' => true,
            'concentrationsEnabled' => true,
        ]);
    });

    // Student Profile
    Route::get('/student-profile', function (Request $request) {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        return response()->json([
            'advisorInfo' => [
                'name' => $user->name ?? 'Not Assigned',
                'email' => $user->email ?? '',
            ],
        ]);
    });
    
    Route::put('/student-profile', function (Request $request) {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        // Update profile logic here if needed
        return response()->json(['message' => 'Profile updated successfully']);
    });

    // Curriculum (CurriculumController) - separate from Curricula
    Route::post('/curriculum/upload', [CurriculumController::class, 'upload']);
    Route::get('/curriculum/bscs2022', [CurriculumController::class, 'bscs2022']);
    Route::get('/curriculum/template', [CurriculumController::class, 'template']);
    Route::get('/curriculum/{id}', [CurriculumController::class, 'show']);
    Route::put('/curriculum/{id}', [CurriculumController::class, 'update']);
    Route::delete('/curriculum/{id}', [CurriculumController::class, 'destroy']);
    Route::get('/curriculum/{id}/courses', [CurriculumController::class, 'courses']);
    Route::post('/curriculum/{id}/courses', [CurriculumController::class, 'addCourse']);
    Route::delete('/curriculum/{id}/courses/{courseId}', [CurriculumController::class, 'removeCourse']);

    // Elective Rules
    Route::prefix('curricula/{curriculum}')->group(function () {
        Route::get('/elective-rules', [ElectiveRuleController::class, 'index']);
        Route::post('/elective-rules', [ElectiveRuleController::class, 'store']);
        Route::put('/elective-rules/settings', [ElectiveRuleController::class, 'updateSettings']);
        Route::put('/elective-rules/{rule}', [ElectiveRuleController::class, 'update']);
        Route::delete('/elective-rules/{rule}', [ElectiveRuleController::class, 'destroy']);
    });

    // Download
    Route::get('/download/sample-xlsx', [DownloadController::class, 'sampleXlsx']);
    Route::get('/download/sample-csv', [DownloadController::class, 'sampleCsv']);
    Route::get('/download/tentative-schedule-template', [DownloadController::class, 'tentativeScheduleTemplate']);

    // Student Management (Chairperson)
    Route::get('/students', [StudentController::class, 'index']);
    Route::get('/students/{id}/progress', [StudentController::class, 'progress']);
    Route::get('/students/{id}/planned-courses', [StudentController::class, 'plannedCourses']);
    Route::put('/students/{id}/update', [StudentController::class, 'update']);

    // Tentative Schedules (Chairperson)
    Route::prefix('tentative-schedules')->group(function () {
        Route::get('/', [TentativeScheduleController::class, 'index']);
        Route::post('/', [TentativeScheduleController::class, 'store']);
        Route::get('/{id}', [TentativeScheduleController::class, 'show']);
        Route::put('/{id}', [TentativeScheduleController::class, 'update']);
        Route::delete('/{id}', [TentativeScheduleController::class, 'destroy']);
    });

});
