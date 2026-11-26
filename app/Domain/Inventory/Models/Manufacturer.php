<?php

namespace App\Domain\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Manufacturer extends Model
{
    protected $fillable = ['name', 'website'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
