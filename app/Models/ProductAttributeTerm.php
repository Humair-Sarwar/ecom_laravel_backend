<?php

namespace App\Models;

use App\Models\Attribute;
use App\Models\Media;
use App\Models\Term;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\HasApiTokens;

class ProductAttributeTerm extends Model
{
    use HasUuids, HasApiTokens;
    protected $table = 'product_attributes_terms';

    protected $guarded = [];

    public function mediaTerms(){
        return $this->hasOne(Media::class, 'id', 'image_id');
    }

    public function attribute()
{
    return $this->belongsTo(Attribute::class);
}

public function term()
{
    return $this->belongsTo(Term::class);
}
}
