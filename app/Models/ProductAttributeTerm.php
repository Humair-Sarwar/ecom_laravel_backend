<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class ProductAttributeTerm extends Model
{
    use HasUuids, HasApiTokens;
    protected $table = 'product_attributes_terms';

    protected $guarded = [];
}
