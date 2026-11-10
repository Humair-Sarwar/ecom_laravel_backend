<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'purchase_orders';

    protected $fillable = [
        'business_id',
        'supplier_id',
        'order_no',
        'order_date',
        'status',
        'fulfillment_status',
        'is_trade_in',
        'grand_total',
        'paid_amount',
        'balance',
        'cash_paid',
        'card_paid',
        'bank_paid',
        'use_today_sales',
        'notes',
        'created_by',
        'created_by_name',
        'is_deleted',
    ];

    protected $casts = [
        'is_trade_in' => 'boolean',
        'use_today_sales' => 'boolean',
        'is_deleted' => 'boolean',
        'grand_total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'balance' => 'decimal:2',
        'cash_paid' => 'decimal:2',
        'card_paid' => 'decimal:2',
        'bank_paid' => 'decimal:2',
        'order_date' => 'date',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class, 'purchase_order_id');
    }
}
