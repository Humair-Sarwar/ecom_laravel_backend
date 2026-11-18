<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ProductOptionValue extends Model
{
    use HasUuids;

    protected $table = 'product_option_values';

    protected $guarded = [];

    protected $casts = [
        'is_deleted' => 'boolean',
        'sort_order' => 'integer',
    ];
}
