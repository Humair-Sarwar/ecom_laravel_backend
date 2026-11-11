<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PurchaseReturnItem extends Model
{
    use HasUuids;

    protected $table = 'purchase_return_items';

    protected $fillable = [
        'purchase_return_id',
        'purchase_order_item_id',
        'product_id',
        'product_name',
        'sku',
        'barcode',
        'unit_cost',
        'quantity',
        'total_cost',
        'imei_serial_no',
    ];

    protected $casts = [
        'unit_cost' => 'decimal:2',
        'quantity' => 'integer',
        'total_cost' => 'decimal:2',
    ];

    public function purchaseReturn()
    {
        return $this->belongsTo(PurchaseReturn::class, 'purchase_return_id');
    }

    public function purchaseOrderItem()
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id');
    }
}
