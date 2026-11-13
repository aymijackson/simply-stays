<?php

namespace Modules\Services\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceCategory extends Model
{
    /** URLs use the slug, never the database id. */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected $fillable = ['name', 'slug', 'fulfillment_type', 'description'];

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }
}
