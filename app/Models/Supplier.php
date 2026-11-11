<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'suppliers';

    protected $fillable = [
        'business_id',
        'supplier_name',
        'full_name',
        'country_code',
        'mobile_no',
        'email',
        'description',
        'tax_definition',
        'is_default',
        'balance',
        'created_by',
        'created_by_name',
        'is_active',
        'is_deleted',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'is_deleted' => 'boolean',
        'balance' => 'decimal:2',
    ];

    public function business()
    {
        return $this->belongsTo(User::class, 'business_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
