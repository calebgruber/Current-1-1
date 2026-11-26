<?php

namespace App\Domain\Search\Models;

use Illuminate\Database\Eloquent\Model;

class SearchEntry extends Model
{
    protected $table = 'search_index';

    protected $fillable = ['entity_type', 'entity_id', 'title', 'subtitle', 'keywords', 'codes', 'url'];
}
