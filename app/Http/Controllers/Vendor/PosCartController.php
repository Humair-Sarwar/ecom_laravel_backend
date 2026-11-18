<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessGroup;
use App\Models\PosOrder;
use App\Models\PosOrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PosCartController extends Controller
{
    private function resolveActiveBusiness(Request $request): ?Business
    {
        $activeBiz = $request->attributes->get('current_business');
        if ($activeBiz instanceof Business) {
            return $activeBiz;
        }

        $user = $request->user();
        if (!$user) {
            return null;
        }

        $ownedGroupIds = BusinessGroup::where('owner_id', $user->id)
            ->where('status', 'active')
            ->pluck('id');

        return Business::where(function ($q) use ($user, $ownedGroupIds) {
            $q->where('owner_id', $user->id);
            if ($ownedGroupIds->isNotEmpty()) {
                $q->orWhereIn('business_group_id', $ownedGroupIds);
            }
        })->where('status', 'active')->orderBy('id', 'asc')->first();
    }

    private function generateOrderNo(Business $business): string
    {
        $prefix = str_pad((string) ($business->id % 1000), 3, '0', STR_PAD_LEFT) . '-' . now()->format('my');
        $count = PosOrder::where('business_id', $business->id)
            ->where('order_no', 'like', $prefix . '-%')
            ->count() + 1;

        return $prefix . '-' . str_pad((string) $count, 2, '0', STR_PAD_LEFT);
    }

    private function getProcessCounts(int $businessId): array
    {
        $rows = PosOrder::where('business_id', $businessId)
            ->where('is_deleted', false)
            ->whereIn('status', ['cart', 'pending', 'in_progress', 'completed'])
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'pending' => (int) (($rows['pending'] ?? 0) + ($rows['cart'] ?? 0)),
            'in_progress' => (int) ($rows['in_progress'] ?? 0),
            'completed' => (int) ($rows['completed'] ?? 0),
        ];
    }

    private function getActiveCart(Business $business, $user): ?PosOrder
    {
        return PosOrder::with('items')
            ->where('business_id', $business->id)
            ->where('status', 'cart')
            ->where('is_deleted', false)
            ->when($user?->id, fn ($q) => $q->where('created_by', $user->id))
            ->orderBy('updated_at', 'desc')
            ->first();
    }

    private function recalculateOrderTotals(PosOrder $order): PosOrder
    {
        $items = $order->items()->get();
        $subtotal = 0.0;
        $discount = 0.0;

        foreach ($items as $item) {
            $gross = round((float) $item->unit_price * (int) $item->qty, 2);
            $itemDiscount = round((float) $item->discount_amount, 2);
            $lineTotal = max(0.0, round($gross - $itemDiscount, 2));
            if ((float) $item->line_total !== $lineTotal) {
                $item->line_total = $lineTotal;
                $item->save();
            }
            $subtotal += $gross;
            $discount += $itemDiscount;
        }

        $total = max(0.0, round($subtotal - $discount + (float) $order->tax_amount, 2));
        $order->subtotal = round($subtotal, 2);
        $order->discount_amount = round($discount, 2);
        $order->total_amount = $total;
        $order->balance_amount = round($total - (float) $order->paid_amount, 2);
        $order->save();

        return $order->fresh(['items']);
    }

    private function shouldAffectStock(string $transactionType): bool
    {
        return in_array($transactionType, ['sale', 'replace'], true);
    }

    /**
     * Adjust product stock_quantity by $delta (negative = deduct, positive = restore).
     * Returns null on success or an error string when stock is insufficient.
     */
    private function adjustProductStock(?string $productId, ?string $variationId, int $delta, string $transactionType = 'sale'): ?string
    {
        if ($delta === 0 || !$this->shouldAffectStock($transactionType)) {
            return null;
        }

        $targetId = $variationId ?: $productId;
        if (!$targetId) {
            return null;
        }

        $product = Product::where('id', $targetId)
            ->where('is_deleted', false)
            ->lockForUpdate()
            ->first();

        if (!$product) {
            return null;
        }

        $isManaged = $product->apply_stock_strictly === null ? true : (bool) $product->apply_stock_strictly;
        if (!$isManaged) {
            return null;
        }

        $currentStock = (int) ($product->stock_quantity ?? 0);
        if ($delta < 0) {
            $needed = abs($delta);
            if ($currentStock < $needed) {
                return $currentStock <= 0
                    ? 'Product is out of stock.'
                    : "Only {$currentStock} pcs available in stock.";
            }
        }

        $nextStock = max(0, $currentStock + $delta);
        $product->stock_quantity = $nextStock;
        $product->product_status_stock = $nextStock > 0;
        $product->save();

        return null;
    }

    private function cartPayload(Business $business, ?PosOrder $cart): array
    {
        return [
            'cart' => $cart,
            'counts' => $this->getProcessCounts($business->id),
        ];
    }

    public function getCart(Request $request)
    {
        $business = $this->resolveActiveBusiness($request);
        if (!$business) {
            return response()->json(['status' => false, 'message' => 'Active business not found.'], 404);
        }

        $user = $request->user();

        if ($request->boolean('park_on_load')) {
            $openCarts = PosOrder::where('business_id', $business->id)
                ->where('status', 'cart')
                ->where('is_deleted', false)
                ->when($user?->id, fn ($q) => $q->where('created_by', $user->id))
                ->get();

            foreach ($openCarts as $openCart) {
                $openCart->status = 'pending';
                $openCart->save();
            }

            return response()->json([
                'status' => true,
                'data' => $this->cartPayload($business, null),
            ]);
        }

        $cart = $this->getActiveCart($business, $user);

        return response()->json([
            'status' => true,
            'data' => $this->cartPayload($business, $cart),
        ]);
    }

    public function createOrder(Request $request)
    {
        $business = $this->resolveActiveBusiness($request);
        if (!$business) {
            return response()->json(['status' => false, 'message' => 'Active business not found.'], 404);
        }

        $user = $request->user();
        $cart = $this->getActiveCart($business, $user);

        if (!$cart) {
            $cart = PosOrder::create([
                'business_id' => $business->id,
                'order_no' => $this->generateOrderNo($business),
                'status' => 'cart',
                'transaction_type' => $request->input('transaction_type', 'sale'),
                'created_by' => $user?->id,
                'created_by_name' => $user?->name ?? 'Staff',
            ]);
            $cart = $cart->fresh(['items']);
        }

        return response()->json([
            'status' => true,
            'message' => 'New order created.',
            'data' => $this->cartPayload($business, $cart),
        ]);
    }

    public function addItem(Request $request)
    {
        $business = $this->resolveActiveBusiness($request);
        if (!$business) {
            return response()->json(['status' => false, 'message' => 'Active business not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'product_id' => 'required|uuid',
            'variation_id' => 'nullable|uuid',
            'qty' => 'nullable|integer|min:1|max:9999',
            'transaction_type' => 'nullable|string|in:sale,return,replace,trade',
            'unit_price' => 'nullable|numeric|min:0',
            'product_name' => 'nullable|string|max:500',
            'sku' => 'nullable|string|max:150',
            'vat_status' => 'nullable|string|max:100',
            'imei_serial_no' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $user = $request->user();
        $qty = (int) ($request->input('qty', 1));
        $txType = $request->input('transaction_type', 'sale');
        $product = Product::where('id', $request->input('product_id'))
            ->where('is_deleted', false)
            ->first();

        $unitPrice = $request->filled('unit_price')
            ? (float) $request->input('unit_price')
            : (float) (($product?->sale_price > 0 ? $product->sale_price : $product?->regular_price) ?? 0);

        $productName = $product?->title ?? $request->input('product_name', 'Untitled Product');
        $sku = $product?->product_sku ?? $request->input('sku');
        $vatStatus = $request->input('vat_status', 'VAT Exempt');
        $imei = $request->input('imei_serial_no');

        $variationId = $request->input('variation_id');
        $imei = trim((string) ($request->input('imei_serial_no') ?? '')) ?: null;

        if ($imei !== null) {
            $alreadyInCart = PosOrderItem::query()
                ->join('pos_orders', 'pos_orders.id', '=', 'pos_order_items.pos_order_id')
                ->where('pos_orders.business_id', $business->id)
                ->where('pos_orders.is_deleted', false)
                ->whereIn('pos_orders.status', ['cart', 'pending', 'in_progress'])
                ->where('pos_order_items.imei_serial_no', $imei)
                ->select(
                    'pos_orders.id as order_id',
                    'pos_orders.order_no',
                    'pos_orders.status as order_status',
                    'pos_order_items.product_name',
                    'pos_order_items.imei_serial_no'
                )
                ->first();

            if ($alreadyInCart) {
                return response()->json([
                    'status' => false,
                    'code' => 'SERIALIZED_ALREADY_IN_CART',
                    'message' => "Product {$imei} is already added in cart.",
                    'existing_item' => $alreadyInCart,
                ], 409);
            }
        }

        $result = DB::transaction(function () use ($business, $user, $request, $qty, $txType, $unitPrice, $productName, $sku, $vatStatus, $imei, $variationId) {
            $stockError = $this->adjustProductStock($request->input('product_id'), $variationId, -$qty, $txType);
            if ($stockError !== null) {
                return ['error' => $stockError];
            }

            $cart = $this->getActiveCart($business, $user);

            if (!$cart) {
                $cart = PosOrder::create([
                    'business_id' => $business->id,
                    'order_no' => $this->generateOrderNo($business),
                    'status' => 'cart',
                    'transaction_type' => $txType,
                    'created_by' => $user?->id,
                    'created_by_name' => $user?->name ?? 'Staff',
                ]);
            }

            $disallowDuplicate = (bool) ($business->disallow_pos_duplicate_cart ?? false);
            $existing = null;

            if ($disallowDuplicate && !$imei) {
                $existingQuery = PosOrderItem::where('pos_order_id', $cart->id)
                    ->where('product_id', $request->input('product_id'))
                    ->where('transaction_type', $txType);

                if ($variationId) {
                    $existingQuery->where('variation_id', $variationId);
                } else {
                    $existingQuery->whereNull('variation_id');
                }

                $existing = $existingQuery->whereNull('imei_serial_no')->first();
            }

            if ($existing) {
                $newQty = $existing->qty + $qty;
                $existing->qty = $newQty;
                $existing->line_total = max(0.0, round(($existing->unit_price * $newQty) - $existing->discount_amount, 2));
                $existing->save();
            } else {
                PosOrderItem::create([
                    'pos_order_id' => $cart->id,
                    'product_id' => $request->input('product_id'),
                    'variation_id' => $variationId,
                    'transaction_type' => $txType,
                    'product_name' => $productName,
                    'sku' => $sku,
                    'vat_status' => $vatStatus,
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'discount_amount' => 0.00,
                    'discount_type' => 'fixed',
                    'line_total' => round($unitPrice * $qty, 2),
                    'imei_serial_no' => $imei,
                ]);
            }

            return ['cart' => $this->recalculateOrderTotals($cart)];
        });

        if (isset($result['error'])) {
            return response()->json([
                'status' => false,
                'message' => $result['error'],
            ], 422);
        }

        return response()->json([
            'status' => true,
            'message' => 'Item added to cart.',
            'data' => $this->cartPayload($business, $result['cart']),
        ]);
    }

    public function updateItem(Request $request, string $itemId)
    {
        $business = $this->resolveActiveBusiness($request);
        if (!$business) {
            return response()->json(['status' => false, 'message' => 'Active business not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'qty' => 'nullable|integer|min:0|max:9999',
            'unit_price' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'imei_serial_no' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
        }

        $cart = $this->getActiveCart($business, $request->user());
        if (!$cart) {
            return response()->json(['status' => false, 'message' => 'Active cart not found.'], 404);
        }

        $item = PosOrderItem::where('id', $itemId)
            ->where('pos_order_id', $cart->id)
            ->first();

        if (!$item) {
            return response()->json(['status' => false, 'message' => 'Cart item not found.'], 404);
        }

        $result = DB::transaction(function () use ($request, $cart, $item) {
            if ($request->has('qty')) {
                $newQty = (int) $request->input('qty');
                $oldQty = (int) $item->qty;
                $stockDelta = $oldQty - $newQty; // positive restores stock, negative deducts stock
                $stockError = $this->adjustProductStock(
                    $item->product_id,
                    $item->variation_id,
                    $stockDelta,
                    $item->transaction_type ?? 'sale'
                );
                if ($stockError !== null) {
                    return ['error' => $stockError];
                }
            }

            if ($request->has('qty') && (int) $request->input('qty') <= 0) {
                $item->delete();
            } else {
                if ($request->has('qty')) {
                    $item->qty = (int) $request->input('qty');
                }
                if ($request->has('unit_price')) {
                    $item->unit_price = (float) $request->input('unit_price');
                }
                if ($request->has('discount_amount')) {
                    $item->discount_amount = (float) $request->input('discount_amount');
                }
                if ($request->has('imei_serial_no')) {
                    $item->imei_serial_no = $request->input('imei_serial_no');
                }
                if ($request->has('notes')) {
                    $item->notes = $request->input('notes');
                }
                $item->line_total = max(0.0, round(($item->unit_price * $item->qty) - $item->discount_amount, 2));
                $item->save();
            }

            return ['cart' => $this->recalculateOrderTotals($cart)];
        });

        if (isset($result['error'])) {
            return response()->json(['status' => false, 'message' => $result['error']], 422);
        }

        return response()->json([
            'status' => true,
            'data' => $this->cartPayload($business, $result['cart']),
        ]);
    }

    public function removeItem(Request $request, string $itemId)
    {
        $business = $this->resolveActiveBusiness($request);
        if (!$business) {
            return response()->json(['status' => false, 'message' => 'Active business not found.'], 404);
        }

        $cart = $this->getActiveCart($business, $request->user());
        if (!$cart) {
            return response()->json(['status' => false, 'message' => 'Active cart not found.'], 404);
        }

        $cart = DB::transaction(function () use ($cart, $itemId) {
            $item = PosOrderItem::where('id', $itemId)
                ->where('pos_order_id', $cart->id)
                ->first();

            if ($item) {
                $this->adjustProductStock(
                    $item->product_id,
                    $item->variation_id,
                    (int) $item->qty,
                    $item->transaction_type ?? 'sale'
                );
                $item->delete();
            }

            return $this->recalculateOrderTotals($cart);
        });

        return response()->json([
            'status' => true,
            'data' => $this->cartPayload($business, $cart),
        ]);
    }

    public function clearCart(Request $request)
    {
        $business = $this->resolveActiveBusiness($request);
        if (!$business) {
            return response()->json(['status' => false, 'message' => 'Active business not found.'], 404);
        }

        $cart = $this->getActiveCart($business, $request->user());
        if ($cart) {
            DB::transaction(function () use ($cart) {
                foreach ($cart->items as $item) {
                    $this->adjustProductStock(
                        $item->product_id,
                        $item->variation_id,
                        (int) $item->qty,
                        $item->transaction_type ?? 'sale'
                    );
                }
                PosOrderItem::where('pos_order_id', $cart->id)->delete();
                $cart->is_deleted = true;
                $cart->save();
            });
        }

        return response()->json([
            'status' => true,
            'message' => 'Cart cleared.',
            'data' => $this->cartPayload($business, null),
        ]);
    }

    public function saveStatus(Request $request)
    {
        $business = $this->resolveActiveBusiness($request);
        if (!$business) {
            return response()->json(['status' => false, 'message' => 'Active business not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:pending,in_progress,completed',
            'notes' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
        }

        $cart = $this->getActiveCart($business, $request->user());
        if (!$cart || $cart->items->isEmpty()) {
            return response()->json(['status' => false, 'message' => 'Please add at least one item to cart first.'], 422);
        }

        $targetStatus = $request->input('status');
        $cart->status = $targetStatus;
        if ($request->filled('notes')) {
            $cart->notes = $request->input('notes');
        }
        if ($targetStatus === 'completed') {
            $cart->completed_at = now();
        }
        $cart->save();

        return response()->json([
            'status' => true,
            'message' => 'Order saved as ' . str_replace('_', '-', $targetStatus) . '.',
            'data' => $this->cartPayload($business, null),
        ]);
    }

    public function listOrders(Request $request)
    {
        $business = $this->resolveActiveBusiness($request);
        if (!$business) {
            return response()->json(['status' => false, 'message' => 'Active business not found.'], 404);
        }

        $status = $request->query('status');
        $search = trim((string) $request->query('search', ''));

        $query = PosOrder::with('items')
            ->where('business_id', $business->id)
            ->where('is_deleted', false);

        if ($status === 'pending') {
            $query->whereIn('status', ['pending', 'cart']);
        } elseif ($status && in_array($status, ['in_progress', 'completed'], true)) {
            $query->where('status', $status);
        } else {
            $query->whereIn('status', ['cart', 'pending', 'in_progress', 'completed']);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('order_no', 'ilike', "%{$search}%")
                  ->orWhere('customer_name', 'ilike', "%{$search}%")
                  ->orWhere('customer_phone', 'ilike', "%{$search}%");
            });
        }

        $orders = $query->orderBy('updated_at', 'desc')->limit(100)->get();

        return response()->json([
            'status' => true,
            'data' => [
                'orders' => $orders,
                'counts' => $this->getProcessCounts($business->id),
            ],
        ]);
    }

    public function loadOrderToCart(Request $request, string $id)
    {
        $business = $this->resolveActiveBusiness($request);
        if (!$business) {
            return response()->json(['status' => false, 'message' => 'Active business not found.'], 404);
        }

        $order = PosOrder::with('items')
            ->where('id', $id)
            ->where('business_id', $business->id)
            ->where('is_deleted', false)
            ->first();

        if (!$order) {
            return response()->json(['status' => false, 'message' => 'Order not found.'], 404);
        }

        $user = $request->user();
        DB::transaction(function () use ($business, $user, $order) {
            $currentCart = $this->getActiveCart($business, $user);
            if ($currentCart && $currentCart->id !== $order->id) {
                if ($currentCart->items->isEmpty()) {
                    $currentCart->is_deleted = true;
                    $currentCart->save();
                } else {
                    $currentCart->status = 'in_progress';
                    $currentCart->save();
                }
            }

            $order->status = 'cart';
            $order->created_by = $user?->id ?? $order->created_by;
            $order->save();
        });

        return response()->json([
            'status' => true,
            'message' => 'Order loaded into cart.',
            'data' => $this->cartPayload($business, $order->fresh(['items'])),
        ]);
    }

    public function deleteOrders(Request $request)
    {
        $business = $this->resolveActiveBusiness($request);
        if (!$business) {
            return response()->json(['status' => false, 'message' => 'Active business not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'order_ids' => 'required|array|min:1',
            'order_ids.*' => 'required|uuid',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
        }

        $orders = PosOrder::with('items')
            ->where('business_id', $business->id)
            ->whereIn('id', $request->input('order_ids'))
            ->where('is_deleted', false)
            ->get();

        DB::transaction(function () use ($orders) {
            foreach ($orders as $order) {
                foreach ($order->items as $item) {
                    $this->adjustProductStock(
                        $item->product_id,
                        $item->variation_id,
                        (int) $item->qty,
                        $item->transaction_type ?? 'sale'
                    );
                }
                $order->is_deleted = true;
                $order->save();
            }
        });

        return response()->json([
            'status' => true,
            'message' => 'Selected orders deleted.',
            'data' => [
                'counts' => $this->getProcessCounts($business->id),
            ],
        ]);
    }
}
