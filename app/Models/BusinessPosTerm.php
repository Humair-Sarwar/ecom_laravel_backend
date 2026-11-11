<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessPosTerm extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'business_pos_terms';

    protected $fillable = [
        'business_id',
        'section',
        'title',
        'note',
        'is_default',
        'created_by',
        'created_by_name',
        'is_deleted',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_deleted' => 'boolean',
    ];
}
