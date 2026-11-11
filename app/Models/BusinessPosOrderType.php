<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessPosOrderType extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'business_pos_order_types';

    protected $fillable = [
        'business_id',
        'parent_id',
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

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')
            ->where('is_deleted', false)
            ->orderBy('sort_order')
            ->orderBy('created_at');
    }
}
