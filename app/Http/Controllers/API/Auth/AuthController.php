<?php

namespace App\Http\Controllers\API\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AuditLog;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::with(['faculty', 'department'])
                ->where('email', $request->email)
                ->first();

            if (!$user) {
                throw ValidationException::withMessages([
                    'email' => ['No user found with this email'],
                ]);
            }

            // Support both plaintext and hashed passwords
            $passwordValid = $request->password === $user->password || 
                            Hash::check($request->password, $user->password);

            if (!$passwordValid) {
                throw ValidationException::withMessages([
                    'password' => ['Invalid password'],
                ]);
            }

            // Use Laravel's built-in session-based authentication for SPA
            auth()->guard('web')->login($user);

            // Log login for chairperson and super admin users
            if (in_array($user->role, ['CHAIRPERSON', 'SUPER_ADMIN', 'ADVISOR'])) {
                try {
                    AuditLog::create([
                        'user_id' => $user->id,
                        'entity_type' => 'AUTH',
                        'entity_id' => $user->id,
                        'action' => 'LOGIN',
                        'description' => "{$user->name} ({$user->role}) logged in",
                        'ip_address' => $request->ip(),
                    ]);
                } catch (\Exception $e) {
                    // Don't fail login if audit logging fails
                    \Illuminate\Support\Facades\Log::warning('Failed to create login audit log: ' . $e->getMessage());
                }
            }

            return response()->json([
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'name' => $user->name,
                    'role' => $user->role,
                    'faculty_id' => $user->faculty_id,
                    'department_id' => $user->department_id,
                    'faculty' => $user->faculty,
                    'department' => $user->department,
                ],
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Authentication failed',
                'messages' => $e->errors()
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Login failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        try {
            $user = auth()->guard('web')->user();
            
            // Log logout for chairperson and super admin users
            if ($user && in_array($user->role, ['CHAIRPERSON', 'SUPER_ADMIN', 'ADVISOR'])) {
                try {
                    AuditLog::create([
                        'user_id' => $user->id,
                        'entity_type' => 'AUTH',
                        'entity_id' => $user->id,
                        'action' => 'LOGOUT',
                        'description' => "{$user->name} ({$user->role}) logged out",
                        'ip_address' => $request->ip(),
                    ]);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning('Failed to create logout audit log: ' . $e->getMessage());
                }
            }

            auth()->guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            
            return response()->json([
                'message' => 'Successfully logged out'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Logout failed'
            ], 500);
        }
    }
}