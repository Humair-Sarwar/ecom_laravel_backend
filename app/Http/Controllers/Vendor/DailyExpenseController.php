<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessExpenseType;
use App\Models\BusinessGroup;
use App\Models\DailyExpense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DailyExpenseController extends Controller
{
    /**
     * Resolve the active business for the authenticated vendor request.
     */
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

    /**
     * Validate request payload and resolve matching daily expense type.
     */
    private function validateAndResolveExpenseType(Request $request, Business $business): array
    {
        $validator = Validator::make($request->all(), [
            'expense_date' => 'required|date',
            'expense_type_id' => 'required|uuid',
            'amount' => 'required|numeric|min:0.01|max:999999999.99',
            'transaction_type' => 'required|string|in:cash,card,bank,Cash,Card,Bank',
            'description' => 'nullable|string|max:2000',
            'in_store_payment' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return [
                'error' => response()->json([
                    'status' => false,
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors(),
                ], 422),
            ];
        }

        $expenseType = BusinessExpenseType::where('business_id', $business->id)
            ->where('expense_category', 'daily')
            ->where('is_deleted', false)
            ->find($request->input('expense_type_id'));

        if (!$expenseType) {
            return [
                'error' => response()->json([
                    'status' => false,
                    'message' => 'Selected daily expense type does not exist.',
                ], 422),
            ];
        }

        return ['expenseType' => $expenseType];
    }

    /**
     * Format a DailyExpense record consistently for JSON output.
     */
    private function formatExpense(DailyExpense $expense): array
    {
        return [
            'id' => $expense->id,
            'business_id' => $expense->business_id,
            'expense_type_id' => $expense->expense_type_id,
            'expense_type_name' => $expense->expenseType?->name ?? $expense->expense_type_name,
            'expense_date' => $expense->expense_date ? $expense->expense_date->format('Y-m-d') : null,
            'amount' => (float) $expense->amount,
            'transaction_type' => strtolower((string) $expense->transaction_type),
            'description' => $expense->description ?? '',
            'in_store_payment' => (bool) $expense->in_store_payment,
            'created_by' => $expense->created_by,
            'created_by_name' => $expense->created_by_name ?: 'Vendor',
            'created_at' => $expense->created_at?->toIso8601String(),
        ];
    }

    /**
     * List paginated daily expenses with date, expense type, and transaction type filters.
     */
    public function index(Request $request)
    {
        $business = $this->resolveActiveBusiness($request);
        if (!$business) {
            return response()->json(['status' => false, 'message' => 'Active business not found'], 404);
        }

        $perPage = max(1, min(100, (int) $request->input('per_page', 10)));
        $page = max(1, (int) $request->input('page', 1));

        $query = DailyExpense::where('business_id', $business->id)
            ->where('is_deleted', false)
            ->with('expenseType');

        if ($request->filled('date_from')) {
            $query->whereDate('expense_date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('expense_date', '<=', $request->input('date_to'));
        }

        if ($request->filled('expense_type_id') && $request->input('expense_type_id') !== 'all') {
            $query->where('expense_type_id', $request->input('expense_type_id'));
        }

        if ($request->filled('transaction_type') && strtolower((string) $request->input('transaction_type')) !== 'all') {
            $query->where('transaction_type', strtolower((string) $request->input('transaction_type')));
        }

        $totalExpenses = DailyExpense::where('business_id', $business->id)
            ->where('is_deleted', false)
            ->count();

        $filteredTotalAmount = (clone $query)->sum('amount');

        $paginator = $query->orderBy('expense_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        $formatted = collect($paginator->items())
            ->map(fn (DailyExpense $item) => $this->formatExpense($item))
            ->values();

        return response()->json([
            'status' => true,
            'message' => 'Daily expenses retrieved successfully',
            'data' => $formatted,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem() ?? 0,
                'to' => $paginator->lastItem() ?? 0,
            ],
            'metrics' => [
                'total_expenses' => (int) $totalExpenses,
                'showing' => $formatted->count(),
                'filtered_total_amount' => (float) $filteredTotalAmount,
            ],
            'currency' => $business->currency ?: 'GBP',
        ], 200);
    }

    /**
     * Create a new Daily Expense record.
     */
    public function store(Request $request)
    {
        $business = $this->resolveActiveBusiness($request);
        if (!$business) {
            return response()->json(['status' => false, 'message' => 'Active business not found'], 404);
        }

        $resolved = $this->validateAndResolveExpenseType($request, $business);
        if (isset($resolved['error'])) {
            return $resolved['error'];
        }

        /** @var BusinessExpenseType $expenseType */
        $expenseType = $resolved['expenseType'];
        $user = $request->user();
        $creatorName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))
            ?: ($business->name ?: ($user->email ?? 'Vendor'));

        $expense = DailyExpense::create([
            'business_id' => $business->id,
            'expense_type_id' => $expenseType->id,
            'expense_type_name' => $expenseType->name,
            'expense_date' => $request->input('expense_date'),
            'amount' => round((float) $request->input('amount'), 2),
            'transaction_type' => strtolower((string) $request->input('transaction_type')),
            'description' => trim((string) $request->input('description', '')) ?: null,
            'in_store_payment' => filter_var($request->input('in_store_payment', false), FILTER_VALIDATE_BOOLEAN),
            'created_by' => $user?->id,
            'created_by_name' => $creatorName,
            'is_deleted' => false,
        ]);

        $expense->setRelation('expenseType', $expenseType);

        return response()->json([
            'status' => true,
            'message' => 'Daily expense created successfully',
            'data' => $this->formatExpense($expense),
        ], 201);
    }

    /**
     * Update an existing Daily Expense record.
     */
    public function update(Request $request, $id)
    {
        $business = $this->resolveActiveBusiness($request);
        if (!$business) {
            return response()->json(['status' => false, 'message' => 'Active business not found'], 404);
        }

        $expense = DailyExpense::where('business_id', $business->id)
            ->where('is_deleted', false)
            ->find($id);

        if (!$expense) {
            return response()->json(['status' => false, 'message' => 'Daily expense not found'], 404);
        }

        $resolved = $this->validateAndResolveExpenseType($request, $business);
        if (isset($resolved['error'])) {
            return $resolved['error'];
        }

        /** @var BusinessExpenseType $expenseType */
        $expenseType = $resolved['expenseType'];

        $expense->update([
            'expense_type_id' => $expenseType->id,
            'expense_type_name' => $expenseType->name,
            'expense_date' => $request->input('expense_date'),
            'amount' => round((float) $request->input('amount'), 2),
            'transaction_type' => strtolower((string) $request->input('transaction_type')),
            'description' => trim((string) $request->input('description', '')) ?: null,
            'in_store_payment' => filter_var($request->input('in_store_payment', false), FILTER_VALIDATE_BOOLEAN),
        ]);

        $fresh = $expense->fresh('expenseType');

        return response()->json([
            'status' => true,
            'message' => 'Daily expense updated successfully',
            'data' => $this->formatExpense($fresh),
        ], 200);
    }

    /**
     * Soft-delete a Daily Expense record.
     */
    public function destroy(Request $request, $id)
    {
        $business = $this->resolveActiveBusiness($request);
        if (!$business) {
            return response()->json(['status' => false, 'message' => 'Active business not found'], 404);
        }

        $expense = DailyExpense::where('business_id', $business->id)
            ->where('is_deleted', false)
            ->find($id);

        if (!$expense) {
            return response()->json(['status' => false, 'message' => 'Daily expense not found'], 404);
        }

        $expense->is_deleted = true;
        $expense->save();

        return response()->json([
            'status' => true,
            'message' => 'Daily expense deleted successfully',
        ], 200);
    }
}
