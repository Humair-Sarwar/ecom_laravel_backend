<?php

namespace App\Http\Controllers\SystemUser;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RoleController extends Controller
{
    /**
     * Display a listing of the roles with search and pagination.
     */
    public function index(Request $request)
    {
        $search = trim($request->input('search', ''));
        $perPage = max((int) $request->input('per_page', 10), 1);
        $page = max((int) $request->input('page', 1), 1);

        $query = Role::where('is_deleted', false);

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $paginated = $query->orderBy('created_at', 'asc')->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'status' => true,
            'message' => 'Roles fetched successfully',
            'data' => $paginated->items(),
            'total' => $paginated->total(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'per_page' => $paginated->perPage(),
        ], 200);
    }

    /**
     * Store a newly created role.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'permissions' => 'nullable|array',
        ]);

        $baseSlug = Str::slug($request->title);
        $slug = $baseSlug;
        $counter = 1;

        while (Role::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        $role = Role::create([
            'title' => $request->title,
            'slug' => $slug,
            'description' => $request->description,
            'is_active' => $request->boolean('is_active', true),
            'permissions' => $request->permissions ?? [],
            'synced_permissions' => $request->permissions ?? [],
            'is_deleted' => false,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Role created successfully',
            'data' => $role,
        ], 201);
    }

    /**
     * Display the specified role.
     */
    public function show(string $id)
    {
        $role = Role::where('is_deleted', false)->find($id);

        if (!$role) {
            return response()->json([
                'status' => false,
                'message' => 'Role not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $role,
        ], 200);
    }

    /**
     * Update the specified role.
     */
    public function update(Request $request, string $id)
    {
        $role = Role::where('is_deleted', false)->find($id);

        if (!$role) {
            return response()->json([
                'status' => false,
                'message' => 'Role not found',
            ], 404);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'permissions' => 'nullable|array',
        ]);

        $role->title = $request->title;
        if ($request->has('description')) {
            $role->description = $request->description;
        }
        if ($request->has('is_active')) {
            $role->is_active = $request->boolean('is_active');
        }
        if ($request->has('permissions')) {
            $role->permissions = $request->permissions;
        }

        $role->save();

        // Propagate role changes to assigned businesses
        Business::where('role_id', $role->id)->update([
            'role_title' => $role->title,
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Role updated successfully',
            'data' => $role,
        ], 200);
    }

    /**
     * Remove the specified role (soft delete).
     */
    public function destroy(string $id)
    {
        $role = Role::where('is_deleted', false)->find($id);

        if (!$role) {
            return response()->json([
                'status' => false,
                'message' => 'Role not found',
            ], 404);
        }

        $role->is_deleted = true;
        $role->save();

        // Propagate role changes to assigned businesses
        Business::where('role_id', $role->id)->update([
            'role_title' => $role->title,
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Role deleted successfully',
        ], 200);
    }

    /**
     * Toggle active status.
     */
    public function toggleStatus(string $id)
    {
        $role = Role::where('is_deleted', false)->find($id);

        if (!$role) {
            return response()->json([
                'status' => false,
                'message' => 'Role not found',
            ], 404);
        }

        $role->is_active = !$role->is_active;
        $role->save();

        // Propagate role changes to assigned businesses
        Business::where('role_id', $role->id)->update([
            'role_title' => $role->title,
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Role status updated successfully',
            'data' => $role,
        ], 200);
    }

    /**
     * Sync Role permissions across all assigned users and systems.
     */
    public function syncRole(string $id)
    {
        $role = Role::where('is_deleted', false)->find($id);

        if (!$role) {
            return response()->json([
                'status' => false,
                'message' => 'Role not found',
            ], 404);
        }

        // Synchronize permissions to active synced_permissions
        $role->synced_permissions = is_array($role->permissions) ? $role->permissions : [];
        $role->touch();
        $role->save();

        // Synchronize and touch all businesses assigned this role
        $updatedCount = Business::where('role_id', $role->id)->update([
            'role_title' => $role->title,
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => true,
            'message' => "Role '{$role->title}' synchronized successfully across {$updatedCount} business(es)",
            'data' => $role,
            'assigned_businesses' => $updatedCount,
        ], 200);
    }
}
