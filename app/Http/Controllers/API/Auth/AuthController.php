<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Http\Controllers\Controller;
use App\Models\User;

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

            // Support both plaintext and hashed passwords (matching your NextAuth logic)
            $passwordValid = $request->password === $user->password || 
                            Hash::check($request->password, $user->password);

            if (!$passwordValid) {
                throw ValidationException::withMessages([
                    'password' => ['Invalid password'],
                ]);
            }

            // Create token
            $token = $user->createToken('auth-token')->plainTextToken;

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
                'token' => $token
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
            $request->user()->currentAccessToken()->delete();
            
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