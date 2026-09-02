<?php

namespace Modules\Services\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuItemPairing extends Model
{
    protected $table = 'menu_item_pairings';

    protected $fillable = [
        'menu_item_id',
        'paired_menu_item_id',
        'type',
    ];

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function pairedMenuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'paired_menu_item_id');
    }
}
