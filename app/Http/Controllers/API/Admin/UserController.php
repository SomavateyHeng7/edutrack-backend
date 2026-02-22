<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Faculty;
use App\Models\AuditLog;
use App\Models\Blacklist;
use App\Models\Concentration;
use App\Models\Curriculum;
use App\Models\DepartmentCourseType;

class UserController extends Controller
{
    // DELETE: /api/users/{userId}
    public function destroy(Request $request, $userId)
    {
        try {
            $user = Auth::user();
            if (!$user || $user->role !== 'SUPER_ADMIN') {
                return response()->json(['error' => 'Unauthorized - Super Admin access required'], 401);
            }
            if (!$userId) {
                return response()->json(['error' => 'Missing required field: userId'], 400);
            }

            $userToDelete = User::find($userId);
            if (!$userToDelete) {
                return response()->json(['error' => 'User not found'], 404);
            }

            DB::transaction(function () use ($userId) {
                // Delete all audit logs for the user
                AuditLog::where('user_id', $userId)->delete();

                // Nullify foreign key references so deletion doesn't fail
                Blacklist::where('created_by_id', $userId)->update(['created_by_id' => null]);
                Curriculum::where('created_by_id', $userId)->update(['created_by_id' => null]);
                Concentration::where('created_by_id', $userId)->update(['created_by_id' => null]);
                DepartmentCourseType::where('assigned_by_id', $userId)->update(['assigned_by_id' => null]);

                // Remove advisor references from other users
                User::where('advisor_id', $userId)->update(['advisor_id' => null]);

                // Delete the user
                User::where('id', $userId)->delete();
            });

            return response()->json(['message' => 'User and related audit logs deleted successfully'], 200);

        } catch (\Exception $e) {
            Log::error('Error deleting user and audit logs: ' . $e->getMessage());
            return response()->json(['error' => 'Error deleting user and audit logs'], 500);
        }
    }

    // GET: /api/users
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user || $user->role !== 'SUPER_ADMIN') {
                return response()->json(['error' => 'Unauthorized - Super Admin access required'], 401);
            }

            $users = User::with(['faculty:id,name'])
                ->orderByDesc('created_at')
                ->get();
            return response()->json(['users' => $users], 200);

        } catch (\Exception $e) {
            Log::error('Error fetching users: '.$e->getMessage());
            return response()->json(['error' => 'Failed to fetch users'], 500);
        }
    }

    // POST: /api/users
    public function store(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user || $user->role !== 'SUPER_ADMIN') {
                return response()->json(['error' => 'Unauthorized - Super Admin access required'], 401);
            }

            $data = $request->all();
            $required = ['name', 'email', 'role', 'facultyId', 'departmentId'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return response()->json([
                        'error' => 'Missing required fields: name, email, role, facultyId, departmentId'
                    ], 400);
                }
            }

            // Check if user already exists
            if (User::where('email', $data['email'])->exists()) {
                return response()->json(['error' => 'User already exists'], 400);
            }

            // Check faculty
            $faculty = Faculty::with(['departments'])->find($data['facultyId']);
            if (!$faculty) {
                return response()->json(['error' => 'Invalid faculty'], 400);
            }

            // Check department
            $departmentExists = $faculty->departments->contains('id', $data['departmentId']);
            if (!$departmentExists) {
                return response()->json([
                    'error' => 'Invalid department or department does not belong to the specified faculty'
                ], 400);
            }

            // Use provided password or generate a temporary one
            $plainPassword = $data['password'] ?? Str::random(8);

            // Create user (with hashed password for security)
            $newUser = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($plainPassword),
                'role' => $data['role'],
                'faculty_id' => $data['facultyId'],
                'department_id' => $data['departmentId'],
            ]);

            // Load faculty and department for response
            $newUser->load(['faculty:id,name', 'department:id,name']);

            // Do not return password in response
            $userData = $newUser->toArray();
            unset($userData['password']);

            return response()->json([
                'message' => 'User created successfully',
                'user' => $userData,
                'plainPassword' => $plainPassword // In production, send via email instead
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error creating user: ' . $e->getMessage());
            return response()->json(['error' => 'Error creating user'], 500);
        }
    }

    public function update(Request $request, $userId)
    {
        try {
            $user = Auth::user();
            if (!$user || $user->role !== 'SUPER_ADMIN') {
                return response()->json(['error' => 'Unauthorized - Super Admin access required'], 401);
            }

            $data = $request->all();
            $userToUpdate = User::find($userId);
            if (!$userToUpdate) {
                return response()->json(['error' => 'User not found'], 404);
            }

            // Update fields if provided
            foreach (['name', 'email', 'role', 'facultyId', 'departmentId'] as $field) {
                if (isset($data[$field])) {
                    if ($field === 'facultyId') {
                        $userToUpdate->faculty_id = $data[$field];
                    } elseif ($field === 'departmentId') {
                        $userToUpdate->department_id = $data[$field];
                    } else {
                        $userToUpdate->$field = $data[$field];
                    }
                }
            }

            // Update password if provided
            if (!empty($data['password'])) {
                $userToUpdate->password = Hash::make($data['password']);
            }

            $userToUpdate->save();

            // Load faculty and department for response
            $userToUpdate->load(['faculty:id,name', 'department:id,name']);

            // Do not return password in response
            $userData = $userToUpdate->toArray();
            unset($userData['password']);

            return response()->json([
                'message' => 'User updated successfully',
                'user' => $userData,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error updating user: ' . $e->getMessage());
            return response()->json(['error' => 'Error updating user'], 500);
        }
    }
}
