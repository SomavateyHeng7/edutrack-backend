<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\FacultyController;
use App\Http\Controllers\API\DepartmentController;
use App\Http\Controllers\API\CurriculumController;
use App\Http\Controllers\API\CourseController;
use App\Http\Controllers\API\ConcentrationController;
use App\Http\Controllers\API\UserController;

// Authentication routes
Route::post('/login', [AuthController::class, 'login']);
Route::get('public-departments', [DepartmentController::class, 'publicIndex']);
Route::get('public-curricula', [CurriculumController::class, 'publicIndex']);

// Protected routes
Route::middleware(['auth:sanctum'])->group(function () {
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return response()->json($request->user()->load(['faculty', 'department']));
    });
    
    // Faculties
    Route::get('/faculties', [FacultyController::class, 'index']);
    Route::post('/faculties', [FacultyController::class, 'store']);
    Route::get('/faculties/{id}', [FacultyController::class, 'show']);
    Route::put('/faculties/{id}', [FacultyController::class, 'update']);
    Route::delete('/faculties/{id}', [FacultyController::class, 'destroy']);
    
    // Departments
    Route::get('/departments', [DepartmentController::class, 'index']);
    Route::post('/departments', [DepartmentController::class, 'store']);
    Route::get('/departments/{id}', [DepartmentController::class, 'show']);
    Route::put('/departments/{id}', [DepartmentController::class, 'update']);
    Route::delete('/departments/{id}', [DepartmentController::class, 'destroy']);
    
    // Curricula
    Route::get('/curricula', [CurriculumController::class, 'index']);
    Route::post('/curricula', [CurriculumController::class, 'store']);
    Route::get('/curricula/{id}', [CurriculumController::class, 'show']);
    Route::put('/curricula/{id}', [CurriculumController::class, 'update']);
    Route::delete('/curricula/{id}', [CurriculumController::class, 'destroy']);
    Route::post('/curricula/{id}/upload', [CurriculumController::class, 'uploadCourses']);
    
    // Courses
    Route::get('/courses', [CourseController::class, 'index']);
    Route::post('/courses', [CourseController::class, 'store']);
    Route::get('/courses/{id}', [CourseController::class, 'show']);
    Route::put('/courses/{id}', [CourseController::class, 'update']);
    Route::delete('/courses/{id}', [CourseController::class, 'destroy']);
    Route::post('/courses/bulk-create', [CourseController::class, 'bulkCreate']);
    
    // Concentrations
    Route::get('/concentrations', [ConcentrationController::class, 'index']);
    Route::post('/concentrations', [ConcentrationController::class, 'store']);
    Route::get('/concentrations/{id}', [ConcentrationController::class, 'show']);
    Route::put('/concentrations/{id}', [ConcentrationController::class, 'update']);
    Route::delete('/concentrations/{id}', [ConcentrationController::class, 'destroy']);
    
    // Users
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
});