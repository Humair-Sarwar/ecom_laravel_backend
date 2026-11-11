<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\LabItem;
use App\Models\LabItemUsage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class InventoryController extends Controller
{
    /**
     * Get paginated Lab Items with filters.
     */
    public function labItems(Request $request)
    {
        $userId = auth()->id();
        $perPage = max(1, (int) $request->input('per_page', 15));
        $page = max(1, (int) $request->input('page', 1));
        $search = trim($request->input('search', ''));
        $stockFilter = $request->input('stock', 'all');
        $condition = $request->input('condition', 'all');
        $quality = $request->input('quality', 'all');
        $brandId = $request->input('brand_id', 'all');
        $categoryId = $request->input('category_id', 'all');

        $query = LabItem::with(['category', 'brand'])
            ->where('business_id', $userId)
            ->where('is_deleted', false);

        // Search by name or barcode
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        // Stock status filter
        if ($stockFilter === 'in_stock') {
            $query->where('stock', '>', 0);
        } elseif ($stockFilter === 'out_of_stock') {
            $query->where('stock', '<=', 0);
        } elseif ($stockFilter === 'low_stock') {
            $query->where('stock', '>', 0)
                  ->whereColumn('stock', '<=', 'min_stock_alert');
        }

        // Condition filter
        if ($condition && $condition !== 'all') {
            $query->where('condition', $condition);
        }

        // Quality filter
        if ($quality && $quality !== 'all') {
            $query->where('quality', $quality);
        }

        // Brand filter
        if ($brandId && $brandId !== 'all') {
            $query->where('brand_id', $brandId);
        }

        // Category filter
        if ($categoryId && $categoryId !== 'all') {
            $query->where('category_id', $categoryId);
        }

        $query->orderBy('sort_order', 'asc')->orderBy('created_at', 'desc');

        $totalCount = (clone $query)->count();
        $items = $query->paginate($perPage, ['*'], 'page', $page);

        // Append available_stock attribute to each item
        $items->getCollection()->transform(function ($item) {
            $item->available_stock = max(0, $item->stock - $item->reserved_stock);
            return $item;
        });

        return response()->json([
            'status' => true,
            'message' => 'Lab items fetched successfully',
            'data' => $items->items(),
            'total' => $totalCount,
            'pagination' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ], 200);
    }

    /**
     * Create a new Lab Item.
     */
    public function createLabItem(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'barcode' => 'nullable|string|max:100',
            'condition' => 'nullable|string|max:100',
            'quality' => 'nullable|string|max:100',
            'category_id' => 'required|exists:categories,id',
            'brand_id' => 'required|exists:brands,id',
            'unit_of_measure' => 'nullable|string|max:50',
            'min_stock_alert' => 'nullable|integer|min:0',
            'sort_order' => 'nullable|integer|min:1',
            'stock' => 'nullable|integer|min:0',
            'last_cost' => 'nullable|numeric|min:0',
            'selling_price' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $userId = auth()->id();

        // Generate a random 5-6 digit unique barcode if empty
        $barcode = trim($request->input('barcode', ''));
        if (empty($barcode)) {
            $barcode = (string) rand(100000, 999999);
            while (LabItem::where('business_id', $userId)->where('barcode', $barcode)->where('is_deleted', false)->exists()) {
                $barcode = (string) rand(100000, 999999);
            }
        }

        $labItem = LabItem::create([
            'business_id' => $userId,
            'name' => $request->name,
            'barcode' => $barcode,
            'condition' => $request->input('condition', 'Brand New'),
            'quality' => $request->input('quality', 'Premium'),
            'category_id' => $request->category_id,
            'brand_id' => $request->brand_id,
            'unit_of_measure' => $request->input('unit_of_measure', 'Piece'),
            'min_stock_alert' => (int) $request->input('min_stock_alert', 0),
            'sort_order' => (int) $request->input('sort_order', 1),
            'stock' => (int) $request->input('stock', 0),
            'reserved_stock' => 0,
            'last_cost' => (float) $request->input('last_cost', 0.00),
            'selling_price' => (float) $request->input('selling_price', 0.00),
            'description' => $request->description,
            'is_active' => true,
            'is_deleted' => false,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Lab item created successfully',
            'data' => $labItem->load(['category', 'brand']),
        ], 201);
    }

    /**
     * Show single Lab Item.
     */
    public function showLabItem($id)
    {
        $userId = auth()->id();
        $item = LabItem::with(['category', 'brand'])
            ->where('business_id', $userId)
            ->where('is_deleted', false)
            ->find($id);

        if (!$item) {
            return response()->json([
                'status' => false,
                'message' => 'Lab item not found',
            ], 404);
        }

        $item->available_stock = max(0, $item->stock - $item->reserved_stock);

        return response()->json([
            'status' => true,
            'data' => $item,
        ], 200);
    }

    /**
     * Update Lab Item.
     */
    public function updateLabItem(Request $request, $id)
    {
        $userId = auth()->id();
        $item = LabItem::where('business_id', $userId)
            ->where('is_deleted', false)
            ->find($id);

        if (!$item) {
            return response()->json([
                'status' => false,
                'message' => 'Lab item not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'barcode' => 'nullable|string|max:100',
            'condition' => 'nullable|string|max:100',
            'quality' => 'nullable|string|max:100',
            'category_id' => 'required|exists:categories,id',
            'brand_id' => 'required|exists:brands,id',
            'unit_of_measure' => 'nullable|string|max:50',
            'min_stock_alert' => 'nullable|integer|min:0',
            'sort_order' => 'nullable|integer|min:1',
            'stock' => 'nullable|integer|min:0',
            'last_cost' => 'nullable|numeric|min:0',
            'selling_price' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $item->name = $request->name;
        if ($request->has('barcode')) {
            $item->barcode = $request->barcode;
        }
        if ($request->has('condition')) {
            $item->condition = $request->condition;
        }
        if ($request->has('quality')) {
            $item->quality = $request->quality;
        }
        if ($request->has('category_id')) {
            $item->category_id = $request->category_id;
        }
        if ($request->has('brand_id')) {
            $item->brand_id = $request->brand_id;
        }
        if ($request->has('unit_of_measure')) {
            $item->unit_of_measure = $request->unit_of_measure;
        }
        if ($request->has('min_stock_alert')) {
            $item->min_stock_alert = (int) $request->min_stock_alert;
        }
        if ($request->has('sort_order')) {
            $item->sort_order = (int) $request->sort_order;
        }
        if ($request->has('stock')) {
            $item->stock = (int) $request->stock;
        }
        if ($request->has('last_cost')) {
            $item->last_cost = (float) $request->last_cost;
        }
        if ($request->has('selling_price')) {
            $item->selling_price = (float) $request->selling_price;
        }
        if ($request->has('description')) {
            $item->description = $request->description;
        }

        $item->save();

        return response()->json([
            'status' => true,
            'message' => 'Lab item updated successfully',
            'data' => $item->load(['category', 'brand']),
        ], 200);
    }

    /**
     * Soft delete Lab Item.
     */
    public function deleteLabItem($id)
    {
        $userId = auth()->id();
        $item = LabItem::where('business_id', $userId)
            ->where('is_deleted', false)
            ->find($id);

        if (!$item) {
            return response()->json([
                'status' => false,
                'message' => 'Lab item not found',
            ], 404);
        }

        if ((int) $item->stock > 0) {
            return response()->json([
                'status' => false,
                'message' => 'Cannot delete lab item with stock greater than zero',
            ], 422);
        }

        $item->is_deleted = true;
        $item->save();

        return response()->json([
            'status' => true,
            'message' => 'Lab item deleted successfully',
        ], 200);
    }

    /**
     * Quick stock adjustment (Add / Deduct / Set stock + update last cost).
     */
    public function adjustStock(Request $request, $id)
    {
        $userId = auth()->id();
        $item = LabItem::where('business_id', $userId)
            ->where('is_deleted', false)
            ->find($id);

        if (!$item) {
            return response()->json([
                'status' => false,
                'message' => 'Lab item not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'type' => 'required|in:add,deduct,set',
            'quantity' => 'required|integer|min:1',
            'cost_price' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $qty = (int) $request->quantity;
        if ($request->type === 'add') {
            $item->stock += $qty;
        } elseif ($request->type === 'deduct') {
            $item->stock = max(0, $item->stock - $qty);
        } else {
            $item->stock = $qty;
        }

        if ($request->has('cost_price') && is_numeric($request->cost_price)) {
            $item->last_cost = (float) $request->cost_price;
        }

        $item->save();

        return response()->json([
            'status' => true,
            'message' => "Stock adjusted successfully. Current stock: {$item->stock}",
            'data' => $item,
        ], 200);
    }

    /**
     * Batch update categories for multiple items.
     */
    public function batchUpdateCategory(Request $request)
    {
        $userId = auth()->id();
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'category_id' => 'required|exists:categories,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $updated = LabItem::where('business_id', $userId)
            ->whereIn('id', $request->ids)
            ->where('is_deleted', false)
            ->update([
                'category_id' => $request->category_id,
                'updated_at' => now(),
            ]);

        return response()->json([
            'status' => true,
            'message' => "Category updated for {$updated} item(s)",
            'updated_count' => $updated,
        ], 200);
    }

    /**
     * Get Lab Item Usages with date filtering & summary metrics.
     */
    public function labItemUsages(Request $request)
    {
        $userId = auth()->id();
        $perPage = max(1, (int) $request->input('per_page', 15));
        $page = max(1, (int) $request->input('page', 1));
        $search = trim($request->input('search', ''));
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $orderFrom = $request->input('order_from', 'all');
        $categoryId = $request->input('category_id', 'all');

        $query = LabItemUsage::with(['labItem.category'])
            ->where('business_id', $userId);

        if (!empty($dateFrom)) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if (!empty($dateTo)) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('order_id', 'like', "%{$search}%")
                  ->orWhereHas('labItem', function ($subQ) use ($search) {
                      $subQ->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($orderFrom && $orderFrom !== 'all') {
            $query->where('order_from', $orderFrom);
        }

        if ($categoryId && $categoryId !== 'all') {
            $query->whereHas('labItem', function ($subQ) use ($categoryId) {
                $subQ->where('category_id', $categoryId);
            });
        }

        // Summary calculations across matching records
        $totalQuantity = (clone $query)->sum('quantity');
        $totalPackPrice = (clone $query)->sum('pack_price');
        $totalRecordCount = (clone $query)->count();

        $usages = $query->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'status' => true,
            'message' => 'Lab item usages fetched successfully',
            'data' => $usages->items(),
            'total' => $totalRecordCount,
            'summary' => [
                'total_quantity' => (int) $totalQuantity,
                'total_pack_price' => (float) $totalPackPrice,
            ],
            'pagination' => [
                'current_page' => $usages->currentPage(),
                'last_page' => $usages->lastPage(),
                'per_page' => $usages->perPage(),
                'total' => $usages->total(),
            ],
        ], 200);
    }

    /**
     * Log a Lab Item Usage.
     */
    public function createLabItemUsage(Request $request)
    {
        $userId = auth()->id();
        $validator = Validator::make($request->all(), [
            'lab_item_id' => 'required|exists:lab_items,id',
            'quantity' => 'required|integer|min:1',
            'order_id' => 'nullable|string|max:100',
            'order_from' => 'nullable|string|max:50',
            'pack_price' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $labItem = LabItem::where('business_id', $userId)
            ->where('is_deleted', false)
            ->find($request->lab_item_id);

        if (!$labItem) {
            return response()->json([
                'status' => false,
                'message' => 'Lab item not found',
            ], 404);
        }

        $qty = (int) $request->quantity;
        $packPrice = $request->filled('pack_price') ? (float) $request->pack_price : (float) $labItem->last_cost;
        $orderId = $request->filled('order_id') ? $request->order_id : 'ORD-' . strtoupper(Str::random(8));

        // Deduct item stock
        $labItem->stock = max(0, $labItem->stock - $qty);
        $labItem->save();

        $userName = auth()->user()->first_name ? trim(auth()->user()->first_name . ' ' . auth()->user()->last_name) : 'Store Staff';

        $usage = LabItemUsage::create([
            'business_id' => $userId,
            'lab_item_id' => $labItem->id,
            'order_id' => $orderId,
            'order_from' => $request->input('order_from', 'POS'),
            'quantity' => $qty,
            'pack_price' => $packPrice,
            'total_price' => $packPrice * $qty,
            'created_by_name' => $userName,
            'notes' => $request->notes,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Item usage recorded successfully',
            'data' => $usage->load(['labItem']),
        ], 201);
    }
}
