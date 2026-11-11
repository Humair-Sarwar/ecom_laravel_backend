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
        'mobile_country_code',
        'mobile_no',
        'whatsapp_country_code',
        'whatsapp_no',
        'is_system_website',
        'is_personal_website',
        'website_url',
        'logo_image',
        'cover_image',
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
        'tax_title',
        'tax_value',
        'vat_registration_no',
        'enable_vat_tax',
        'disallow_pos_duplicate_cart',
        'daily_stock_movement_report',
        'smtp_active',
        'smtp_port',
        'smtp_host',
        'smtp_email',
        'smtp_password',
        'stock_alert_after_sale',
        'stock_alert_eod_list',
        'latitude',
        'longitude',
        'delivery_days',
        'delivery_hours',
        'delivery_minutes',
        'max_shipping_amount',
        'shipping_charges',
        'description',
        'status',
    ];

    protected $casts = [
        'is_system_website' => 'boolean',
        'is_personal_website' => 'boolean',
        'enable_vat_tax' => 'boolean',
        'disallow_pos_duplicate_cart' => 'boolean',
        'daily_stock_movement_report' => 'boolean',
        'smtp_active' => 'boolean',
        'stock_alert_after_sale' => 'boolean',
        'stock_alert_eod_list' => 'boolean',
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
