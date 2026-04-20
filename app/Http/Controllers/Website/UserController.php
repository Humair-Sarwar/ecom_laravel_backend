<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function index(){
        $data = User::all();
        return response()->json([
            'status' => 'success',
            'message' => 'User API is working fine',
            'data' => $data
        ], 200);
    }

    public function register(Request $request){
        $validateUser = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:5',
            'confirm_password' => 'required|same:password',
        ], ['email.unique' => 'The email already exists. Please choose a different email.']);

        if($validateUser->fails()){
            return response()->json([
                'status' => false,
                'message' => 'validation error',
                'errors' => $validateUser->errors()->all()
            ], 422);
        }

        User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => $request->password,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'User registered successfully'
        ], 201);
    }

    public function login(Request $request){
        $validateUser = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:5',
        ]);

        if($validateUser->fails()){
            return response()->json([
                'status' => false,
                'message' => 'validation error',
                'errors' => $validateUser->errors()->all()
            ], 422);
        }
        if(!Auth::attempt(['email' => $request->email, 'password' => $request->password])){
            return response()->json([
                'status' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }
        $user = User::where('email', $request->email)->first();
        $token = $user->createToken('auth_token')->plainTextToken;
        return response()->json([
            'status' => true,
            'message' => 'User logged in successfully',
            'token' => $token,
            'token_type' => 'Bearer',
            // 'user_type' => $user->user_type
            'user' => [
            'id' => $user->id,
            'email' => $user->email,
            'user_type' => $user->user_type
        ]
        ], 200);
    }

    public function logout(Request $request){
        $request->user()->currentAccessToken()->delete();
        return response()->json([
            'status' => true,
            'message' => 'User logged out successfully'
        ], 200);
    }
}
