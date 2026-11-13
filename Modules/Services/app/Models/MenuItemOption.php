<?php

namespace Modules\Services\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Enums\Currency;
use Modules\Core\Support\Money;
use Modules\Core\Traits\HasPublicId;

class MenuItemOption extends Model
{
    use HasPublicId;

    protected $fillable = [
        'menu_item_option_group_id',
        'name',
        'price_delta_amount',
        'max_quantity',
        'is_default',
        'is_available',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_available' => 'boolean',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(MenuItemOptionGroup::class, 'menu_item_option_group_id');
    }

    public function priceDelta(string $currency): Money
    {
        return Money::fromMinorUnits($this->price_delta_amount, Currency::from($currency));
    }
}
