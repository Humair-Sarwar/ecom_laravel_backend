<?php

namespace App\Models;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Media;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class Product extends Model
{
    use HasUuids, HasApiTokens;

    protected $guarded = [];

    protected $casts = [
        'is_default' => 'integer',
        'is_published' => 'integer',
        'is_deleted' => 'boolean',
        'is_web' => 'integer',
        'is_pos' => 'integer',
        'stock_quantity' => 'integer',
        'is_serialized' => 'boolean',
    ];

    public function defaultVariation()
    {
        return $this->hasOne(Product::class, 'parent_product_id')
            ->where('is_default', 1)
            ->where('is_deleted', false);
    }
    
    public function media()
    {
        return $this->belongsToMany(
            Media::class,
            'product_images',
            'product_id',
            'media_id'
        );
    }

    public function variationImage(){
        return $this->hasOne(Media::class, 'id', 'image_id');
    }

    public function setProductSlugAttribute($value){
        $this->attributes['product_slug'] = Str::slug($value);
    }

    public function category(){
        return $this->belongsTo(Category::class);
    }

    public function brand(){
        return $this->belongsTo(Brand::class);
    }

    public function variations()
    {
        return $this->hasMany(Product::class, 'parent_product_id')
            ->where('is_deleted', false);
    }

    public function parentProduct()
    {
        return $this->belongsTo(Product::class, 'parent_product_id');
    }

    protected static function booted()
    {
        static::creating(function ($product) {
            if (empty($product->product_sku)) {
                $product->product_sku = static::generateUniqueSku();
            }
        });
    }

    protected static function generateUniqueSku()
    {
        do {
            $number = mt_rand(100000, 999999);
        } while (static::where('product_sku', $number)->exists());

        return $number;
    }
}
