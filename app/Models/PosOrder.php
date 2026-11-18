<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PosOrder extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'pos_orders';

    protected $fillable = [
        'business_id',
        'order_no',
        'status',
        'transaction_type',
        'customer_id',
        'customer_name',
        'customer_phone',
        'order_type_id',
        'order_type_name',
        'order_process_id',
        'order_process_name',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'total_amount',
        'paid_amount',
        'balance_amount',
        'notes',
        'created_by',
        'created_by_name',
        'served_by',
        'served_by_name',
        'completed_at',
        'is_deleted',
    ];

    protected $casts = [
        'subtotal' => 'float',
        'discount_amount' => 'float',
        'tax_amount' => 'float',
        'total_amount' => 'float',
        'paid_amount' => 'float',
        'balance_amount' => 'float',
        'completed_at' => 'datetime',
        'is_deleted' => 'boolean',
    ];

    public function items()
    {
        return $this->hasMany(PosOrderItem::class, 'pos_order_id')->orderBy('created_at', 'asc');
    }
}
