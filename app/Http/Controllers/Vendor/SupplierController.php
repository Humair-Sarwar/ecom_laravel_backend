<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SupplierController extends Controller
{
    /**
     * Get paginated suppliers list with search and summary stats.
     */
    public function index(Request $request)
    {
        $userId = auth()->id();
        $perPage = max(1, (int) $request->input('per_page', 15));
        $page = max(1, (int) $request->input('page', 1));
        $search = trim($request->input('search', ''));

        $query = Supplier::where('business_id', $userId)
            ->where('is_deleted', false);

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('supplier_name', 'like', "%{$search}%")
                  ->orWhere('full_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('mobile_no', 'like', "%{$search}%");
            });
        }

        // Summary stats across all active suppliers for this business
        $totalSuppliers = Supplier::where('business_id', $userId)
            ->where('is_deleted', false)
            ->count();

        $totalBalanceOwed = Supplier::where('business_id', $userId)
            ->where('is_deleted', false)
            ->sum('balance');

        $paginator = $query->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'status' => true,
            'data' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem() ?? 0,
                'to' => $paginator->lastItem() ?? 0,
            ],
            'stats' => [
                'total_suppliers' => (int) $totalSuppliers,
                'total_balance_owed' => (float) $totalBalanceOwed,
                'showing' => count($paginator->items()),
            ],
        ], 200);
    }

    /**
     * Store new supplier.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'supplier_name' => 'required|string|max:255',
            'full_name' => 'required|string|max:255',
            'mobile_no' => 'nullable|string|max:50',
            'country_code' => 'nullable|string|max:10',
            'email' => 'nullable|email|max:255',
            'description' => 'nullable|string',
            'tax_definition' => 'nullable|string|max:100',
            'is_default' => 'nullable|boolean',
            'balance' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $userId = auth()->id();
        $user = auth()->user();

        $isDefault = $request->boolean('is_default', false);

        // If setting as default, unset previous default supplier
        if ($isDefault) {
            Supplier::where('business_id', $userId)
                ->where('is_deleted', false)
                ->update(['is_default' => false]);
        }

        $supplier = Supplier::create([
            'business_id' => $userId,
            'supplier_name' => trim($request->supplier_name),
            'full_name' => trim($request->full_name),
            'country_code' => $request->input('country_code', '+44'),
            'mobile_no' => $request->filled('mobile_no') ? trim($request->mobile_no) : null,
            'email' => $request->filled('email') ? trim($request->email) : null,
            'description' => $request->description,
            'tax_definition' => $request->input('tax_definition', 'N/A'),
            'is_default' => $isDefault,
            'balance' => (float) $request->input('balance', 0.00),
            'created_by' => $userId,
            'created_by_name' => $user ? ($user->name ?? $user->email ?? 'Vendor') : 'Vendor',
            'is_active' => true,
            'is_deleted' => false,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Supplier created successfully',
            'data' => $supplier,
        ], 201);
    }

    /**
     * Show single supplier details.
     */
    public function show($id)
    {
        $userId = auth()->id();
        $supplier = Supplier::where('business_id', $userId)
            ->where('is_deleted', false)
            ->find($id);

        if (!$supplier) {
            return response()->json([
                'status' => false,
                'message' => 'Supplier not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $supplier,
        ], 200);
    }

    /**
     * Update existing supplier.
     */
    public function update(Request $request, $id)
    {
        $userId = auth()->id();
        $supplier = Supplier::where('business_id', $userId)
            ->where('is_deleted', false)
            ->find($id);

        if (!$supplier) {
            return response()->json([
                'status' => false,
                'message' => 'Supplier not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'supplier_name' => 'required|string|max:255',
            'full_name' => 'required|string|max:255',
            'mobile_no' => 'nullable|string|max:50',
            'country_code' => 'nullable|string|max:10',
            'email' => 'nullable|email|max:255',
            'description' => 'nullable|string',
            'tax_definition' => 'nullable|string|max:100',
            'is_default' => 'nullable|boolean',
            'balance' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $isDefault = $request->boolean('is_default', false);

        if ($isDefault && !$supplier->is_default) {
            Supplier::where('business_id', $userId)
                ->where('is_deleted', false)
                ->where('id', '!=', $supplier->id)
                ->update(['is_default' => false]);
        }

        $supplier->update([
            'supplier_name' => trim($request->supplier_name),
            'full_name' => trim($request->full_name),
            'country_code' => $request->input('country_code', $supplier->country_code),
            'mobile_no' => $request->filled('mobile_no') ? trim($request->mobile_no) : null,
            'email' => $request->filled('email') ? trim($request->email) : null,
            'description' => $request->description,
            'tax_definition' => $request->input('tax_definition', $supplier->tax_definition),
            'is_default' => $isDefault,
            'balance' => $request->has('balance') ? (float) $request->input('balance') : $supplier->balance,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Supplier updated successfully',
            'data' => $supplier,
        ], 200);
    }

    /**
     * Soft delete supplier.
     */
    public function destroy($id)
    {
        $userId = auth()->id();
        $supplier = Supplier::where('business_id', $userId)
            ->where('is_deleted', false)
            ->find($id);

        if (!$supplier) {
            return response()->json([
                'status' => false,
                'message' => 'Supplier not found',
            ], 404);
        }

        $supplier->update(['is_deleted' => true, 'is_active' => false]);

        return response()->json([
            'status' => true,
            'message' => 'Supplier deleted successfully',
        ], 200);
    }
}
