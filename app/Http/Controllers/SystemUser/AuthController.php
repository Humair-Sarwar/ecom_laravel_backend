<?php

namespace App\Http\Controllers\SystemUser;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Dedicated System User Login (user_type = 3)
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:5',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()->all()
            ], 422);
        }

        if (!Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid email or password'
            ], 401);
        }

        $user = User::where('email', $request->email)->first();

        // Check if user is a System User (user_type == 3)
        if ((int) $user->user_type !== 3) {
            return response()->json([
                'status' => false,
                'message' => 'Access denied. This login portal is strictly for System Users.'
            ], 403);
        }

        // Generate Sanctum token
        $token = $user->createToken('systemuser_auth_token')->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => 'System User logged in successfully',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'user_type' => (int) $user->user_type,
            ]
        ], 200);
    }

    /**
     * System User Logout
     */
    public function logout(Request $request)
    {
        if ($request->user()) {
            $request->user()->currentAccessToken()->delete();
        }

        return response()->json([
            'status' => true,
            'message' => 'System User logged out successfully'
        ], 200);
    }
}
