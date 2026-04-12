<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class Media extends Model
{
    use HasUuids, HasApiTokens;
    protected $table = 'medias';
    protected $guarded = [];
}
