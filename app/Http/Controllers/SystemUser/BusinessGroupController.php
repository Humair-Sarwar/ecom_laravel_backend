<?php

namespace App\Http\Controllers\SystemUser;

use App\Http\Controllers\Controller;
use App\Models\BusinessGroup;
use App\Models\Business;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BusinessGroupController extends Controller
{
    /**
     * List business groups with tab counts, search, sorting and relationships.
     */
    public function index(Request $request)
    {
        $tab = $request->query('tab', 'all'); // 'all', 'active', 'inactive', 'without_businesses'
        $search = trim($request->query('search', ''));
        $sortBy = $request->query('sort_by', 'newest'); // 'newest', 'oldest', 'name_asc', 'name_desc'
        $perPage = (int) $request->query('per_page', 15);
        if ($perPage <= 0) $perPage = 15;

        // Base query with relationships
        $baseQuery = BusinessGroup::with([
            'owner',
            'businesses' => function ($q) {
                $q->with('owner');
            }
        ]);

        // Metrics / Tab counts
        $allCount = BusinessGroup::count();
        $activeCount = BusinessGroup::where('status', 'active')->count();
        $inactiveCount = BusinessGroup::where('status', 'inactive')->count();
        $withoutBusinessesCount = BusinessGroup::doesntHave('businesses')->count();

        $query = clone $baseQuery;

        // Tab filter
        if ($tab === 'active') {
            $query->where('status', 'active');
        } elseif ($tab === 'inactive') {
            $query->where('status', 'inactive');
        } elseif ($tab === 'without_businesses') {
            $query->doesntHave('businesses');
        }

        // Search group, owner, or business in it
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhereHas('owner', function ($oq) use ($search) {
                      $oq->where('first_name', 'like', "%{$search}%")
                         ->orWhere('last_name', 'like', "%{$search}%")
                         ->orWhereRaw("CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,'')) LIKE ?", ["%{$search}%"])
                         ->orWhere('email', 'like', "%{$search}%")
                         ->orWhere('phone', 'like', "%{$search}%")
                         ->orWhere('business_name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('businesses', function ($bq) use ($search) {
                      $bq->where('name', 'like', "%{$search}%")
                         ->orWhere('branch_name', 'like', "%{$search}%");
                  });
            });
        }

        // Sorting
        if ($sortBy === 'oldest') {
            $query->orderBy('created_at', 'asc');
        } elseif ($sortBy === 'name_asc') {
            $query->orderBy('name', 'asc');
        } elseif ($sortBy === 'name_desc') {
            $query->orderBy('name', 'desc');
        } else {
            // Newest first (default)
            $query->orderBy('created_at', 'desc');
        }

        $paginated = $query->paginate($perPage);

        // Format items to match reference layout
        $formatted = $paginated->getCollection()->map(function ($group) {
            $businesses = $group->businesses ?? collect();
            $totalBiz = $businesses->count();
            $activeBiz = $businesses->where('status', 'active')->count();
            $blockedBiz = $businesses->where('status', 'blocked')->count();

            // Collect distinct business owners
            $ownersMap = [];
            foreach ($businesses as $b) {
                $owner = $b->owner;
                if ($owner) {
                    $oid = $owner->id;
                    if (!isset($ownersMap[$oid])) {
                        $displayName = trim(($owner->first_name ?? '') . ' ' . ($owner->last_name ?? '')) ?: ($owner->business_name ?: $owner->email);
                        $ownersMap[$oid] = [
                            'id' => $owner->id,
                            'name' => $displayName,
                            'email' => $owner->email,
                            'businesses_count' => 0,
                        ];
                    }
                    $ownersMap[$oid]['businesses_count']++;
                }
            }

            $distinctOwners = array_values($ownersMap);
            $ownersCount = count($distinctOwners);

            $groupOwnerName = $group->owner
                ? (trim(($group->owner->first_name ?? '') . ' ' . ($group->owner->last_name ?? '')) ?: ($group->owner->business_name ?: $group->owner->email))
                : '-';

            return [
                'id' => $group->id,
                'name' => $group->name,
                'status' => $group->status,
                'description' => $group->description,
                'created_at' => $group->created_at ? $group->created_at->format('Y-m-d H:i:s') : null,
                'created_formatted' => $group->created_at ? $group->created_at->format('j M Y') : '-',
                'owner' => $group->owner ? [
                    'id' => $group->owner->id,
                    'name' => $groupOwnerName,
                    'email' => $group->owner->email,
                    'phone' => $group->owner->phone,
                ] : null,
                'metrics' => [
                    'businesses_count' => $totalBiz,
                    'active_businesses_count' => $activeBiz,
                    'blocked_businesses_count' => $blockedBiz,
                    'owners_count' => $ownersCount,
                ],
                'businesses' => $businesses->map(function ($b) {
                    $ownerName = $b->owner
                        ? (trim(($b->owner->first_name ?? '') . ' ' . ($b->owner->last_name ?? '')) ?: ($b->owner->business_name ?: $b->owner->email))
                        : '-';
                    $ownerEmail = $b->owner->email ?? '';
                    return [
                        'id' => $b->id,
                        'name' => $b->name,
                        'branch_name' => $b->branch_name,
                        'display_name' => $b->branch_name ? "{$b->name} · {$b->branch_name}" : $b->name,
                        'schema_name' => $b->schema_name,
                        'status' => $b->status,
                        'owner_id' => $b->owner_id,
                        'owner_name' => $ownerName,
                        'owner_email' => $ownerEmail,
                    ];
                }),
                'business_owners' => $distinctOwners,
            ];
        });

        return response()->json([
            'status' => true,
            'message' => 'Business groups retrieved successfully',
            'counts' => [
                'all' => $allCount,
                'active' => $activeCount,
                'inactive' => $inactiveCount,
                'without_businesses' => $withoutBusinessesCount,
            ],
            'data' => $formatted,
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ], 200);
    }

    /**
     * Get owner search options with their owned businesses count.
     */
    public function searchOwners(Request $request)
    {
        $search = trim($request->query('query', ''));

        $query = User::where('user_type', '!=', 3);

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhereRaw("CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,'')) LIKE ?", ["%{$search}%"])
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('business_name', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('first_name', 'asc')->limit(100)->get();

        // Count businesses for each user
        $userIds = $users->pluck('id')->all();
        $bizCounts = Business::whereIn('owner_id', $userIds)
            ->select('owner_id', DB::raw('count(*) as total'))
            ->groupBy('owner_id')
            ->pluck('total', 'owner_id');

        $result = $users->map(function ($u) use ($bizCounts) {
            $displayName = trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) ?: ($u->business_name ?: $u->email);
            $count = (int) ($bizCounts[$u->id] ?? 0);
            return [
                'id' => $u->id,
                'name' => $displayName,
                'email' => $u->email,
                'phone' => $u->phone,
                'businesses_count' => $count,
                'label' => "{$displayName} ({$u->email})",
                'sub_label' => "Owns {$count} business" . ($count === 1 ? '' : 'es'),
            ];
        });

        return response()->json([
            'status' => true,
            'data' => $result,
        ], 200);
    }

    public function businesses(Request $request)
    {
        $ownerId = $request->query('owner_id');
        $query = Business::query();
        if (!empty($ownerId)) {
            $query->where('owner_id', $ownerId);
        }
        $businesses = $query->select('id', 'name', 'branch_name', 'status', 'schema_name', 'owner_id', 'business_group_id')
            ->with('owner:id,name,first_name,last_name,email')
            ->orderBy('name', 'asc')
            ->get()
            ->map(function ($b) {
                $ownerName = $b->owner ? ($b->owner->name ?: trim(($b->owner->first_name ?? '') . ' ' . ($b->owner->last_name ?? ''))) : '-';
                return [
                    'id' => $b->id,
                    'name' => $b->name,
                    'branch_name' => $b->branch_name,
                    'display_name' => $b->branch_name ? "{$b->name} · {$b->branch_name}" : $b->name,
                    'status' => $b->status,
                    'schema_name' => $b->schema_name,
                    'owner_id' => $b->owner_id,
                    'owner_name' => $ownerName,
                    'owner_email' => $b->owner->email ?? '',
                    'business_group_id' => $b->business_group_id,
                ];
            });

        return response()->json([
            'status' => true,
            'data' => $businesses,
        ], 200);
    }

    /**
     * Create a new business group.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'owner_id' => 'required|string',
            'business_ids' => 'nullable|array',
            'business_ids.*' => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $group = BusinessGroup::create([
                'name' => trim($request->input('name')),
                'owner_id' => $request->input('owner_id'),
                'status' => 'active',
                'description' => $request->input('description'),
            ]);

            // Assign businesses if specified
            $businessIds = $request->input('business_ids', []);
            if (!empty($businessIds)) {
                Business::whereIn('id', $businessIds)
                    ->update(['business_group_id' => $group->id]);
            } else {
                // Auto-assign all businesses owned by this owner
                Business::where('owner_id', $group->owner_id)
                    ->update(['business_group_id' => $group->id]);
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Business group created successfully',
                'data' => $group,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Failed to create business group: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get single business group details.
     */
    public function show($id)
    {
        $group = BusinessGroup::with([
            'owner',
            'businesses' => function ($q) {
                $q->with('owner');
            }
        ])->find($id);

        if (!$group) {
            return response()->json([
                'status' => false,
                'message' => 'Business group not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $group,
        ], 200);
    }

    /**
     * Update business group details.
     */
    public function update(Request $request, $id)
    {
        $group = BusinessGroup::find($id);
        if (!$group) {
            return response()->json([
                'status' => false,
                'message' => 'Business group not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'owner_id' => 'sometimes|required|string',
            'status' => 'nullable|string|in:active,inactive',
            'business_ids' => 'nullable|array',
            'business_ids.*' => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $data = [];
            if ($request->has('name')) $data['name'] = trim($request->input('name'));
            if ($request->has('owner_id')) $data['owner_id'] = $request->input('owner_id');
            if ($request->has('status')) $data['status'] = $request->input('status');
            if ($request->has('description')) $data['description'] = $request->input('description');

            $group->update($data);

            if ($request->has('business_ids')) {
                $newBizIds = $request->input('business_ids', []);
                // Remove group from businesses no longer in list
                Business::where('business_group_id', $group->id)
                    ->whereNotIn('id', $newBizIds)
                    ->update(['business_group_id' => null]);

                // Assign to this group
                if (!empty($newBizIds)) {
                    Business::whereIn('id', $newBizIds)
                        ->update(['business_group_id' => $group->id]);
                }
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Business group updated successfully',
                'data' => $group->load(['owner', 'businesses.owner']),
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Failed to update business group: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle group status (active <-> inactive).
     */
    public function toggleStatus($id)
    {
        $group = BusinessGroup::find($id);
        if (!$group) {
            return response()->json([
                'status' => false,
                'message' => 'Business group not found',
            ], 404);
        }

        $newStatus = ($group->status === 'active') ? 'inactive' : 'active';
        $group->update(['status' => $newStatus]);

        return response()->json([
            'status' => true,
            'message' => "Business group is now {$newStatus}",
            'data' => $group,
        ], 200);
    }

    /**
     * Soft delete business group and unlink businesses.
     */
    public function destroy($id)
    {
        $group = BusinessGroup::find($id);
        if (!$group) {
            return response()->json([
                'status' => false,
                'message' => 'Business group not found',
            ], 404);
        }

        DB::beginTransaction();
        try {
            // Unlink businesses
            Business::where('business_group_id', $group->id)
                ->update(['business_group_id' => null]);

            $group->delete();

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Business group deleted successfully',
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete business group: ' . $e->getMessage(),
            ], 500);
        }
    }
}
