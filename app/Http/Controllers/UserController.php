<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::with(['faculty', 'department', 'advisor'])->get();
        return response()->json($users);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users,email|max:255',
            'password' => 'required|string|min:8',
            'role' => 'required|in:STUDENT,ADVISOR,CHAIRPERSON,SUPER_ADMIN',
            'faculty_id' => 'required|string|exists:faculties,id',
            'department_id' => 'required|string|exists:departments,id',
            'advisor_id' => 'nullable|string|exists:users,id',
            'gpa' => 'nullable|numeric|between:0,4.0',
            'credits' => 'nullable|integer|min:0',
            'scholarship_hour' => 'nullable|integer|min:0',
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $user = User::create($validated);
        
        return response()->json($user, 201);
    }
    public function show(User $user): JsonResponse
    {
        $user->load(['faculty', 'department', 'advisor', 'students', 'studentCourses']);
        return response()->json($user);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'string|max:255',
            'email' => 'string|email|unique:users,email,' . $user->id . '|max:255',
            'role' => 'in:STUDENT,ADVISOR,CHAIRPERSON,SUPER_ADMIN',
            'faculty_id' => 'string|exists:faculties,id',
            'department_id' => 'string|exists:departments,id',
            'advisor_id' => 'nullable|string|exists:users,id',
            'gpa' => 'nullable|numeric|between:0,4.0',
            'credits' => 'nullable|integer|min:0',
            'scholarship_hour' => 'nullable|integer|min:0',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);
        return response()->json($user);
    }
    public function destroy(User $user): JsonResponse
    {
        $user->delete();
        return response()->json(['message' => 'User deleted successfully']);
    }
}