<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PurchaseReturn extends Model
{
    use HasUuids;

    protected $table = 'purchase_returns';

    protected $fillable = [
        'business_id',
        'supplier_id',
        'purchase_order_id',
        'purchase_order_no',
        'return_no',
        'return_date',
        'total_items',
        'total_amount',
        'return_amount',
        'remaining_amount',
        'cash_paid',
        'card_paid',
        'bank_paid',
        'notes',
        'created_by',
        'created_by_name',
        'is_deleted',
    ];

    protected $casts = [
        'return_date' => 'date',
        'total_items' => 'integer',
        'total_amount' => 'decimal:2',
        'return_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'cash_paid' => 'decimal:2',
        'card_paid' => 'decimal:2',
        'bank_paid' => 'decimal:2',
        'is_deleted' => 'boolean',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function items()
    {
        return $this->hasMany(PurchaseReturnItem::class, 'purchase_return_id');
    }
}
