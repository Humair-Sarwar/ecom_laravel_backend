<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PosOrderItem extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'pos_order_items';

    protected $fillable = [
        'pos_order_id',
        'product_id',
        'variation_id',
        'transaction_type',
        'product_name',
        'sku',
        'vat_status',
        'qty',
        'unit_price',
        'discount_amount',
        'discount_type',
        'line_total',
        'imei_serial_no',
        'notes',
    ];

    protected $casts = [
        'qty' => 'integer',
        'unit_price' => 'float',
        'discount_amount' => 'float',
        'line_total' => 'float',
    ];

    public function order()
    {
        return $this->belongsTo(PosOrder::class, 'pos_order_id');
    }
}
