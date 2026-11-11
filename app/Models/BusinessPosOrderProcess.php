<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessPosOrderProcess extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'business_pos_order_processes';

    protected $fillable = [
        'business_id',
        'name',
        'sort_order',
        'is_default',
        'created_by',
        'created_by_name',
        'is_deleted',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_default' => 'boolean',
        'is_deleted' => 'boolean',
    ];
}
