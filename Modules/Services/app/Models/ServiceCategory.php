<?php

namespace Modules\Services\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceCategory extends Model
{
    protected $fillable = ['name', 'slug', 'fulfillment_type', 'description'];

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }
}
