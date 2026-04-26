<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class Policy extends Model
{
    use HasUuids, HasApiTokens;

    protected $guarded = [];
    
}
