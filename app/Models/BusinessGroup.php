<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BusinessGroup extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'business_groups';

    protected $fillable = [
        'name',
        'owner_id',
        'status',
        'description',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Group owner user relation
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Businesses in this group
     */
    public function businesses()
    {
        return $this->hasMany(Business::class, 'business_group_id');
    }
}
