<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SupplierLedgerController extends Controller
{
    /**
     * Get paginated ledger history and 4 summary metrics for a supplier.
     */
    public function index(Request $request, $supplierId)
    {
        $userId = auth()->id();
        $supplier = Supplier::where('business_id', $userId)
            ->where('is_deleted', false)
            ->find($supplierId);

        if (!$supplier) {
            return response()->json([
                'status' => false,
                'message' => 'Supplier not found',
            ], 404);
        }

        $perPage = max(1, (int) $request->input('per_page', 15));
        $page = max(1, (int) $request->input('page', 1));
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $onlyPo = $request->boolean('only_po', false);

        $query = SupplierLedger::where('business_id', $userId)
            ->where('supplier_id', $supplierId)
            ->where('is_deleted', false);

        if ($onlyPo) {
            $query->where('transaction_type', 'purchase_order');
        }

        if (!empty($dateFrom)) {
            $query->whereDate('transaction_date', '>=', $dateFrom);
        }

        if (!empty($dateTo)) {
            $query->whereDate('transaction_date', '<=', $dateTo);
        }

        // Summary metrics
        $totalPurchases = (clone $query)->sum('purchase_amount');
        $totalPayments = (clone $query)->sum('payment_amount');

        // Start balance calculation:
        // 1. If date_from filter is provided, start_balance is the balance immediately prior to date_from
        // 2. If no date filter or across all time, start_balance represents initial recorded balance (such as previous_balance)
        if (!empty($dateFrom)) {
            $priorPurchases = SupplierLedger::where('business_id', $userId)
                ->where('supplier_id', $supplierId)
                ->where('is_deleted', false)
                ->whereDate('transaction_date', '<', $dateFrom)
                ->sum('purchase_amount');

            $priorPayments = SupplierLedger::where('business_id', $userId)
                ->where('supplier_id', $supplierId)
                ->where('is_deleted', false)
                ->whereDate('transaction_date', '<', $dateFrom)
                ->sum('payment_amount');

            $startBalance = (float) $priorPurchases - (float) $priorPayments;
        } else {
            // Initial opening/start balance recorded for this supplier (e.g. from earliest previous_balance or opening entry)
            $firstPreviousBalanceEntry = SupplierLedger::where('business_id', $userId)
                ->where('supplier_id', $supplierId)
                ->where('transaction_type', 'previous_balance')
                ->where('is_deleted', false)
                ->orderBy('transaction_date', 'asc')
                ->orderBy('created_at', 'asc')
                ->first();

            if ($firstPreviousBalanceEntry) {
                $startBalance = (float) $firstPreviousBalanceEntry->purchase_amount;
            } else {
                $earliestEntry = SupplierLedger::where('business_id', $userId)
                    ->where('supplier_id', $supplierId)
                    ->where('is_deleted', false)
                    ->orderBy('transaction_date', 'asc')
                    ->orderBy('created_at', 'asc')
                    ->first();
                $startBalance = $earliestEntry ? (float) ($earliestEntry->purchase_amount ?: $earliestEntry->balance) : 0.00;
            }
        }

        // Most recent payment eligible for "Update Last Payment" (only standalone payments made through + Payment modal, not PO payments)
        $lastPayment = SupplierLedger::where('business_id', $userId)
            ->where('supplier_id', $supplierId)
            ->where('transaction_type', 'payment')
            ->where(function ($q) {
                $q->whereNull('description')
                  ->orWhere('description', '!=', 'Purchase Order Payment');
            })
            ->where('is_deleted', false)
            ->orderBy('transaction_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->first();

        $paginator = $query->orderBy('transaction_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->orderByRaw("CASE WHEN transaction_type = 'payment' THEN 0 ELSE 1 END")
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'status' => true,
            'supplier' => $supplier,
            'data' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'metrics' => [
                'total_purchases' => (float) $totalPurchases,
                'total_payments' => (float) $totalPayments,
                'end_balance' => (float) $supplier->balance,
                'start_balance' => (float) $startBalance,
            ],
            'last_payment' => $lastPayment,
        ], 200);
    }

    /**
     * Get paginated purchase and payment ledger entries across all suppliers (or filtered by supplier).
     */
    public function purchasePayments(Request $request)
    {
        $userId = auth()->id();

        $perPage = max(1, (int) $request->input('per_page', 15));
        $page = max(1, (int) $request->input('page', 1));
        $supplierId = $request->input('supplier_id');
        $paymentType = $request->input('payment_type');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $onlyPo = $request->boolean('only_po', false);
        $search = trim($request->input('search', ''));

        $query = SupplierLedger::where('business_id', $userId)
            ->where('is_deleted', false)
            ->with(['supplier']);

        if (!empty($supplierId) && $supplierId !== 'all') {
            $query->where('supplier_id', $supplierId);
        }

        if (!empty($paymentType) && $paymentType !== 'all') {
            $query->where('payment_type', 'like', "%{$paymentType}%");
        }

        if ($onlyPo) {
            $query->where('transaction_type', 'purchase_order');
        }

        if (!empty($dateFrom)) {
            $query->whereDate('transaction_date', '>=', $dateFrom);
        }

        if (!empty($dateTo)) {
            $query->whereDate('transaction_date', '<=', $dateTo);
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('reference_no', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('created_by_name', 'like', "%{$search}%")
                  ->orWhereHas('supplier', function ($sq) use ($search) {
                      $sq->where('supplier_name', 'like', "%{$search}%")
                         ->orWhere('full_name', 'like', "%{$search}%");
                  });
            });
        }

        // Summary metrics
        $totalPurchases = (clone $query)->sum('purchase_amount');
        $totalPayments = (clone $query)->sum('payment_amount');

        // Start balance:
        if (!empty($dateFrom)) {
            $priorQuery = SupplierLedger::where('business_id', $userId)
                ->where('is_deleted', false)
                ->whereDate('transaction_date', '<', $dateFrom);

            if (!empty($supplierId) && $supplierId !== 'all') {
                $priorQuery->where('supplier_id', $supplierId);
            }

            $priorPurchases = (clone $priorQuery)->sum('purchase_amount');
            $priorPayments = (clone $priorQuery)->sum('payment_amount');
            $startBalance = (float) $priorPurchases - (float) $priorPayments;
        } else {
            // Opening balance: sum of opening balances (previous_balance entries)
            $startBalanceQuery = SupplierLedger::where('business_id', $userId)
                ->where('is_deleted', false)
                ->where('transaction_type', 'previous_balance');

            if (!empty($supplierId) && $supplierId !== 'all') {
                $startBalanceQuery->where('supplier_id', $supplierId);
            }

            $startBalance = (float) $startBalanceQuery->sum('purchase_amount');
        }

        // End balance: current balance of suppliers
        $endBalanceQuery = Supplier::where('business_id', $userId)->where('is_deleted', false);
        if (!empty($supplierId) && $supplierId !== 'all') {
            $endBalanceQuery->where('id', $supplierId);
        }
        $endBalance = (float) $endBalanceQuery->sum('balance');

        $paginator = $query->orderBy('transaction_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->orderByRaw("CASE WHEN transaction_type = 'payment' THEN 0 ELSE 1 END")
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
            'metrics' => [
                'total_purchases' => (float) $totalPurchases,
                'total_payments' => (float) $totalPayments,
                'end_balance' => (float) $endBalance,
                'start_balance' => (float) $startBalance,
            ],
        ], 200);
    }

    /**
     * Make Payment against supplier account.
     */
    public function makePayment(Request $request, $supplierId)
    {
        $userId = auth()->id();
        $user = auth()->user();

        $supplier = Supplier::where('business_id', $userId)
            ->where('is_deleted', false)
            ->find($supplierId);

        if (!$supplier) {
            return response()->json(['status' => false, 'message' => 'Supplier not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'transaction_date' => 'required|date',
            'payment_type' => 'required|string|max:100',
            'description' => 'nullable|string',
            'use_pos_balance' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $amount = (float) $request->amount;

        return DB::transaction(function () use ($supplier, $amount, $request, $userId, $user) {
            $newBalance = max(0, (float) $supplier->balance - $amount);
            $supplier->balance = $newBalance;
            $supplier->save();

            $referenceNo = (string) rand(10000000, 99999999);

            $ledger = SupplierLedger::create([
                'business_id' => $userId,
                'supplier_id' => $supplier->id,
                'reference_no' => $referenceNo,
                'transaction_type' => 'payment',
                'purchase_amount' => 0.00,
                'payment_amount' => $amount,
                'balance' => $newBalance,
                'payment_type' => $request->payment_type,
                'transaction_date' => $request->transaction_date,
                'description' => $request->description,
                'use_pos_balance' => $request->boolean('use_pos_balance', false),
                'created_by' => $userId,
                'created_by_name' => $user ? ($user->name ?? $user->email ?? 'Admin') : 'Admin',
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Payment recorded successfully',
                'data' => $ledger,
                'balance' => $newBalance,
            ], 201);
        });
    }

    /**
     * Add Previous Balance to supplier account.
     */
    public function addPreviousBalance(Request $request, $supplierId)
    {
        $userId = auth()->id();
        $user = auth()->user();

        $supplier = Supplier::where('business_id', $userId)
            ->where('is_deleted', false)
            ->find($supplierId);

        if (!$supplier) {
            return response()->json(['status' => false, 'message' => 'Supplier not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'transaction_date' => 'required|date',
            'description' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $amount = (float) $request->amount;

        return DB::transaction(function () use ($supplier, $amount, $request, $userId, $user) {
            $newBalance = (float) $supplier->balance + $amount;
            $supplier->balance = $newBalance;
            $supplier->save();

            $referenceNo = (string) rand(10000000, 99999999);

            $ledger = SupplierLedger::create([
                'business_id' => $userId,
                'supplier_id' => $supplier->id,
                'reference_no' => $referenceNo,
                'transaction_type' => 'previous_balance',
                'purchase_amount' => $amount,
                'payment_amount' => 0.00,
                'balance' => $newBalance,
                'payment_type' => 'N/A',
                'transaction_date' => $request->transaction_date,
                'description' => $request->description,
                'use_pos_balance' => false,
                'created_by' => $userId,
                'created_by_name' => $user ? ($user->name ?? $user->email ?? 'Admin') : 'Admin',
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Previous balance recorded successfully',
                'data' => $ledger,
                'balance' => $newBalance,
            ], 201);
        });
    }

    /**
     * Update Last Payment.
     */
    public function updateLastPayment(Request $request, $supplierId)
    {
        $userId = auth()->id();

        $supplier = Supplier::where('business_id', $userId)
            ->where('is_deleted', false)
            ->find($supplierId);

        if (!$supplier) {
            return response()->json(['status' => false, 'message' => 'Supplier not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'payment_id' => 'required|uuid',
            'amount' => 'required|numeric|min:0.01',
            'transaction_date' => 'required|date',
            'payment_type' => 'required|string|max:100',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $payment = SupplierLedger::where('business_id', $userId)
            ->where('supplier_id', $supplierId)
            ->where('transaction_type', 'payment')
            ->where('is_deleted', false)
            ->find($request->payment_id);

        if (!$payment) {
            return response()->json(['status' => false, 'message' => 'Payment transaction not found'], 404);
        }

        $oldAmount = (float) $payment->payment_amount;
        $newAmount = (float) $request->amount;
        $diff = $newAmount - $oldAmount;

        return DB::transaction(function () use ($supplier, $payment, $diff, $newAmount, $request) {
            // If new payment amount is greater, balance decreases; if smaller, balance increases
            $newBalance = max(0, (float) $supplier->balance - $diff);
            $supplier->balance = $newBalance;
            $supplier->save();

            $payment->update([
                'payment_amount' => $newAmount,
                'balance' => $newBalance,
                'transaction_date' => $request->transaction_date,
                'payment_type' => $request->payment_type,
                'description' => $request->description,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Payment updated successfully',
                'data' => $payment,
                'balance' => $newBalance,
            ], 200);
        });
    }

    /**
     * Delete Payment.
     */
    public function deletePayment(Request $request, $supplierId, $paymentId)
    {
        $userId = auth()->id();

        $supplier = Supplier::where('business_id', $userId)
            ->where('is_deleted', false)
            ->find($supplierId);

        if (!$supplier) {
            return response()->json(['status' => false, 'message' => 'Supplier not found'], 404);
        }

        $payment = SupplierLedger::where('business_id', $userId)
            ->where('supplier_id', $supplierId)
            ->where('transaction_type', 'payment')
            ->where('is_deleted', false)
            ->find($paymentId);

        if (!$payment) {
            return response()->json(['status' => false, 'message' => 'Payment transaction not found'], 404);
        }

        return DB::transaction(function () use ($supplier, $payment) {
            // Revert payment: supplier balance increases back
            $supplier->balance = (float) $supplier->balance + (float) $payment->payment_amount;
            $supplier->save();

            $payment->update(['is_deleted' => true]);

            return response()->json([
                'status' => true,
                'message' => 'Payment deleted successfully',
                'balance' => $supplier->balance,
            ], 200);
        });
    }
}
