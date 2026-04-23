<?php

namespace App\Models;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Media;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class Product extends Model
{
    use HasUuids, HasApiTokens;

    protected $guarded = [];
    
    public function media()
    {
        return $this->belongsToMany(
            Media::class,
            'product_images',
            'product_id',
            'media_id'
        );
    }

    public function category(){
        return $this->belongsTo(Category::class);
    }

    public function brand(){
        return $this->belongsTo(Brand::class);
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
