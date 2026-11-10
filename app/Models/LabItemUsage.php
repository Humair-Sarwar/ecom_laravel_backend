<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LabItemUsage extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'lab_item_usages';

    protected $fillable = [
        'business_id',
        'lab_item_id',
        'order_id',
        'order_from',
        'quantity',
        'pack_price',
        'total_price',
        'created_by_name',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'pack_price' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    public function labItem()
    {
        return $this->belongsTo(LabItem::class, 'lab_item_id');
    }
}
