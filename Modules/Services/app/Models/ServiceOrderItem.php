<?php

namespace Modules\Services\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Enums\Currency;
use Modules\Core\Support\Money;

class ServiceOrderItem extends Model
{
    protected $fillable = [
        'service_order_id',
        'menu_item_id',
        'name',
        'unit_price_amount',
        'quantity',
        'is_complimentary',
        'line_total_amount',
    ];

    protected function casts(): array
    {
        return [
            'is_complimentary' => 'boolean',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class, 'service_order_id');
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(ServiceOrderItemOption::class);
    }

    public function lineTotal(string $currency): Money
    {
        return Money::fromMinorUnits($this->line_total_amount, Currency::from($currency));
    }
}
