<?php

namespace Modules\Services\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Enums\Currency;
use Modules\Core\Support\Money;

class ServiceOrderItemOption extends Model
{
    protected $fillable = [
        'service_order_item_id',
        'menu_item_option_id',
        'name',
        'price_delta_amount',
        'quantity',
        'line_total_amount',
    ];

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(ServiceOrderItem::class, 'service_order_item_id');
    }

    public function menuItemOption(): BelongsTo
    {
        return $this->belongsTo(MenuItemOption::class);
    }

    public function lineTotal(string $currency): Money
    {
        return Money::fromMinorUnits($this->line_total_amount, Currency::from($currency));
    }
}
