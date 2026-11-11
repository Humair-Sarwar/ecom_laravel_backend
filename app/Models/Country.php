<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    use HasFactory;

    protected $table = 'countries';

    protected $fillable = [
        'name',
        'nice_name',
        'iso3',
        'iso',
        'phone_code',
        'num_code',
        'currency',
        'time_zone',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function cities()
    {
        return $this->hasMany(City::class, 'country_id');
    }
}
