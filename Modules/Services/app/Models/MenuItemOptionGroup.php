<?php

namespace Modules\Services\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Traits\HasPublicId;

class MenuItemOptionGroup extends Model
{
    use HasPublicId;

    protected $fillable = [
        'menu_item_id',
        'name',
        'selection_type',
        'is_required',
        'min_select',
        'max_select',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
        ];
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(MenuItemOption::class);
    }

    public function isSingle(): bool
    {
        return $this->selection_type === 'single';
    }
}
