<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\LabItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\SupplierLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PurchaseOrderController extends Controller
{
    /**
     * List all Purchase Orders with filtering, search and metrics.
     */
    public function index(Request $request)
    {
        $businessId = auth()->id();

        $query = PurchaseOrder::where('business_id', $businessId)
            ->where('is_deleted', false)
            ->with(['supplier', 'items']);

        // Filter by status: when omitted on Purchase Orders, exclude unfinished drafts
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        } else {
            $query->where('status', '!=', 'draft');
        }

        // Filter by supplier_id
        if ($request->filled('supplier_id') && $request->input('supplier_id') !== 'all') {
            $query->where('supplier_id', $request->input('supplier_id'));
        }

        // Filter by date range (order_date or created_at)
        if ($request->filled('date_from')) {
            $query->whereDate('order_date', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('order_date', '<=', $request->input('date_to'));
        }

        // Search by order_no, supplier name, or created_by_name
        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('order_no', 'like', "%{$search}%")
                  ->orWhere('created_by_name', 'like', "%{$search}%")
                  ->orWhereHas('supplier', function ($sq) use ($search) {
                      $sq->where('supplier_name', 'like', "%{$search}%")
                         ->orWhere('full_name', 'like', "%{$search}%");
                  });
            });
        }

        // Metrics calculation across all matching orders
        $totalOrdersCount = (clone $query)->count();

        // Pagination
        $perPage = (int) $request->input('per_page', 15);
        if ($perPage <= 0) $perPage = 15;

        $orders = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // Attach payment breakdowns for each order (Cash, Card, Bank) from SupplierLedger
        $orderNos = $orders->pluck('order_no')->filter()->all();
        $ledgerPayments = SupplierLedger::where('business_id', $businessId)
            ->whereIn('reference_no', $orderNos)
            ->where('transaction_type', 'payment')
            ->where('is_deleted', false)
            ->get()
            ->groupBy('reference_no');

        $formattedOrders = $orders->getCollection()->map(function ($po) use ($ledgerPayments) {
            $poArray = $po->toArray();
            $payments = $ledgerPayments->get($po->order_no, collect());

            $paymentBreakdown = [];
            foreach ($payments as $pay) {
                $type = $pay->payment_type ?: 'Payment';
                $amount = (float) $pay->payment_amount;
                if ($amount > 0) {
                    $paymentBreakdown[] = [
                        'id' => $pay->id,
                        'supplier_id' => $pay->supplier_id,
                        'reference_no' => $pay->reference_no,
                        'transaction_type' => $pay->transaction_type,
                        'payment_amount' => $amount,
                        'payment_type' => $type,
                        'transaction_date' => $pay->transaction_date,
                        'description' => $pay->description,
                        'use_pos_balance' => (bool) $pay->use_pos_balance,
                        'type' => $type,
                        'amount' => $amount,
                    ];
                }
            }

            $poArray['payment_breakdown'] = $paymentBreakdown;
            $poArray['items_count'] = count($poArray['items'] ?? []);
            $poArray['section'] = 'purchase-order';
            return $poArray;
        });

        return response()->json([
            'status' => true,
            'data' => $formattedOrders,
            'pagination' => [
                'total' => $orders->total(),
                'per_page' => $orders->perPage(),
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'from' => $orders->firstItem() ?: 0,
                'to' => $orders->lastItem() ?: 0,
            ],
            'metrics' => [
                'total_orders' => $totalOrdersCount,
                'showing_orders' => $orders->count(),
            ],
        ], 200);
    }

    /**
     * List Purchase Order drafts (status = 'draft').
     */
    public function drafts(Request $request)
    {
        $request->merge(['status' => 'draft']);
        return $this->index($request);
    }

    /**
     * Soft-delete (discard) an unfinished purchase order draft.
     */
    public function destroyDraft($id)
    {
        $businessId = auth()->id();
        $draft = PurchaseOrder::where('business_id', $businessId)
            ->where('status', 'draft')
            ->where('is_deleted', false)
            ->find($id);

        if (!$draft) {
            return response()->json([
                'status' => false,
                'message' => 'Purchase draft not found',
            ], 404);
        }

        $draft->update(['is_deleted' => true]);

        return response()->json([
            'status' => true,
            'message' => 'Purchase draft deleted successfully',
        ], 200);
    }

    /**
     * Search products & inventory items for Purchase Order.
     */
    public function searchProducts(Request $request)
    {
        $businessId = auth()->id();
        $query = trim((string) $request->input('search', ''));

        $results = [];

        // 1. Search regular catalog Products
        // Only show published products with manage stock enabled (apply_stock_strictly = true)
        // In variable product cases, only show child variations whose parent has manage stock enabled
        $productQuery = Product::where('business_id', $businessId)
            ->where('is_deleted', false)
            ->where('is_published', true)
            ->where(function ($q) {
                // Either simple products (apply_stock_strictly = true)
                $q->where(function ($sub) {
                    $sub->whereNull('parent_product_id')
                        ->where('product_type', '!=', 2)
                        ->where('apply_stock_strictly', true);
                })
                // OR child variations (whose parent is published, not deleted, and has apply_stock_strictly = true)
                ->orWhere(function ($sub) {
                    $sub->whereNotNull('parent_product_id')
                        ->where(function ($cv) {
                            $cv->whereNull('apply_stock_strictly')
                               ->orWhere('apply_stock_strictly', true);
                        })
                        ->whereHas('parentProduct', function ($p) {
                            $p->where('is_published', true)
                              ->where('is_deleted', false)
                              ->where('apply_stock_strictly', true);
                        });
                });
            })
            ->with(['category', 'brand', 'media', 'variationImage', 'parentProduct.category', 'parentProduct.brand', 'parentProduct.media'])
            ->limit(40);

        if (!empty($query)) {
            $productQuery->where(function ($q) use ($query) {
                $q->where('title', 'ilike', "%{$query}%")
                    ->orWhere('product_sku', 'ilike', "%{$query}%");
            });
        }

        $products = $productQuery->get();

        foreach ($products as $prod) {
            $image = null;
            if ($prod->parent_product_id) {
                // Variation case:
                // 1) Variation direct assigned image
                $varMediaPath = optional($prod->variationImage)->media_path;

                // 2) Mapped term image
                if (!$varMediaPath) {
                    $termMediaPath = DB::table('product_variation_term_mappings')
                        ->where('product_variation_term_mappings.product_id', $prod->id)
                        ->join('product_attributes_terms', function ($join) use ($prod) {
                            $join->on('product_variation_term_mappings.term_id', '=', 'product_attributes_terms.term_id')
                                 ->where('product_attributes_terms.product_id', '=', $prod->parent_product_id);
                        })
                        ->join('medias', 'product_attributes_terms.image_id', '=', 'medias.id')
                        ->value('medias.media_path');
                    if ($termMediaPath) {
                        $varMediaPath = $termMediaPath;
                    }
                }

                // 3) Fallback to parent's first image
                if (!$varMediaPath && $prod->parentProduct && $prod->parentProduct->media->isNotEmpty()) {
                    $varMediaPath = $prod->parentProduct->media->first()->media_path;
                }

                $image = $this->resolveMediaUrl($varMediaPath);
            } else {
                // Simple product case:
                // Show first image of product
                $simpleMediaPath = $prod->media->isNotEmpty()
                    ? $prod->media->first()->media_path
                    : (optional($prod->variationImage)->media_path ?: null);

                $image = $this->resolveMediaUrl($simpleMediaPath);
            }

            // Derive product title/name cleanly (from title or parent title or SKU)
            $title = trim((string) $prod->title);
            if (empty($title) && $prod->parentProduct) {
                $title = trim((string) $prod->parentProduct->title);
            }
            if (empty($title)) {
                $title = $prod->product_sku ? 'Product ' . $prod->product_sku : 'Product Item';
            }

            // Real stock quantity (0 for newly added products until received)
            $stockCount = (int) ($prod->stock_quantity ?? 0);
            $regularPrice = (float) ($prod->regular_price ?? 0);
            $salePrice = (float) ($prod->sale_price ?? 0);
            $isOnSale = (bool) ($prod->is_on_sale ?? false);
            if (!$isOnSale && $salePrice > 0 && $regularPrice > 0 && $salePrice < $regularPrice) {
                $isOnSale = true;
            }
            $costPrice = ($isOnSale && $salePrice > 0) ? $salePrice : ($regularPrice > 0 ? $regularPrice : $salePrice);
            $sellingPrice = $costPrice;

            $categoryName = $prod->category 
                ? ($prod->category->title ?? $prod->category->name ?? 'General')
                : ($prod->parentProduct && $prod->parentProduct->category 
                    ? ($prod->parentProduct->category->title ?? $prod->parentProduct->category->name ?? 'General') 
                    : 'General');

            $brandName = $prod->brand 
                ? ($prod->brand->title ?? $prod->brand->name ?? 'N/A')
                : ($prod->parentProduct && $prod->parentProduct->brand 
                    ? ($prod->parentProduct->brand->title ?? $prod->parentProduct->brand->name ?? 'N/A') 
                    : 'N/A');

            $conditionRaw = $prod->condition ?: ($prod->parentProduct ? $prod->parentProduct->condition : 1);
            $conditionFormatted = $this->formatConditionLabel($conditionRaw);

            $results[] = [
                'id' => $prod->id,
                'type' => 'product',
                'name' => $title,
                'title' => $title,
                'sku' => $prod->product_sku ?: 'N/A',
                'barcode' => $prod->product_sku ?: 'N/A',
                'stock' => $stockCount,
                'cost_price' => $costPrice,
                'regular_price' => $regularPrice,
                'sale_price' => $salePrice,
                'is_on_sale' => $isOnSale,
                'is_serialized' => (bool) ($prod->is_serialized ?? ($prod->parentProduct ? $prod->parentProduct->is_serialized : false)),
                'selling_price' => $sellingPrice,
                'condition' => $conditionFormatted,
                'category_name' => $categoryName,
                'brand_name' => $brandName,
                'image' => $image,
            ];
        }

        // 2. Search Lab Items (Inventory items)
        $labQuery = LabItem::where('business_id', $businessId)
            ->where('is_deleted', false)
            ->with(['category', 'brand'])
            ->limit(20);

        if (!empty($query)) {
            $labQuery->where(function ($q) use ($query) {
                $q->where('name', 'ilike', "%{$query}%")
                    ->orWhere('barcode', 'ilike', "%{$query}%");
            });
        }

        $labItems = $labQuery->get();

        foreach ($labItems as $lab) {
            $labName = trim((string) $lab->name);
            if (empty($labName)) {
                $labName = $lab->barcode ? 'Item ' . $lab->barcode : 'Inventory Item';
            }

            $results[] = [
                'id' => $lab->id,
                'type' => 'lab_item',
                'name' => $labName,
                'title' => $labName,
                'sku' => $lab->barcode ?: 'LAB-' . substr($lab->id, 0, 6),
                'barcode' => $lab->barcode ?: 'N/A',
                'stock' => (int) ($lab->stock ?? 0),
                'cost_price' => (float) ($lab->last_cost ?? 0),
                'selling_price' => (float) ($lab->selling_price ?? 0),
                'condition' => $lab->condition ?? 'Brand New',
                'category_name' => $lab->category ? $lab->category->name : 'Inventory',
                'brand_name' => $lab->brand ? $lab->brand->name : 'N/A',
                'warranty' => 'N/A',
                'year' => date('Y'),
                'image' => null,
            ];
        }

        return response()->json([
            'status' => true,
            'data' => $results,
        ], 200);
    }

    /**
     * Check if an IMEI / Serial number already exists in the database.
     */
    public function checkImei(Request $request)
    {
        $businessId = auth()->id();
        $imei = trim((string) $request->input('imei', ''));

        if ($imei === '') {
            return response()->json([
                'status' => true,
                'exists' => false,
            ]);
        }

        $excludeOrderId = $request->input('exclude_order_id');

        // 1. Check in purchase_order_items (exact match or in comma-separated list) for this business
        $existsInPurchases = PurchaseOrderItem::whereHas('purchaseOrder', function ($q) use ($businessId, $excludeOrderId) {
            $q->where('business_id', $businessId)
              ->where('is_deleted', false);
            if (!empty($excludeOrderId)) {
                $q->where('id', '!=', $excludeOrderId);
            }
        })
        ->where(function ($q) use ($imei) {
            $q->where('imei_serial_no', $imei)
              ->orWhereRaw("? = ANY(string_to_array(replace(imei_serial_no, ' ', ''), ','))", [$imei]);
        })
        ->exists();

        // 2. Also check if products or variations use this sku
        $existsInProducts = Product::where('business_id', $businessId)
            ->where('is_deleted', false)
            ->where('product_sku', $imei)
            ->exists();

        $exists = $existsInPurchases || $existsInProducts;

        return response()->json([
            'status' => true,
            'exists' => $exists,
            'message' => $exists ? 'IMEI or Serial Number already exists in database.' : 'IMEI or Serial Number is available.',
        ]);
    }

    /**
     * Create a new Purchase Order (or update / finalize an existing draft when draft_id is passed).
     */
    public function store(Request $request)
    {
        $businessId = auth()->id();
        $user = auth()->user();
        $requestedStatus = $request->input('status', 'completed');
        $isDraft = ($requestedStatus === 'draft');

        $validator = Validator::make($request->all(), [
            'draft_id' => 'nullable|uuid',
            'supplier_id' => 'required|uuid|exists:suppliers,id',
            'order_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.name' => 'required|string',
            'items.*.unit_cost' => $isDraft ? 'nullable|numeric|min:0' : 'required|numeric|min:0',
            'items.*.quantity' => $isDraft ? 'nullable|integer|min:1' : 'required|integer|min:1',
            'cash_paid' => 'nullable|numeric|min:0',
            'card_paid' => 'nullable|numeric|min:0',
            'bank_paid' => 'nullable|numeric|min:0',
            'is_trade_in' => 'nullable|boolean',
            'use_today_sales' => 'nullable|boolean',
            'status' => 'nullable|string|in:draft,completed',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $draftId = $request->input('draft_id');
        $existingDraft = null;
        if (!empty($draftId)) {
            $existingDraft = PurchaseOrder::where('business_id', $businessId)
                ->where('status', 'draft')
                ->where('is_deleted', false)
                ->find($draftId);

            if (!$existingDraft) {
                return response()->json([
                    'status' => false,
                    'message' => 'Purchase draft not found',
                ], 404);
            }
        }

        $supplier = Supplier::where('business_id', $businessId)
            ->where('is_deleted', false)
            ->find($request->input('supplier_id'));

        if (!$supplier) {
            return response()->json([
                'status' => false,
                'message' => 'Supplier not found',
            ], 404);
        }

        DB::beginTransaction();
        try {
            $itemsData = $request->input('items', []);

            // Validate that provided imei_serial_no don't already exist in database (excluding this draft if updating)
            foreach ($itemsData as $it) {
                $imei = trim((string) ($it['imei_serial_no'] ?? ''));
                if ($imei !== '') {
                    $existsInPurchases = PurchaseOrderItem::whereHas('purchaseOrder', function ($q) use ($businessId, $existingDraft) {
                        $q->where('business_id', $businessId)
                          ->where('is_deleted', false);
                        if ($existingDraft) {
                            $q->where('id', '!=', $existingDraft->id);
                        }
                    })
                    ->where(function ($q) use ($imei) {
                        $q->where('imei_serial_no', $imei)
                          ->orWhereRaw("? = ANY(string_to_array(replace(imei_serial_no, ' ', ''), ','))", [$imei]);
                    })
                    ->exists();

                    if ($existsInPurchases) {
                        DB::rollBack();
                        return response()->json([
                            'status' => false,
                            'message' => "IMEI or Serial Number \"{$imei}\" already exists in the system.",
                        ], 422);
                    }
                }
            }

            // Generate unique 8-digit order number (or preserve existing draft order_no)
            $orderNo = $existingDraft ? $existingDraft->order_no : $this->generateUniqueOrderNo();

            $cashPaid = (float) $request->input('cash_paid', 0);
            $cardPaid = (float) $request->input('card_paid', 0);
            $bankPaid = (float) $request->input('bank_paid', 0);
            $paidAmount = $cashPaid + $cardPaid + $bankPaid;

            $grandTotal = 0.00;

            foreach ($itemsData as $it) {
                $cost = (float) ($it['unit_cost'] ?? 0);
                $qty = max(1, (int) ($it['quantity'] ?? 1));
                $grandTotal += ($cost * $qty);
            }

            $balance = max(0.00, $grandTotal - $paidAmount);
            $status = $requestedStatus;

            // Count fulfillment statuses
            $totalItemsCount = count($itemsData);
            $readyCount = 0;
            foreach ($itemsData as $it) {
                $itemSt = $it['status'] ?? 'ready_for_sell';
                if ($itemSt === 'ready_for_sell') {
                    $readyCount++;
                }
            }

            $fulfillmentStatus = 'fulfilled';
            if ($readyCount === 0) {
                $fulfillmentStatus = 'pending';
            } elseif ($readyCount < $totalItemsCount) {
                $fulfillmentStatus = 'partial';
            }

            $creatorName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))
                ?: ($user->name ?? $user->email ?? 'Vendor');

            $orderAttributes = [
                'business_id' => $businessId,
                'supplier_id' => $supplier->id,
                'order_no' => $orderNo,
                'order_date' => $request->input('order_date'),
                'status' => $status,
                'fulfillment_status' => $fulfillmentStatus,
                'is_trade_in' => $request->boolean('is_trade_in', false),
                'grand_total' => $grandTotal,
                'paid_amount' => $paidAmount,
                'balance' => $balance,
                'cash_paid' => $cashPaid,
                'card_paid' => $cardPaid,
                'bank_paid' => $bankPaid,
                'use_today_sales' => $request->boolean('use_today_sales', false),
                'notes' => $request->input('notes'),
                'created_by' => $businessId,
                'created_by_name' => $creatorName,
            ];

            if ($existingDraft) {
                $existingDraft->update($orderAttributes);
                $existingDraft->items()->delete();
                $order = $existingDraft;
            } else {
                $order = PurchaseOrder::create($orderAttributes);
            }

            // Save Items
            foreach ($itemsData as $it) {
                $unitCost = (float) ($it['unit_cost'] ?? 0);
                $qty = max(1, (int) ($it['quantity'] ?? 1));
                $totalCost = $unitCost * $qty;
                $itemStatus = $it['status'] ?? 'ready_for_sell';
                $isReady = ($itemStatus === 'ready_for_sell');
                $receivedQty = ($status === 'completed' && $isReady) ? $qty : 0;
                $receivedAt = ($status === 'completed' && $isReady) ? now() : null;

                PurchaseOrderItem::create([
                    'purchase_order_id' => $order->id,
                    'product_id' => $it['product_id'] ?? null,
                    'product_name' => $it['name'],
                    'sku' => $it['sku'] ?? null,
                    'barcode' => $it['barcode'] ?? null,
                    'condition' => $it['condition'] ?? null,
                    'category_name' => $it['category_name'] ?? null,
                    'brand_name' => $it['brand_name'] ?? null,
                    'warranty' => $it['warranty'] ?? null,
                    'year' => $it['year'] ?? null,
                    'unit_cost' => $unitCost,
                    'pack_price' => isset($it['pack_price']) ? (float) $it['pack_price'] : null,
                    'quantity' => $qty,
                    'received_quantity' => $receivedQty,
                    'total_cost' => $totalCost,
                    'description' => $it['description'] ?? null,
                    'imei_serial_no' => $it['imei_serial_no'] ?? null,
                    'received_at' => $receivedAt,
                    'item_status' => $itemStatus,
                ]);

                // Increment stock only for items ready for sell and when order is completed
                if ($status === 'completed' && $isReady && !empty($it['product_id'])) {
                    if (isset($it['type']) && $it['type'] === 'lab_item') {
                        $lab = LabItem::where('business_id', $businessId)->find($it['product_id']);
                        if ($lab) {
                            $lab->increment('stock', $qty);
                            $lab->update(['last_cost' => $unitCost]);
                        }
                    } else {
                        $prod = Product::where('business_id', $businessId)->find($it['product_id']);
                        if ($prod) {
                            $prod->increment('stock_quantity', $qty);
                        }
                    }
                }
            }

            // Only adjust supplier balance & ledger if order is not draft
            if ($status === 'completed') {
                $initialSupplierBalance = (float) $supplier->balance;
                $runningBalance = $initialSupplierBalance + $grandTotal;

                // Entry 1: The Purchase Order entry (increases balance by grand total)
                SupplierLedger::create([
                    'business_id' => $businessId,
                    'supplier_id' => $supplier->id,
                    'reference_no' => $orderNo,
                    'transaction_type' => 'purchase_order',
                    'purchase_amount' => $grandTotal,
                    'payment_amount' => 0.00,
                    'balance' => $runningBalance,
                    'payment_type' => 'N/A',
                    'transaction_date' => $request->input('order_date'),
                    'description' => count($itemsData) . ' product(s) ordered' . ($request->input('notes') ? ' - ' . $request->input('notes') : ''),
                    'use_pos_balance' => false,
                    'created_by' => $businessId,
                    'created_by_name' => $user->name ?? $user->email ?? 'Vendor',
                    'created_at' => now()->subSeconds(10),
                ]);

                // Payment entries: separate record for Cash, Bank, Card
                // Process in chronological order (Cash first, then Bank, then Card) with increasing timestamps so they display stacked in reverse chronological order:
                // (Top: Card, Middle: Bank, Bottom: Cash, Base: Purchase Order)
                $paymentSplits = [];
                if ($cashPaid > 0) {
                    $paymentSplits[] = ['type' => 'Cash', 'amount' => $cashPaid, 'seconds' => 3];
                }
                if ($bankPaid > 0) {
                    $paymentSplits[] = ['type' => 'Bank', 'amount' => $bankPaid, 'seconds' => 2];
                }
                if ($cardPaid > 0) {
                    $paymentSplits[] = ['type' => 'Card', 'amount' => $cardPaid, 'seconds' => 1];
                }

                foreach ($paymentSplits as $split) {
                    $runningBalance = max(0.00, $runningBalance - $split['amount']);
                    SupplierLedger::create([
                        'business_id' => $businessId,
                        'supplier_id' => $supplier->id,
                        'reference_no' => $orderNo,
                        'transaction_type' => 'payment',
                        'purchase_amount' => 0.00,
                        'payment_amount' => $split['amount'],
                        'balance' => $runningBalance,
                        'payment_type' => $split['type'],
                        'transaction_date' => $request->input('order_date'),
                        'description' => 'Purchase Order Payment',
                        'use_pos_balance' => $request->boolean('use_today_sales', false),
                        'created_by' => $businessId,
                        'created_by_name' => $user->name ?? $user->email ?? 'Vendor',
                        'created_at' => now()->subSeconds($split['seconds']),
                    ]);
                }

                $supplier->update(['balance' => $runningBalance]);
            }

            DB::commit();

            $order->load(['items', 'supplier']);

            return response()->json([
                'status' => true,
                'message' => $status === 'draft' ? 'Purchase order draft saved.' : 'Purchase order created successfully.',
                'data' => $order,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Failed to create purchase order: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show single Purchase Order with items and supplier.
     */
    public function show($id)
    {
        $businessId = auth()->id();
        $order = PurchaseOrder::where('business_id', $businessId)
            ->where('is_deleted', false)
            ->with(['items', 'supplier'])
            ->find($id);

        if (!$order) {
            return response()->json([
                'status' => false,
                'message' => 'Purchase order not found',
            ], 404);
        }

        $payments = SupplierLedger::where('business_id', $businessId)
            ->where('supplier_id', $order->supplier_id)
            ->where('reference_no', $order->order_no)
            ->where('transaction_type', 'payment')
            ->where('is_deleted', false)
            ->orderBy('created_at', 'asc')
            ->get();

        $orderData = $order->toArray();
        $orderData['payment_logs'] = $payments;

        return response()->json([
            'status' => true,
            'data' => $orderData,
        ], 200);
    }

    /**
     * Show single Purchase Order by order_no (useful for Ledger reference receipts).
     */
    public function showByOrderNo($orderNo)
    {
        $businessId = auth()->id();
        $order = PurchaseOrder::where('business_id', $businessId)
            ->where('is_deleted', false)
            ->where('order_no', $orderNo)
            ->with(['items', 'supplier'])
            ->first();

        if (!$order) {
            return response()->json([
                'status' => false,
                'message' => 'Purchase order not found',
            ], 404);
        }

        // Fetch payment logs associated with this purchase order
        $payments = SupplierLedger::where('business_id', $businessId)
            ->where('supplier_id', $order->supplier_id)
            ->where('reference_no', $orderNo)
            ->where('transaction_type', 'payment')
            ->where('is_deleted', false)
            ->orderBy('created_at', 'asc')
            ->get();

        $orderData = $order->toArray();
        $orderData['payment_logs'] = $payments;

        return response()->json([
            'status' => true,
            'data' => $orderData,
        ], 200);
    }

    /**
     * Get multiple Purchase Orders with items and supplier by order numbers or ledger IDs.
     */
    public function getMultipleOrders(Request $request)
    {
        $businessId = auth()->id();
        $orderNos = $request->input('order_nos', []);

        if (empty($orderNos)) {
            return response()->json([
                'status' => true,
                'data' => [],
            ], 200);
        }

        $orders = PurchaseOrder::where('business_id', $businessId)
            ->where('is_deleted', false)
            ->whereIn('order_no', (array) $orderNos)
            ->with(['items', 'supplier'])
            ->get();

        return response()->json([
            'status' => true,
            'data' => $orders,
        ], 200);
    }

    /**
     * Fulfill / Receive items on a Purchase Order.
     */
    public function receiveItems(Request $request, $id)
    {
        $businessId = auth()->id();

        $order = PurchaseOrder::where('business_id', $businessId)
            ->where('is_deleted', false)
            ->with(['items', 'supplier'])
            ->find($id);

        if (!$order) {
            return response()->json([
                'status' => false,
                'message' => 'Purchase order not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|uuid',
            'items.*.receive_qty' => 'required|integer|min:1',
            'items.*.imei_serial_no' => 'nullable|string',
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
            foreach ($request->input('items', []) as $rec) {
                $item = PurchaseOrderItem::where('purchase_order_id', $order->id)
                    ->find($rec['item_id']);

                if (!$item) {
                    continue;
                }

                $receiveQty = (int) $rec['receive_qty'];
                $pendingQty = max(0, $item->quantity - $item->received_quantity);

                if ($receiveQty > $pendingQty) {
                    return response()->json([
                        'status' => false,
                        'message' => "Cannot receive {$receiveQty} units of \"{$item->product_name}\". Only {$pendingQty} pending.",
                    ], 422);
                }

                $newReceivedQty = $item->received_quantity + $receiveQty;
                $isFullyReceived = ($newReceivedQty >= $item->quantity);

                $updateData = [
                    'received_quantity' => $newReceivedQty,
                    'received_at' => now(),
                    'item_status' => $isFullyReceived ? 'ready_for_sell' : 'pending',
                ];

                if (!empty($rec['imei_serial_no'])) {
                    $existingImei = $item->imei_serial_no ? $item->imei_serial_no . ', ' : '';
                    $updateData['imei_serial_no'] = $existingImei . trim($rec['imei_serial_no']);
                }

                $item->update($updateData);

                // Increment live product or lab item stock
                if (!empty($item->product_id)) {
                    $prod = Product::where('business_id', $businessId)->find($item->product_id);
                    if ($prod) {
                        $prod->increment('stock_quantity', $receiveQty);
                    } else {
                        $lab = LabItem::where('business_id', $businessId)->find($item->product_id);
                        if ($lab) {
                            $lab->increment('stock', $receiveQty);
                        }
                    }
                }
            }

            // Recalculate parent order fulfillment status
            $allItems = PurchaseOrderItem::where('purchase_order_id', $order->id)->get();
            $allDone = true;
            $anyReceived = false;

            foreach ($allItems as $it) {
                if ($it->received_quantity < $it->quantity) {
                    $allDone = false;
                }
                if ($it->received_quantity > 0) {
                    $anyReceived = true;
                }
            }

            $orderFulfillment = 'pending';
            if ($allDone) {
                $orderFulfillment = 'fulfilled';
            } elseif ($anyReceived) {
                $orderFulfillment = 'partial';
            }

            $order->update(['fulfillment_status' => $orderFulfillment]);

            DB::commit();

            $order->load(['items', 'supplier']);

            return response()->json([
                'status' => true,
                'message' => 'Stock fulfilled successfully.',
                'data' => $order,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Fulfillment failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an existing Purchase Order (pack price, description, status, etc.).
     * Unit cost and quantity remain preserved/locked to keep ledger integrity intact.
     */
    public function update(Request $request, $id)
    {
        $businessId = auth()->id();
        $order = PurchaseOrder::where('business_id', $businessId)
            ->where('is_deleted', false)
            ->with(['items'])
            ->find($id);

        if (!$order) {
            return response()->json([
                'status' => false,
                'message' => 'Purchase order not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|uuid',
            'items.*.pack_price' => 'nullable|numeric|min:0',
            'items.*.description' => 'nullable|string',
            'items.*.item_status' => 'nullable|string|in:ready_for_sell,pending',
            'notes' => 'nullable|string',
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
            if ($request->has('notes')) {
                $order->update(['notes' => $request->input('notes')]);
            }

            foreach ($request->input('items') as $itData) {
                $item = PurchaseOrderItem::where('purchase_order_id', $order->id)
                    ->where('id', $itData['id'])
                    ->first();

                if ($item) {
                    $itemUpdate = [];
                    if (array_key_exists('pack_price', $itData)) {
                        $itemUpdate['pack_price'] = $itData['pack_price'] !== null ? (float) $itData['pack_price'] : null;
                    }
                    if (array_key_exists('description', $itData)) {
                        $itemUpdate['description'] = $itData['description'];
                    }

                    $oldStatus = $item->item_status ?: 'ready_for_sell';
                    $newStatus = array_key_exists('item_status', $itData) && !empty($itData['item_status'])
                        ? $itData['item_status']
                        : $oldStatus;

                    if ($newStatus !== $oldStatus) {
                        $itemUpdate['item_status'] = $newStatus;
                        $qty = (int) $item->quantity;

                        // When switching to ready_for_sell: mark received and increment live stock
                        if ($newStatus === 'ready_for_sell') {
                            $itemUpdate['received_quantity'] = $qty;
                            $itemUpdate['received_at'] = now();

                            if ($order->status === 'completed' && !empty($item->product_id)) {
                                $prod = Product::where('business_id', $businessId)->find($item->product_id);
                                if ($prod) {
                                    $prod->increment('stock_quantity', $qty);
                                } else {
                                    $lab = LabItem::where('business_id', $businessId)->find($item->product_id);
                                    if ($lab) {
                                        $lab->increment('stock', $qty);
                                    }
                                }
                            }
                        }
                        // When switching to pending (vice-versa): revert received and decrement stock
                        elseif ($newStatus === 'pending') {
                            $prevReceived = (int) ($item->received_quantity ?? $qty);
                            $itemUpdate['received_quantity'] = 0;
                            $itemUpdate['received_at'] = null;

                            if ($order->status === 'completed' && !empty($item->product_id) && $prevReceived > 0) {
                                $prod = Product::where('business_id', $businessId)->find($item->product_id);
                                if ($prod) {
                                    $newStock = max(0, (int) $prod->stock_quantity - $prevReceived);
                                    $prod->update(['stock_quantity' => $newStock]);
                                } else {
                                    $lab = LabItem::where('business_id', $businessId)->find($item->product_id);
                                    if ($lab) {
                                        $newStock = max(0, (int) $lab->stock - $prevReceived);
                                        $lab->update(['stock' => $newStock]);
                                    }
                                }
                            }
                        }
                    }

                    if (!empty($itemUpdate)) {
                        $item->update($itemUpdate);
                    }
                }
            }

            // Recalculate parent order fulfillment status
            $allItems = PurchaseOrderItem::where('purchase_order_id', $order->id)->get();
            $allDone = true;
            $anyReceived = false;

            foreach ($allItems as $it) {
                if ($it->item_status !== 'ready_for_sell' || $it->received_quantity < $it->quantity) {
                    $allDone = false;
                }
                if ($it->item_status === 'ready_for_sell' || $it->received_quantity > 0) {
                    $anyReceived = true;
                }
            }

            $orderFulfillment = 'pending';
            if ($allDone) {
                $orderFulfillment = 'fulfilled';
            } elseif ($anyReceived) {
                $orderFulfillment = 'partial';
            }

            $order->update(['fulfillment_status' => $orderFulfillment]);

            DB::commit();

            $order->load(['items', 'supplier']);

            return response()->json([
                'status' => true,
                'message' => 'Purchase order updated successfully',
                'data' => $order,
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Failed to update order: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Map numeric or text condition to human-readable label.
     */
    protected function formatConditionLabel($condition): string
    {
        $map = [
            1 => 'Brand New',
            2 => 'Refurbished',
            3 => 'Like New',
            4 => 'Excellent',
            5 => 'Good',
            6 => 'Fair',
            7 => 'For Parts / Not Working',
        ];

        if (is_numeric($condition) && isset($map[(int) $condition])) {
            return $map[(int) $condition];
        }

        return is_string($condition) && trim($condition) !== ''
            ? $condition
            : 'Brand New';
    }

    /**
     * Resolve full storage URL for a media path.
     */
    protected function resolveMediaUrl(?string $mediaPath): ?string
    {
        if (!$mediaPath) {
            return null;
        }
        if (str_starts_with($mediaPath, 'http://') || str_starts_with($mediaPath, 'https://')) {
            return $mediaPath;
        }
        return asset('storage/' . ltrim($mediaPath, '/'));
    }

    /**
     * Generate an 8-digit unique random order number.
     */
    private function generateUniqueOrderNo()
    {
        do {
            $num = (string) rand(10000000, 99999999);
        } while (PurchaseOrder::where('order_no', $num)->exists());

        return $num;
    }
}
