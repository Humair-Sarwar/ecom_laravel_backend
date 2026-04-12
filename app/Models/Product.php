<?php

namespace App\Models;

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
}
