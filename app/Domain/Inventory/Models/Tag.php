<?php

namespace App\Domain\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Tag extends Model
{
    protected $fillable = ['name', 'slug', 'color'];

    public function products(): MorphToMany
    {
        return $this->morphedByMany(Product::class, 'taggable');
    }
}
