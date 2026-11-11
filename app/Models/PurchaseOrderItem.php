<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'purchase_order_items';

    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'product_name',
        'sku',
        'barcode',
        'condition',
        'category_name',
        'brand_name',
        'warranty',
        'year',
        'unit_cost',
        'pack_price',
        'quantity',
        'received_quantity',
        'total_cost',
        'description',
        'imei_serial_no',
        'received_at',
        'item_status',
    ];

    protected $casts = [
        'unit_cost' => 'decimal:2',
        'pack_price' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'quantity' => 'integer',
        'received_quantity' => 'integer',
        'received_at' => 'datetime',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
