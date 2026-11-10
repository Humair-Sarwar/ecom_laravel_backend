<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LabItem extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'lab_items';

    protected $fillable = [
        'business_id',
        'name',
        'barcode',
        'condition',
        'quality',
        'category_id',
        'brand_id',
        'unit_of_measure',
        'min_stock_alert',
        'sort_order',
        'stock',
        'reserved_stock',
        'last_cost',
        'selling_price',
        'description',
        'is_active',
        'is_deleted',
    ];

    protected $casts = [
        'stock' => 'integer',
        'reserved_stock' => 'integer',
        'min_stock_alert' => 'integer',
        'sort_order' => 'integer',
        'last_cost' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'is_active' => 'boolean',
        'is_deleted' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id')->where('is_deleted', false);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function usages()
    {
        return $this->hasMany(LabItemUsage::class, 'lab_item_id');
    }

    public function getAvailableStockAttribute()
    {
        return max(0, $this->stock - $this->reserved_stock);
    }
}
