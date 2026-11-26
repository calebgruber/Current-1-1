<?php

namespace App\Domain\Authentication\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    protected $fillable = ['key', 'module', 'resource', 'action', 'label', 'description'];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }
}
