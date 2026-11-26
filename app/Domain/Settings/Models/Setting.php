<?php

namespace App\Domain\Settings\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['group', 'key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }
}
