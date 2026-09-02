<?php

namespace Modules\Listings\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Amenity extends Model
{
    protected $fillable = ['name', 'slug'];

    public function listings(): BelongsToMany
    {
        return $this->belongsToMany(Listing::class, 'amenity_listing');
    }
}
