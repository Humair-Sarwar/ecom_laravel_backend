<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierLedger extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'supplier_ledgers';

    protected $fillable = [
        'business_id',
        'supplier_id',
        'reference_no',
        'transaction_type',
        'purchase_amount',
        'payment_amount',
        'balance',
        'payment_type',
        'transaction_date',
        'description',
        'use_pos_balance',
        'created_by',
        'created_by_name',
        'is_deleted',
    ];

    protected $casts = [
        'purchase_amount' => 'decimal:2',
        'payment_amount' => 'decimal:2',
        'balance' => 'decimal:2',
        'use_pos_balance' => 'boolean',
        'is_deleted' => 'boolean',
        'transaction_date' => 'date:Y-m-d',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function business()
    {
        return $this->belongsTo(User::class, 'business_id');
    }
}
