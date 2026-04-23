<?php

namespace App\Models;

use App\Models\Attribute;
use App\Models\Category;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class Category extends Model
{
    use HasUuids, HasApiTokens;

    protected $guarded = [];

    public function children(){
        return $this->hasMany(Category::class, 'parent_category_id')->with(['children', 'media', 'coverMedia']);
    }

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_category_id');
    }

    public function media(){
        return $this->belongsTo(Media::class, 'image_id');
    }
    public function coverMedia()
{
    return $this->belongsTo(Media::class, 'cover_image_id', 'id');
}

public function attributes()
    {
        return $this->belongsToMany(
            Attribute::class,
            'attribute_term_categories', // pivot table
            'category_id',               // foreign key of related model
            'attribute_id',              // foreign key of this model
        );
    }

//     public function childrenRecursive()
// {
//     return $this->hasMany(Category::class, 'parent_category_id')
//                 ->with('childrenRecursive');
// }
}
