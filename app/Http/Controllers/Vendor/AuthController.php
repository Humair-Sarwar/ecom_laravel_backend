<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Business;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Dedicated Vendor / Staff Login
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

        // Check if user is vendor or staff (not a customer user_type == 1)
        if ((int) $user->user_type === 1) {
            return response()->json([
                'status' => false,
                'message' => 'Access denied. This login portal is for Vendors and Staff only.'
            ], 403);
        }

        // For Vendors (user_type == 2): Verify that their business exists, is not deleted, and is active
        if ((int) $user->user_type === 2) {
            // Find businesses owned by this vendor (including soft-deleted to check removal status)
            $allBusinesses = Business::withTrashed()->where('owner_id', $user->id)->get();

            if ($allBusinesses->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No business found associated with this account. Access denied.'
                ], 403);
            }

            // Check if all businesses are deleted
            $nonDeletedBusinesses = $allBusinesses->whereNull('deleted_at');
            if ($nonDeletedBusinesses->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Your business has been deleted or deactivated. Access denied.'
                ], 403);
            }

            // Check if any business is active (not blocked)
            $activeBusiness = $nonDeletedBusinesses->firstWhere('status', 'active');
            if (!$activeBusiness) {
                return response()->json([
                    'status' => false,
                    'message' => 'Your business account is currently blocked/suspended. Please contact the administrator.'
                ], 403);
            }
        }

        // Generate Sanctum token
        $token = $user->createToken('vendor_auth_token')->plainTextToken;

        // Fetch active business details if vendor
        $businessInfo = null;
        $roleInfo = null;
        $permissions = [];

        if ((int) $user->user_type === 2) {
            $activeBiz = Business::where('owner_id', $user->id)->where('status', 'active')->first();
            if ($activeBiz) {
                if (!empty($activeBiz->role_id)) {
                    $role = Role::where('is_deleted', false)->where('is_active', true)->find($activeBiz->role_id);
                    if ($role) {
                        $roleInfo = [
                            'id' => $role->id,
                            'title' => $role->title,
                        ];
                        // Only permissions that have been synchronized take effect on the vendor portal
                        $assignedPermissions = is_array($role->synced_permissions)
                            ? $role->synced_permissions
                            : (is_array($role->permissions) ? $role->permissions : []);
                        $permissions = $assignedPermissions;
                    }
                }

                $businessInfo = [
                    'id' => $activeBiz->id,
                    'name' => $activeBiz->name,
                    'branch_name' => $activeBiz->branch_name,
                    'schema_name' => $activeBiz->schema_name ?: 'public',
                    'status' => $activeBiz->status,
                    'package_level' => $activeBiz->package_level,
                    'currency' => $activeBiz->currency,
                    'role_id' => $activeBiz->role_id,
                    'role_title' => $activeBiz->role_title ?: ($roleInfo ? $roleInfo['title'] : null),
                ];
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'Vendor logged in successfully',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'user_type' => (int) $user->user_type,
            ],
            'business' => $businessInfo,
            'role' => $roleInfo,
            'permissions' => $permissions,
        ], 200);
    }

    /**
     * Get Current Authenticated Vendor User & Business Profile + Permissions
     */
    public function me(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $activeBiz = Business::where('owner_id', $user->id)->where('status', 'active')->first();
        $roleInfo = null;
        $permissions = [];

        if ($activeBiz && !empty($activeBiz->role_id)) {
            $role = Role::where('is_deleted', false)->where('is_active', true)->find($activeBiz->role_id);
            if ($role) {
                $roleInfo = [
                    'id' => $role->id,
                    'title' => $role->title,
                ];
                // Only permissions that have been synchronized take effect on the vendor portal
                $assignedPermissions = is_array($role->synced_permissions)
                    ? $role->synced_permissions
                    : (is_array($role->permissions) ? $role->permissions : []);
                $permissions = $assignedPermissions;
            }
        }

        $businessInfo = null;
        if ($activeBiz) {
            $businessInfo = [
                'id' => $activeBiz->id,
                'name' => $activeBiz->name,
                'branch_name' => $activeBiz->branch_name,
                'schema_name' => $activeBiz->schema_name ?: 'public',
                'status' => $activeBiz->status,
                'package_level' => $activeBiz->package_level,
                'currency' => $activeBiz->currency,
                'role_id' => $activeBiz->role_id,
                'role_title' => $activeBiz->role_title ?: ($roleInfo ? $roleInfo['title'] : null),
            ];
        }

        return response()->json([
            'status' => true,
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'user_type' => (int) $user->user_type,
            ],
            'business' => $businessInfo,
            'role' => $roleInfo,
            'permissions' => $permissions,
        ], 200);
    }

    /**
     * Vendor Logout
     */
    public function logout(Request $request)
    {
        if ($request->user()) {
            $request->user()->currentAccessToken()->delete();
        }

        return response()->json([
            'status' => true,
            'message' => 'Vendor logged out successfully'
        ], 200);
    }
}
