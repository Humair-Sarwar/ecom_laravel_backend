<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\LabItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\Supplier;
use App\Models\SupplierLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PurchaseReturnController extends Controller
{
    /**
     * List Purchase Return Orders with date range, supplier filter, search & metrics.
     */
    public function index(Request $request)
    {
        $businessId = auth()->id();

        $query = PurchaseReturn::where('business_id', $businessId)
            ->where('is_deleted', false)
            ->with(['supplier', 'items']);

        if ($request->filled('supplier_id') && $request->input('supplier_id') !== 'all') {
            $query->where('supplier_id', $request->input('supplier_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('return_date', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('return_date', '<=', $request->input('date_to'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('return_no', 'like', "%{$search}%")
                  ->orWhere('purchase_order_no', 'like', "%{$search}%")
                  ->orWhere('created_by_name', 'like', "%{$search}%")
                  ->orWhereHas('supplier', function ($sq) use ($search) {
                      $sq->where('supplier_name', 'like', "%{$search}%")
                         ->orWhere('full_name', 'like', "%{$search}%");
                  });
            });
        }

        $totalReturnsCount = (clone $query)->count();

        $perPage = (int) $request->input('per_page', 15);
        if ($perPage <= 0) {
            $perPage = 15;
        }

        $returns = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $formatted = $returns->getCollection()->map(function ($ret) {
            $arr = $ret->toArray();
            $arr['payment_logs'] = $this->buildPaymentLogs($ret);
            $arr['items_count'] = (int) ($ret->total_items ?: count($arr['items'] ?? []));
            return $arr;
        });

        return response()->json([
            'status' => true,
            'data' => $formatted,
            'pagination' => [
                'total' => $returns->total(),
                'per_page' => $returns->perPage(),
                'current_page' => $returns->currentPage(),
                'last_page' => $returns->lastPage(),
                'from' => $returns->firstItem() ?: 0,
                'to' => $returns->lastItem() ?: 0,
            ],
            'metrics' => [
                'total_returns' => $totalReturnsCount,
                'showing' => $returns->count(),
            ],
        ], 200);
    }

    /**
     * Look up a completed Purchase Order by its receipt / order number with available returnable quantities.
     */
    public function lookupReceipt(Request $request)
    {
        $businessId = auth()->id();
        $receiptNo = trim((string) $request->input('receipt_no', ''));

        if ($receiptNo === '') {
            return response()->json([
                'status' => false,
                'message' => 'Please enter a receipt number',
            ], 422);
        }

        $order = PurchaseOrder::where('business_id', $businessId)
            ->where('status', 'completed')
            ->where('is_deleted', false)
            ->where('order_no', $receiptNo)
            ->with(['supplier', 'items'])
            ->first();

        if (!$order) {
            return response()->json([
                'status' => false,
                'message' => "Purchase order #{$receiptNo} not found",
            ], 404);
        }

        $orderArr = $order->toArray();
        $itemIds = $order->items->pluck('id')->all();

        $returnedSums = [];
        if (!empty($itemIds)) {
            $rows = PurchaseReturnItem::select('purchase_order_item_id', DB::raw('SUM(quantity) as returned_qty'))
                ->whereIn('purchase_order_item_id', $itemIds)
                ->whereHas('purchaseReturn', function ($q) {
                    $q->where('is_deleted', false);
                })
                ->groupBy('purchase_order_item_id')
                ->get();

            foreach ($rows as $r) {
                $returnedSums[$r->purchase_order_item_id] = (int) $r->returned_qty;
            }
        }

        $orderArr['items'] = collect($orderArr['items'] ?? [])->map(function ($it) use ($returnedSums) {
            $purchasedQty = (int) ($it['quantity'] ?? 0);
            $returnedQty = (int) ($returnedSums[$it['id']] ?? 0);
            $it['returned_qty'] = $returnedQty;
            $it['available_qty'] = max(0, $purchasedQty - $returnedQty);
            return $it;
        })->values()->all();

        return response()->json([
            'status' => true,
            'data' => $orderArr,
        ], 200);
    }

    /**
     * Create a new Purchase Return Order, decrement stock, record refund logs & adjust supplier balance.
     */
    public function store(Request $request)
    {
        $businessId = auth()->id();
        $user = auth()->user();

        $validator = Validator::make($request->all(), [
            'purchase_order_id' => 'required|uuid',
            'items' => 'required|array|min:1',
            'items.*.purchase_order_item_id' => 'required|uuid',
            'items.*.quantity' => 'required|integer|min:1',
            'cash_paid' => 'nullable|numeric|min:0',
            'card_paid' => 'nullable|numeric|min:0',
            'bank_paid' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $order = PurchaseOrder::where('business_id', $businessId)
            ->where('status', 'completed')
            ->where('is_deleted', false)
            ->with(['supplier', 'items'])
            ->find($request->input('purchase_order_id'));

        if (!$order) {
            return response()->json([
                'status' => false,
                'message' => 'Purchase order not found',
            ], 404);
        }

        $supplier = $order->supplier;
        if (!$supplier) {
            return response()->json([
                'status' => false,
                'message' => 'Associated supplier not found',
            ], 404);
        }

        DB::beginTransaction();
        try {
            $poItemsById = $order->items->keyBy('id');
            $reqItems = $request->input('items', []);

            $totalAmount = 0.00;
            $totalItemsQty = 0;
            $preparedItems = [];

            foreach ($reqItems as $reqIt) {
                $poItemId = $reqIt['purchase_order_item_id'];
                /** @var PurchaseOrderItem|null $poItem */
                $poItem = $poItemsById->get($poItemId);
                if (!$poItem) {
                    DB::rollBack();
                    return response()->json([
                        'status' => false,
                        'message' => 'One or more selected items do not belong to this purchase order.',
                    ], 422);
                }

                $alreadyReturned = (int) PurchaseReturnItem::where('purchase_order_item_id', $poItem->id)
                    ->whereHas('purchaseReturn', function ($q) {
                        $q->where('is_deleted', false);
                    })
                    ->sum('quantity');

                $availableQty = max(0, (int) $poItem->quantity - $alreadyReturned);
                $qtyToReturn = (int) $reqIt['quantity'];

                if ($qtyToReturn > $availableQty) {
                    DB::rollBack();
                    return response()->json([
                        'status' => false,
                        'message' => "Return quantity ({$qtyToReturn}) exceeds available quantity ({$availableQty}) for \"{$poItem->product_name}\".",
                    ], 422);
                }

                $unitCost = (float) $poItem->unit_cost;
                $lineTotal = $unitCost * $qtyToReturn;
                $totalAmount += $lineTotal;
                $totalItemsQty += $qtyToReturn;

                $preparedItems[] = [
                    'po_item' => $poItem,
                    'quantity' => $qtyToReturn,
                    'unit_cost' => $unitCost,
                    'total_cost' => $lineTotal,
                ];
            }

            $cashPaid = (float) $request->input('cash_paid', 0);
            $cardPaid = (float) $request->input('card_paid', 0);
            $bankPaid = (float) $request->input('bank_paid', 0);
            $returnAmount = $cashPaid + $cardPaid + $bankPaid;

            if ($returnAmount > ($totalAmount + 0.01)) {
                DB::rollBack();
                return response()->json([
                    'status' => false,
                    'message' => 'Refund amount cannot exceed total return amount.',
                ], 422);
            }

            $remainingAmount = max(0.00, $totalAmount - $returnAmount);
            $returnNo = $this->generateUniqueReturnNo();
            $creatorName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))
                ?: ($user->name ?? $user->email ?? 'Vendor');

            $purchaseReturn = PurchaseReturn::create([
                'business_id' => $businessId,
                'supplier_id' => $supplier->id,
                'purchase_order_id' => $order->id,
                'purchase_order_no' => $order->order_no,
                'return_no' => $returnNo,
                'return_date' => now()->toDateString(),
                'total_items' => $totalItemsQty,
                'total_amount' => $totalAmount,
                'return_amount' => $returnAmount,
                'remaining_amount' => $remainingAmount,
                'cash_paid' => $cashPaid,
                'card_paid' => $cardPaid,
                'bank_paid' => $bankPaid,
                'notes' => $request->input('notes'),
                'created_by' => $businessId,
                'created_by_name' => $creatorName,
            ]);

            foreach ($preparedItems as $prep) {
                /** @var PurchaseOrderItem $poItem */
                $poItem = $prep['po_item'];
                $qtyToReturn = $prep['quantity'];

                PurchaseReturnItem::create([
                    'purchase_return_id' => $purchaseReturn->id,
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $poItem->product_id,
                    'product_name' => $poItem->product_name,
                    'sku' => $poItem->sku,
                    'barcode' => $poItem->barcode,
                    'unit_cost' => $prep['unit_cost'],
                    'quantity' => $qtyToReturn,
                    'total_cost' => $prep['total_cost'],
                    'imei_serial_no' => $poItem->imei_serial_no,
                ]);

                // Decrement stock if item was received into stock
                if (!empty($poItem->product_id) && (int) ($poItem->received_quantity ?? $poItem->quantity) > 0) {
                    $prod = Product::where('business_id', $businessId)->find($poItem->product_id);
                    if ($prod) {
                        $prod->update(['stock_quantity' => max(0, (int) $prod->stock_quantity - $qtyToReturn)]);
                    } else {
                        $lab = LabItem::where('business_id', $businessId)->find($poItem->product_id);
                        if ($lab) {
                            $lab->update(['stock' => max(0, (int) $lab->stock - $qtyToReturn)]);
                        }
                    }
                }
            }

            // Reduce supplier payable balance by un-refunded remaining credit amount
            if ($remainingAmount > 0) {
                $newBalance = max(0.00, (float) $supplier->balance - $remainingAmount);
                SupplierLedger::create([
                    'business_id' => $businessId,
                    'supplier_id' => $supplier->id,
                    'reference_no' => $returnNo,
                    'transaction_type' => 'purchase_return',
                    'purchase_amount' => 0.00,
                    'payment_amount' => $remainingAmount,
                    'balance' => $newBalance,
                    'payment_type' => 'Return Credit',
                    'transaction_date' => now()->toDateString(),
                    'description' => "Purchase Return #{$returnNo} (PO #{$order->order_no})" . ($request->input('notes') ? ' - ' . $request->input('notes') : ''),
                    'use_pos_balance' => false,
                    'created_by' => $businessId,
                    'created_by_name' => $creatorName,
                ]);
                $supplier->update(['balance' => $newBalance]);
            }

            DB::commit();

            $purchaseReturn->load(['supplier', 'items', 'purchaseOrder']);
            $data = $purchaseReturn->toArray();
            $data['payment_logs'] = $this->buildPaymentLogs($purchaseReturn);

            return response()->json([
                'status' => true,
                'message' => 'Purchase return created successfully',
                'data' => $data,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Failed to create purchase return: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show a single Purchase Return Order receipt with payment logs.
     */
    public function show($id)
    {
        $businessId = auth()->id();
        $returnOrder = PurchaseReturn::where('business_id', $businessId)
            ->where('is_deleted', false)
            ->with(['supplier', 'items', 'purchaseOrder'])
            ->find($id);

        if (!$returnOrder) {
            return response()->json([
                'status' => false,
                'message' => 'Return order not found',
            ], 404);
        }

        $data = $returnOrder->toArray();
        $data['payment_logs'] = $this->buildPaymentLogs($returnOrder);

        return response()->json([
            'status' => true,
            'data' => $data,
        ], 200);
    }

    /**
     * Format refund payment logs cleanly for the return receipt.
     */
    private function buildPaymentLogs(PurchaseReturn $ret): array
    {
        $logs = [];
        $dateStr = $ret->created_at ? $ret->created_at->toIso8601String() : now()->toIso8601String();

        if ((float) $ret->cash_paid > 0) {
            $logs[] = [
                'date' => $dateStr,
                'payment_method' => 'Cash',
                'amount' => -((float) $ret->cash_paid),
            ];
        }
        if ((float) $ret->card_paid > 0) {
            $logs[] = [
                'date' => $dateStr,
                'payment_method' => 'Card',
                'amount' => -((float) $ret->card_paid),
            ];
        }
        if ((float) $ret->bank_paid > 0) {
            $logs[] = [
                'date' => $dateStr,
                'payment_method' => 'Bank Transfer',
                'amount' => -((float) $ret->bank_paid),
            ];
        }
        if ((float) $ret->remaining_amount > 0) {
            $logs[] = [
                'date' => $dateStr,
                'payment_method' => 'Supplier Balance Credit',
                'amount' => -((float) $ret->remaining_amount),
            ];
        }

        return $logs;
    }

    /**
     * Generate a unique 6-digit return receipt number.
     */
    private function generateUniqueReturnNo(): string
    {
        do {
            $num = (string) rand(100000, 999999);
        } while (PurchaseReturn::where('return_no', $num)->exists());

        return $num;
    }
}
