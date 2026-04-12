<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class Term extends Model
{
    use HasUuids, HasApiTokens;

    protected $guarded = [];

    public function setTermSlugAttribute($value){
        $this->attributes['term_slug'] = Str::slug($value);
    }

    public function media(){
        return $this->belongsTo(Media::class, 'image_id');
    }
}
