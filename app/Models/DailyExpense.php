<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyExpense extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'daily_expenses';

    protected $fillable = [
        'business_id',
        'expense_type_id',
        'expense_type_name',
        'expense_date',
        'amount',
        'transaction_type',
        'description',
        'in_store_payment',
        'created_by',
        'created_by_name',
        'is_deleted',
    ];

    protected $casts = [
        'expense_date' => 'date:Y-m-d',
        'amount' => 'decimal:2',
        'in_store_payment' => 'boolean',
        'is_deleted' => 'boolean',
    ];

    public function expenseType()
    {
        return $this->belongsTo(BusinessExpenseType::class, 'expense_type_id');
    }
}
