<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SetTenantSchemaMiddleware
{
    /**
     * Handle an incoming request for Vendor portal.
     * Enforces active business status and automatically applies PostgreSQL search_path.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        // If user is a vendor (user_type == 2)
        if ((int) $user->user_type === 2) {
            $business = Business::where('owner_id', $user->id)->first();

            // If business does not exist or was soft-deleted
            if (!$business) {
                // Revoke token if active
                if ($request->user()->currentAccessToken()) {
                    $request->user()->currentAccessToken()->delete();
                }

                return response()->json([
                    'status' => false,
                    'message' => 'Your business has been deleted or removed. Access denied.',
                ], 403);
            }

            // If business is blocked/suspended
            if ($business->status === 'blocked') {
                return response()->json([
                    'status' => false,
                    'message' => 'Your business is currently blocked/suspended by the administrator. Access denied.',
                ], 403);
            }

            // Apply PostgreSQL tenant schema
            $schemaName = $business->schema_name ?: 'public';
            $schemaName = preg_replace('/[^a-zA-Z0-9_]/', '', $schemaName);

            try {
                DB::statement("SET search_path TO \"{$schemaName}\", public");
            } catch (\Throwable $e) {
                // Fallback to public if schema not found
                DB::statement("SET search_path TO public");
            }

            // Bind current business to request for controller convenience
            $request->attributes->set('current_business', $business);
            $request->attributes->set('tenant_schema', $schemaName);
        }

        return $next($request);
    }
}
