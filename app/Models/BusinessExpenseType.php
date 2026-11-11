<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessExpenseType extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'business_expense_types';

    protected $fillable = [
        'business_id',
        'expense_category',
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
