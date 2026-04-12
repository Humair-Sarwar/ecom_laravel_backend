<?php

namespace App\Models;

use App\Models\AttributeTermCategory;
use App\Models\Category;
use App\Models\Term;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class Attribute extends Model
{
    use HasUuids, HasApiTokens;

    protected $guarded = [];

    public function setAttributeSlugAttribute($value){
        $this->attributes['attribute_slug'] = Str::slug($value);
    }

    public function terms(){
        return $this->hasMany(Term::class);
    }

    public function attributeTermCategories(){
        return $this->hasMany(AttributeTermCategory::class, 'attribute_id');
        
    }

    public function categories()
    {
        return $this->belongsToMany(
            Category::class,
            'attribute_term_categories', // pivot table
            'attribute_id',              // foreign key of this model
            'category_id'                // foreign key of related model
        );
    }

    public function media(){
        return $this->belongsTo(Media::class, 'image_id');
    }
}
