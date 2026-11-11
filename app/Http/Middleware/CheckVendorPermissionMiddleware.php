<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Business;
use App\Models\Role;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckVendorPermissionMiddleware
{
    /**
     * Handle an incoming request for Vendor portal role privilege checks.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$permissions  One or more required permission slugs (e.g. 'warranty_view')
     */
    public function handle(Request $request, Closure $next, ...$permissions): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        // Retrieve the current active business for this vendor
        $business = $request->attributes->get('current_business');
        if (!$business) {
            $business = Business::where('owner_id', $user->id)
                ->where('status', 'active')
                ->first();
        }

        if (!$business) {
            return response()->json([
                'status' => false,
                'message' => 'Active business not found. Access denied.',
            ], 403);
        }

        // Verify assigned role
        if (empty($business->role_id)) {
            return response()->json([
                'status' => false,
                'message' => 'No role assigned to this business account. Please contact the administrator.',
            ], 403);
        }

        $role = Role::where('is_deleted', false)->find($business->role_id);
        if (!$role || !$role->is_active) {
            return response()->json([
                'status' => false,
                'message' => 'The assigned role is inactive or could not be found.',
            ], 403);
        }

        // Use synced_permissions (only applied after administrator clicks Sync)
        $assignedPermissions = is_array($role->synced_permissions)
            ? $role->synced_permissions
            : (is_array($role->permissions) ? $role->permissions : []);

        // Verify each requested permission is granted
        foreach ($permissions as $permission) {
            $trimmed = trim($permission);
            if (!empty($trimmed) && !in_array($trimmed, $assignedPermissions, true)) {
                return response()->json([
                    'status' => false,
                    'message' => "Access denied. You lack the '{$trimmed}' privilege to perform this action.",
                    'required_privilege' => $trimmed,
                ], 403);
            }
        }

        return $next($request);
    }
}
