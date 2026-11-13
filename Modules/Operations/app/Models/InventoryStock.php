<?php

namespace Modules\Operations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryStock extends Model
{
    protected $table = 'ops_inventory_stocks';

    protected $fillable = ['item_id', 'location_id', 'quantity', 'par_level'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'par_level' => 'integer',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }

    /** How many are missing against the par level (0 when at or above par, or no par set). */
    public function shortfall(): int
    {
        return $this->par_level === null ? 0 : max(0, $this->par_level - $this->quantity);
    }
}
