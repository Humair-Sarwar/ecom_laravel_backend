<?php

namespace App\Models;

use App\Models\Media;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class Brand extends Model
{
    use HasFactory, HasUuids, HasApiTokens;

    protected $guarded = [];

    public function setSlugAttribute($value){
        $this->attributes['slug'] = Str::slug($value);
    }

    public function media(){
        return $this->belongsTo(Media::class, 'image_id');
    }
}
