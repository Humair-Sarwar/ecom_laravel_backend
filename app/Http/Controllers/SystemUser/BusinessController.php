<?php

namespace App\Http\Controllers\SystemUser;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\User;
use App\Models\Role;
use App\Models\Country;
use App\Models\City;
use App\Services\TenantSchemaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BusinessController extends Controller
{
    /**
     * Display a listing of businesses with tab counts and filters.
     */
    public function index(Request $request)
    {
        $tab = $request->query('tab', 'all'); // 'all', 'active', 'blocked', 'deleted'
        $search = trim($request->query('search', ''));
        $ownerId = $request->query('owner_id', '');
        $schemaName = $request->query('schema_name', '');
        $packageLevel = $request->query('package_level', '');
        $viewMode = $request->query('view_mode', 'businesses'); // 'businesses' or 'by_owner'
        $perPage = (int) $request->query('per_page', 10);

        // Calculate counts for all tabs
        $allCount = Business::count();
        $activeCount = Business::where('status', 'active')->count();
        $blockedCount = Business::where('status', 'blocked')->count();
        $deletedCount = Business::onlyTrashed()->count();

        // Build base query
        if ($tab === 'deleted') {
            $query = Business::onlyTrashed()->with(['owner', 'role', 'country', 'city']);
        } else {
            $query = Business::with(['owner', 'role', 'country', 'city']);

            if ($tab === 'active') {
                $query->where('status', 'active');
            } elseif ($tab === 'blocked') {
                $query->where('status', 'blocked');
            }
        }

        // Apply filters
        if (!empty($ownerId) && $ownerId !== 'all') {
            $query->where('owner_id', $ownerId);
        }

        if (!empty($schemaName) && $schemaName !== 'all') {
            $query->where('schema_name', $schemaName);
        }

        if (!empty($packageLevel) && $packageLevel !== 'all') {
            $query->where('package_level', $packageLevel);
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('branch_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('mobile_no', 'like', "%{$search}%")
                  ->orWhere('whatsapp_no', 'like', "%{$search}%")
                  ->orWhere('schema_name', 'like', "%{$search}%")
                  ->orWhereHas('owner', function ($oq) use ($search) {
                      $oq->where('first_name', 'like', "%{$search}%")
                         ->orWhere('last_name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        // Check if grouped by owner view is requested
        if ($viewMode === 'by_owner') {
            $businesses = $query->orderBy('created_at', 'desc')->get();
            $grouped = $businesses->groupBy('owner_id')->map(function ($items, $key) {
                $owner = $items->first()->owner;
                return [
                    'owner_id' => $key,
                    'owner' => $owner,
                    'businesses_count' => $items->count(),
                    'businesses' => $items,
                ];
            })->values();

            return response()->json([
                'status' => true,
                'view_mode' => 'by_owner',
                'counts' => [
                    'all' => $allCount,
                    'active' => $activeCount,
                    'blocked' => $blockedCount,
                    'deleted' => $deletedCount,
                ],
                'data' => $grouped,
            ]);
        }

        // Standard paginated listing
        $paginated = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'status' => true,
            'view_mode' => 'businesses',
            'counts' => [
                'all' => $allCount,
                'active' => $activeCount,
                'blocked' => $blockedCount,
                'deleted' => $deletedCount,
            ],
            'data' => $paginated->items(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'per_page' => $paginated->perPage(),
            'total' => $paginated->total(),
        ]);
    }

    /**
     * Get form options for create and update dropdowns.
     */
    public function formOptions()
    {
        // Platform users available as business owners (exclude System Users user_type == 3)
        $owners = User::where('user_type', '!=', 3)
            ->select('id', 'first_name', 'last_name', 'email', 'phone', 'business_name', 'user_type')
            ->orderBy('first_name', 'asc')
            ->get();

        // Roles (all created roles)
        $roles = Role::where('is_deleted', false)
            ->select('id', 'title', 'slug', 'description')
            ->orderBy('title', 'asc')
            ->get();

        // Countries (all created countries)
        $countries = Country::select('id', 'name', 'nice_name', 'iso', 'phone_code', 'currency')
            ->orderBy('name', 'asc')
            ->get();

        // Cities (all created cities)
        $cities = City::select('id', 'country_id', 'name')
            ->orderBy('name', 'asc')
            ->get();

        // Existing schemas for schema dropdown (from PostgreSQL and existing businesses)
        $pgSchemas = TenantSchemaService::getAllSchemas();
        $businessSchemas = Business::whereNotNull('schema_name')
            ->where('schema_name', '!=', '')
            ->distinct()
            ->pluck('schema_name')
            ->toArray();
        $existingSchemas = array_values(array_unique(array_merge($pgSchemas, $businessSchemas)));

        $businessTypes = [
            'Electronics Store',
            'Liquor Store',
            'Apparel Store',
            'Flower Shop',
            'Restaurant / Cafe',
            'Grocery Store',
            'Pharmacy',
            'Repair Center',
            'General Merchandise',
        ];

        $currencies = [
            ['code' => 'GBP', 'name' => 'British Pound (£)'],
            ['code' => 'PKR', 'name' => 'Pakistani Rupee (₨)'],
            ['code' => 'USD', 'name' => 'US Dollar ($)'],
            ['code' => 'EUR', 'name' => 'Euro (€)'],
            ['code' => 'AED', 'name' => 'UAE Dirham (د.إ)'],
            ['code' => 'SAR', 'name' => 'Saudi Riyal (﷼)'],
            ['code' => 'CAD', 'name' => 'Canadian Dollar ($)'],
            ['code' => 'AUD', 'name' => 'Australian Dollar ($)'],
        ];

        return response()->json([
            'status' => true,
            'data' => [
                'owners' => $owners,
                'roles' => $roles,
                'countries' => $countries,
                'cities' => $cities,
                'existing_schemas' => $existingSchemas,
                'business_types' => $businessTypes,
                'currencies' => $currencies,
            ],
        ]);
    }

    /**
     * Store a newly created business.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'business_type' => 'required|string|max:255',
            'branch_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'owner_id' => 'required|exists:users,id',
            'role_id' => 'required|string|exists:roles,id',
            'country_id' => 'required|integer|exists:countries,id',
            'city_id' => 'required|integer|exists:cities,id',
            'services' => 'required|array|min:1',
            'package_level' => 'required|string|in:Basic,Standard,Premium',
            'schema_type' => 'required|string|in:new,existing',
            'schema_name' => 'nullable|string|max:100',
            'mobile_no' => 'nullable|string|max:50',
            'whatsapp_no' => 'nullable|string|max:50',
            'is_system_website' => 'boolean',
            'is_personal_website' => 'boolean',
            'website_url' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'city_name' => 'nullable|string|max:100',
            'state_region' => 'nullable|string|max:100',
            'post_code' => 'nullable|string|max:50',
            'currency' => 'nullable|string|max:10',
            'latitude' => 'nullable|string|max:50',
            'longitude' => 'nullable|string|max:50',
            'description' => 'nullable|string',
        ], [
            'services.required' => 'Please select at least one enabled service.',
            'services.min' => 'Please select at least one enabled service.',
            'role_id.required' => 'Please select a role for the business.',
            'country_id.required' => 'Please select a country.',
            'city_id.required' => 'Please select a city.',
        ]);

        // Auto resolve role title
        if (!empty($validated['role_id'])) {
            $role = Role::find($validated['role_id']);
            $validated['role_title'] = $role ? $role->title : null;
        }

        // Auto resolve country name
        if (!empty($validated['country_id'])) {
            $country = Country::find($validated['country_id']);
            $validated['country_name'] = $country ? $country->name : null;
        }

        // Auto resolve city name
        if (!empty($validated['city_id'])) {
            $city = City::find($validated['city_id']);
            $validated['city_name'] = $city ? $city->name : ($validated['city_name'] ?? null);
        }

        // Sanitize and provision PostgreSQL tenant schema
        $rawSchema = !empty($validated['schema_name']) ? $validated['schema_name'] : 'public';
        $schemaName = TenantSchemaService::sanitizeSchemaName($rawSchema);
        $validated['schema_name'] = $schemaName;

        // If the user selected "new" schema, verify it does not already exist
        if ($validated['schema_type'] === 'new') {
            $businessExistsWithSchema = Business::where('schema_name', $schemaName)->exists();
            $pgSchemaExists = TenantSchemaService::schemaExists($schemaName);

            if ($businessExistsWithSchema || $pgSchemaExists) {
                return response()->json([
                    'status' => false,
                    'message' => "The schema \"{$schemaName}\" already exists. Please choose a different name or select 'Use an existing schema'.",
                    'errors' => [
                        'schema_name' => ["The schema \"{$schemaName}\" already exists."],
                    ],
                ], 422);
            }
        }

        $provisionResult = TenantSchemaService::provisionTenantSchema($schemaName);

        $business = Business::create($validated);

        // When a business is created against business owner, ensure user_type becomes 2 (Business Owner / Vendor)
        if (!empty($validated['owner_id'])) {
            $ownerUser = User::find($validated['owner_id']);
            if ($ownerUser) {
                $ownerUser->user_type = 2;
                if (empty($ownerUser->business_name)) {
                    $ownerUser->business_name = $business->name . ($business->branch_name ? " · {$business->branch_name} owner" : " owner");
                }
                $ownerUser->save();
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'Business and database schema created successfully',
            'schema_provisioned' => $provisionResult,
            'data' => $business->load(['owner', 'role', 'country', 'city']),
        ], 201);
    }

    /**
     * Display the specified business.
     */
    public function show($id)
    {
        $business = Business::withTrashed()->with(['owner', 'role', 'country', 'city'])->find($id);

        if (!$business) {
            return response()->json([
                'status' => false,
                'message' => 'Business not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $business,
        ]);
    }

    /**
     * Update the specified business.
     */
    public function update(Request $request, $id)
    {
        $business = Business::withTrashed()->find($id);

        if (!$business) {
            return response()->json([
                'status' => false,
                'message' => 'Business not found',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'business_type' => 'required|string|max:255',
            'branch_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'owner_id' => 'nullable|exists:users,id',
            'role_id' => 'required|string|exists:roles,id',
            'country_id' => 'required|integer|exists:countries,id',
            'city_id' => 'required|integer|exists:cities,id',
            'services' => 'required|array|min:1',
            'package_level' => 'required|string|in:Basic,Standard,Premium',
            'schema_type' => 'nullable|string',
            'schema_name' => 'nullable|string|max:100',
            'mobile_no' => 'nullable|string|max:50',
            'whatsapp_no' => 'nullable|string|max:50',
            'is_system_website' => 'boolean',
            'is_personal_website' => 'boolean',
            'website_url' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'city_name' => 'nullable|string|max:100',
            'state_region' => 'nullable|string|max:100',
            'post_code' => 'nullable|string|max:50',
            'currency' => 'nullable|string|max:10',
            'latitude' => 'nullable|string|max:50',
            'longitude' => 'nullable|string|max:50',
            'description' => 'nullable|string',
        ], [
            'services.required' => 'Please select at least one enabled service.',
            'services.min' => 'Please select at least one enabled service.',
            'role_id.required' => 'Please select a role for the business.',
            'country_id.required' => 'Please select a country.',
            'city_id.required' => 'Please select a city.',
        ]);

        // Schema and owner are immutable after creation
        unset($validated['schema_name'], $validated['schema_type']);

        if (isset($validated['role_id'])) {
            $role = Role::find($validated['role_id']);
            $validated['role_title'] = $role ? $role->title : null;
        }

        if (isset($validated['country_id'])) {
            $country = Country::find($validated['country_id']);
            $validated['country_name'] = $country ? $country->name : null;
        }

        if (isset($validated['city_id'])) {
            $city = City::find($validated['city_id']);
            $validated['city_name'] = $city ? $city->name : ($validated['city_name'] ?? null);
        }

        $business->update($validated);

        // Ensure user_type is 2 (Business Owner / Vendor) for the assigned owner
        if (!empty($validated['owner_id'])) {
            $ownerUser = User::find($validated['owner_id']);
            if ($ownerUser && $ownerUser->user_type !== 2) {
                $ownerUser->user_type = 2;
                if (empty($ownerUser->business_name)) {
                    $ownerUser->business_name = $business->name . ($business->branch_name ? " · {$business->branch_name} owner" : " owner");
                }
                $ownerUser->save();
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'Business updated successfully',
            'data' => $business->load(['owner', 'role', 'country', 'city']),
        ]);
    }

    /**
     * Toggle active/blocked status for a business.
     */
    public function toggleBlock($id)
    {
        $business = Business::find($id);

        if (!$business) {
            return response()->json([
                'status' => false,
                'message' => 'Business not found',
            ], 404);
        }

        $newStatus = ($business->status === 'active') ? 'blocked' : 'active';
        $business->status = $newStatus;
        $business->save();

        return response()->json([
            'status' => true,
            'message' => "Business status changed to {$newStatus}",
            'data' => [
                'id' => $business->id,
                'status' => $newStatus,
            ],
        ]);
    }

    /**
     * Remove or restore the specified business.
     */
    public function destroy($id)
    {
        $business = Business::withTrashed()->find($id);

        if (!$business) {
            return response()->json([
                'status' => false,
                'message' => 'Business not found',
            ], 404);
        }

        if ($business->trashed()) {
            // Restore if already in trash
            $business->restore();
            return response()->json([
                'status' => true,
                'message' => 'Business restored successfully',
            ]);
        }

        $business->delete(); // Soft delete

        return response()->json([
            'status' => true,
            'message' => 'Business moved to deleted',
        ]);
    }
}
