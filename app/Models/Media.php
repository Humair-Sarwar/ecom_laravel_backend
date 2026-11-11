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

    protected $appends = ['url'];

    public function getUrlAttribute(): ?string
    {
        if (!$this->media_path) {
            return null;
        }
        if (str_starts_with($this->media_path, 'http://') || str_starts_with($this->media_path, 'https://')) {
            return $this->media_path;
        }
        return asset('storage/' . ltrim($this->media_path, '/'));
    }
}
