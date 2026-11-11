<?php

namespace App\Http\Controllers\SystemUser;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\User;
use App\Models\Role;
use App\Models\Country;
use App\Models\City;
use App\Services\TenantSchemaService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get aggregated system stats, recent activities, package breakdown, and timeline analytics.
     */
    public function stats(Request $request)
    {
        // 1. KPI Counts
        $totalBusinesses = Business::count();
        $activeBusinesses = Business::where('status', 'active')->count();
        $blockedBusinesses = Business::where('status', 'blocked')->count();
        $deletedBusinesses = Business::onlyTrashed()->count();

        // Users by type
        $totalUsers = User::where('user_type', '!=', 3)->count();
        $vendorOwners = User::where('user_type', 2)->count();
        $websiteUsers = User::where('user_type', 1)->count();
        $activeUsers = User::where('user_type', '!=', 3)->where('status', true)->count();

        // Multi-tenant PostgreSQL Schemas
        $pgSchemas = TenantSchemaService::getAllSchemas();
        $businessSchemas = Business::whereNotNull('schema_name')
            ->where('schema_name', '!=', '')
            ->distinct()
            ->pluck('schema_name')
            ->toArray();
        $allUniqueSchemas = array_values(array_unique(array_merge($pgSchemas, $businessSchemas)));
        $totalSchemasCount = count($allUniqueSchemas);

        // System Configuration Counts
        $totalRoles = Role::where('is_deleted', false)->count();
        $totalCountries = Country::count();
        $totalCities = City::count();

        // 2. Package Level Distribution (Basic, Standard, Premium)
        $packageBreakdown = Business::select('package_level', DB::raw('count(*) as count'))
            ->groupBy('package_level')
            ->pluck('count', 'package_level')
            ->toArray();

        $packageDistribution = [
            'Basic' => $packageBreakdown['Basic'] ?? 0,
            'Standard' => $packageBreakdown['Standard'] ?? 0,
            'Premium' => $packageBreakdown['Premium'] ?? 0,
        ];

        // 3. Businesses by Type distribution (Top categories)
        $businessTypeDistribution = Business::select('business_type', DB::raw('count(*) as count'))
            ->groupBy('business_type')
            ->orderBy('count', 'desc')
            ->limit(5)
            ->get();

        // 4. Monthly Timeline Data for Chart (Last 6 Months: Businesses created vs Users registered)
        $months = [];
        $businessGrowth = [];
        $userGrowth = [];

        for ($i = 5; $i >= 0; $i--) {
            $monthDate = Carbon::now()->subMonths($i);
            $monthKey = $monthDate->format('M Y');
            $months[] = $monthKey;

            $start = $monthDate->copy()->startOfMonth();
            $end = $monthDate->copy()->endOfMonth();

            $bCount = Business::whereBetween('created_at', [$start, $end])->count();
            $uCount = User::where('user_type', '!=', 3)->whereBetween('created_at', [$start, $end])->count();

            $businessGrowth[] = $bCount;
            $userGrowth[] = $uCount;
        }

        // 5. Recent Businesses (Latest 5)
        $recentBusinesses = Business::with(['owner:id,first_name,last_name,email', 'role:id,title', 'city:id,name', 'country:id,name'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($b) {
                return [
                    'id' => $b->id,
                    'name' => $b->name,
                    'branch_name' => $b->branch_name,
                    'owner_name' => $b->owner ? ($b->owner->first_name . ' ' . $b->owner->last_name) : '—',
                    'owner_email' => $b->owner ? $b->owner->email : '—',
                    'schema_name' => $b->schema_name ?: 'public',
                    'package_level' => $b->package_level,
                    'status' => $b->status,
                    'created_at' => $b->created_at ? $b->created_at->format('M d, Y') : null,
                ];
            });

        // 6. Recent Registered Users (Latest 5)
        $recentUsers = User::where('user_type', '!=', 3)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($u) {
                return [
                    'id' => $u->id,
                    'name' => trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')),
                    'email' => $u->email,
                    'user_type' => (int) $u->user_type,
                    'user_type_label' => (int) $u->user_type === 2 ? 'Business Owner' : 'Website Customer',
                    'business_name' => $u->business_name ?: '—',
                    'status' => (bool) $u->status,
                    'created_at' => $u->created_at ? $u->created_at->format('M d, Y') : null,
                ];
            });

        return response()->json([
            'status' => true,
            'message' => 'System User Dashboard statistics retrieved successfully',
            'data' => [
                'counts' => [
                    'total_businesses' => $totalBusinesses,
                    'active_businesses' => $activeBusinesses,
                    'blocked_businesses' => $blockedBusinesses,
                    'deleted_businesses' => $deletedBusinesses,
                    'total_users' => $totalUsers,
                    'vendor_owners' => $vendorOwners,
                    'website_users' => $websiteUsers,
                    'active_users' => $activeUsers,
                    'total_schemas' => $totalSchemasCount,
                    'total_roles' => $totalRoles,
                    'total_countries' => $totalCountries,
                    'total_cities' => $totalCities,
                    'system_health' => '100% Operational',
                ],
                'package_distribution' => $packageDistribution,
                'business_type_distribution' => $businessTypeDistribution,
                'growth_chart' => [
                    'months' => $months,
                    'businesses' => $businessGrowth,
                    'users' => $userGrowth,
                ],
                'recent_businesses' => $recentBusinesses,
                'recent_users' => $recentUsers,
            ],
        ]);
    }
}
