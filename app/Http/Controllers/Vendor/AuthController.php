<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Business;
use App\Models\BusinessGroup;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Fetch all businesses accessible to a vendor user (directly owned or via an active Business Group they own).
     */
    private function getAccessibleBusinesses(User $user, bool $withTrashed = false)
    {
        $ownedGroupIds = BusinessGroup::where('owner_id', $user->id)
            ->where('status', 'active')
            ->pluck('id');

        $query = $withTrashed ? Business::withTrashed() : Business::query();

        return $query->where(function ($q) use ($user, $ownedGroupIds) {
            $q->where('owner_id', $user->id);
            if ($ownedGroupIds->isNotEmpty()) {
                $q->orWhereIn('business_group_id', $ownedGroupIds);
            }
        })->orderBy('id', 'asc')->get();
    }

    /**
     * Format business & role payload consistently.
     */
    private function formatBusinessAndRole(Business $activeBiz): array
    {
        $roleInfo = null;
        $permissions = [];

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

        return [$businessInfo, $roleInfo, $permissions];
    }

    /**
     * Format a lightweight business list item for switcher UI.
     */
    private function formatBusinessSummary(Business $b): array
    {
        return [
            'id' => $b->id,
            'name' => $b->name,
            'branch_name' => $b->branch_name,
            'schema_name' => $b->schema_name ?: 'public',
            'display_name' => $b->branch_name
                ? "{$b->name} - {$b->branch_name}"
                : $b->name,
        ];
    }

    /**
     * Dedicated Vendor / Staff Login
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:5',
            'business_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()->all()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid email or password'
            ], 401);
        }

        // Check if user is vendor or staff (not a customer user_type == 1)
        if ((int) $user->user_type === 1) {
            return response()->json([
                'status' => false,
                'message' => 'Access denied. This login portal is for Vendors and Staff only.'
            ], 403);
        }

        $selectedBiz = null;

        // For Vendors (user_type == 2): Verify that their business exists, is not deleted, and is active
        if ((int) $user->user_type === 2) {
            // Find businesses accessible by this vendor (including soft-deleted to check removal status)
            $allBusinesses = $this->getAccessibleBusinesses($user, true);

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
                    'message' => 'Your business has been deleted or removed. Access denied.'
                ], 403);
            }

            // Check if any business is active (not blocked)
            $activeBusinesses = $nonDeletedBusinesses->where('status', 'active')->values();
            if ($activeBusinesses->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Your business account is currently blocked/suspended. Please contact the administrator.'
                ], 403);
            }

            $requestedBusinessId = $request->input('business_id');

            // If vendor has 2 or more active businesses and no business_id was selected yet, prompt selection
            if (empty($requestedBusinessId) && $activeBusinesses->count() >= 2) {
                return response()->json([
                    'status' => true,
                    'requires_business_selection' => true,
                    'message' => 'Please select which business you would like to manage.',
                    'businesses' => $activeBusinesses->map(fn ($b) => $this->formatBusinessSummary($b))->values(),
                    'user' => [
                        'id' => $user->id,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'email' => $user->email,
                        'business_name' => $user->business_name,
                        'user_type' => (int) $user->user_type,
                    ],
                ], 200);
            }

            if (!empty($requestedBusinessId)) {
                $selectedBiz = $activeBusinesses->firstWhere('id', (int) $requestedBusinessId);
                if (!$selectedBiz) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Selected business is invalid, inactive, or not accessible.'
                    ], 403);
                }
            } else {
                $selectedBiz = $activeBusinesses->first();
            }
        }

        // Generate Sanctum token scoped to the selected business if applicable
        $abilities = $selectedBiz ? ['business:' . $selectedBiz->id] : ['*'];
        $token = $user->createToken('vendor_auth_token', $abilities)->plainTextToken;

        // Fetch active business details if vendor
        $businessInfo = null;
        $roleInfo = null;
        $permissions = [];

        if ($selectedBiz) {
            [$businessInfo, $roleInfo, $permissions] = $this->formatBusinessAndRole($selectedBiz);
        }

        $accessibleList = [];
        if ((int) $user->user_type === 2 && isset($activeBusinesses)) {
            $accessibleList = $activeBusinesses->map(fn ($b) => $this->formatBusinessSummary($b))->values();
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
                'business_name' => $user->business_name,
                'user_type' => (int) $user->user_type,
            ],
            'business' => $businessInfo,
            'businesses' => $accessibleList,
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

        $activeBusinesses = (int) $user->user_type === 2
            ? $this->getAccessibleBusinesses($user, false)->where('status', 'active')->values()
            : collect();

        $activeBiz = $request->attributes->get('current_business');
        if (!$activeBiz && $activeBusinesses->isNotEmpty()) {
            $activeBiz = $activeBusinesses->first();
        }

        $roleInfo = null;
        $permissions = [];
        $businessInfo = null;

        if ($activeBiz) {
            [$businessInfo, $roleInfo, $permissions] = $this->formatBusinessAndRole($activeBiz);
        }

        return response()->json([
            'status' => true,
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'business_name' => $user->business_name,
                'user_type' => (int) $user->user_type,
            ],
            'business' => $businessInfo,
            'businesses' => $activeBusinesses->map(fn ($b) => $this->formatBusinessSummary($b))->values(),
            'role' => $roleInfo,
            'permissions' => $permissions,
        ], 200);
    }

    /**
     * Switch active business for the currently authenticated vendor user
     */
    public function switchBusiness(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'business_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()->all(),
            ], 422);
        }

        $user = $request->user();
        if (!$user || (int) $user->user_type !== 2) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $activeBusinesses = $this->getAccessibleBusinesses($user, false)
            ->where('status', 'active')
            ->values();

        $targetBiz = $activeBusinesses->firstWhere('id', (int) $request->input('business_id'));
        if (!$targetBiz) {
            return response()->json([
                'status' => false,
                'message' => 'Selected business is invalid, inactive, or not accessible.',
            ], 403);
        }

        // Rotate Sanctum token scoped to the newly selected business
        $currentToken = $user->currentAccessToken();
        if ($currentToken instanceof \Laravel\Sanctum\PersonalAccessToken) {
            $currentToken->delete();
        }
        $newToken = $user->createToken('vendor_auth_token', ['business:' . $targetBiz->id])->plainTextToken;

        [$businessInfo, $roleInfo, $permissions] = $this->formatBusinessAndRole($targetBiz);

        return response()->json([
            'status' => true,
            'message' => 'Switched business successfully',
            'token' => $newToken,
            'token_type' => 'Bearer',
            'business' => $businessInfo,
            'businesses' => $activeBusinesses->map(fn ($b) => $this->formatBusinessSummary($b))->values(),
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
