<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Business extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'businesses';

    protected $fillable = [
        'owner_id',
        'business_group_id',
        'role_id',
        'role_title',
        'name',
        'branch_name',
        'business_type',
        'email',
        'mobile_no',
        'whatsapp_no',
        'is_system_website',
        'is_personal_website',
        'website_url',
        'package_level',
        'services',
        'schema_type',
        'schema_name',
        'address',
        'city_id',
        'city_name',
        'state_region',
        'post_code',
        'country_id',
        'country_name',
        'currency',
        'latitude',
        'longitude',
        'description',
        'status',
    ];

    protected $casts = [
        'is_system_website' => 'boolean',
        'is_personal_website' => 'boolean',
        'services' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Business group relation
     */
    public function group()
    {
        return $this->belongsTo(BusinessGroup::class, 'business_group_id');
    }

    /**
     * Business owner user relation
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Assigned role relation
     */
    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /**
     * Country relation
     */
    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    /**
     * City relation
     */
    public function city()
    {
        return $this->belongsTo(City::class, 'city_id');
    }
}
