<?php

namespace App\Http\Controllers\SystemUser;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class UserController extends Controller
{
    /**
     * Display a listing of system users (Business Owners & Website Users).
     */
    public function index(Request $request)
    {
        $tab = $request->input('tab', 'all'); // 'all' | 'business_owners' | 'website_users'
        $search = trim($request->input('search', ''));
        $status = $request->input('status', 'all'); // 'all' | 'active' | 'inactive'
        $registered = $request->input('registered', 'any'); // 'any' | 'today' | '7days' | 'month' | 'year'
        $sortBy = $request->input('sort', 'newest'); // 'newest' | 'oldest' | 'name_asc' | 'name_desc'
        $perPage = max((int) $request->input('per_page', 10), 1);
        $page = max((int) $request->input('page', 1), 1);

        // Counts for tabs (only user_type 1 & 2: website users and business owners)
        $allCount = User::whereIn('user_type', [1, 2])->count();
        $businessOwnersCount = User::where('user_type', 2)->count();
        $websiteUsersCount = User::where('user_type', 1)->count();

        // Base query
        $query = User::whereIn('user_type', [1, 2]);

        // Filter by Tab
        if ($tab === 'business_owners') {
            $query->where('user_type', 2);
        } elseif ($tab === 'website_users') {
            $query->where('user_type', 1);
        }

        // Search
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"])
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('business_name', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($status === 'active') {
            $query->where('status', true);
        } elseif ($status === 'inactive') {
            $query->where('status', false);
        }

        // Registered date filter
        if ($registered === 'today') {
            $query->where('created_at', '>=', Carbon::today());
        } elseif ($registered === '7days') {
            $query->where('created_at', '>=', Carbon::now()->subDays(7));
        } elseif ($registered === 'month') {
            $query->where('created_at', '>=', Carbon::now()->startOfMonth());
        } elseif ($registered === 'year') {
            $query->where('created_at', '>=', Carbon::now()->startOfYear());
        }

        // Sorting
        switch ($sortBy) {
            case 'oldest':
                $query->orderBy('created_at', 'asc');
                break;
            case 'name_asc':
                $query->orderBy('first_name', 'asc')->orderBy('last_name', 'asc');
                break;
            case 'name_desc':
                $query->orderBy('first_name', 'desc')->orderBy('last_name', 'desc');
                break;
            case 'newest':
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        $paginated = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'status' => true,
            'message' => 'Users fetched successfully',
            'data' => $paginated->items(),
            'counts' => [
                'all' => $allCount,
                'business_owners' => $businessOwnersCount,
                'website_users' => $websiteUsersCount,
            ],
            'total' => $paginated->total(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'per_page' => $paginated->perPage(),
        ], 200);
    }

    /**
     * Display single user details.
     */
    public function show($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $user,
        ], 200);
    }

    /**
     * Toggle user status (active / inactive).
     */
    public function toggleStatus($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found',
            ], 404);
        }

        $user->status = !$user->status;
        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'User status updated successfully',
            'data' => $user,
        ], 200);
    }

    /**
     * Export users as CSV.
     */
    public function exportCsv(Request $request)
    {
        $tab = $request->input('tab', 'all');
        $search = trim($request->input('search', ''));
        $status = $request->input('status', 'all');
        $registered = $request->input('registered', 'any');

        $query = User::whereIn('user_type', [1, 2]);

        if ($tab === 'business_owners') {
            $query->where('user_type', 2);
        } elseif ($tab === 'website_users') {
            $query->where('user_type', 1);
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('business_name', 'like', "%{$search}%");
            });
        }

        if ($status === 'active') {
            $query->where('status', true);
        } elseif ($status === 'inactive') {
            $query->where('status', false);
        }

        if ($registered === 'today') {
            $query->where('created_at', '>=', Carbon::today());
        } elseif ($registered === '7days') {
            $query->where('created_at', '>=', Carbon::now()->subDays(7));
        } elseif ($registered === 'month') {
            $query->where('created_at', '>=', Carbon::now()->startOfMonth());
        } elseif ($registered === 'year') {
            $query->where('created_at', '>=', Carbon::now()->startOfYear());
        }

        $users = $query->orderBy('created_at', 'desc')->get();

        $headers = [
            'Content-type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename=users_export_' . date('Y-m-d') . '.csv',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($users) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'First Name', 'Last Name', 'Email', 'Phone', 'Type', 'Business/Website', 'Status', 'Registered At', 'Last Login At', 'Last Login IP']);

            foreach ($users as $user) {
                fputcsv($file, [
                    $user->id,
                    $user->first_name,
                    $user->last_name,
                    $user->email,
                    $user->phone,
                    $user->user_type == 2 ? 'Business Owner' : 'Website User',
                    $user->business_name,
                    $user->status ? 'Active' : 'Inactive',
                    $user->created_at ? $user->created_at->format('Y-m-d H:i:s') : '',
                    $user->last_login_at ? $user->last_login_at->format('Y-m-d H:i:s') : 'Never',
                    $user->last_login_ip,
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
