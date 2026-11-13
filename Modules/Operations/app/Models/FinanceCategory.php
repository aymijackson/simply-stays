<?php

namespace Modules\Operations\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Traits\HasPublicId;
use Modules\Listings\Models\Property;

class FinanceCategory extends Model
{
    use HasPublicId;

    protected $table = 'ops_finance_categories';

    /** Seeded for every install; slugs let code find them (e.g. salaries, inventory purchases). */
    public const DEFAULTS = [
        'income' => [
            'room-revenue' => 'Room revenue',
            'food-beverage' => 'Food & beverage sales',
            'laundry-income' => 'Laundry & services',
            'events-income' => 'Events & hall hire',
            'other-income' => 'Other income',
        ],
        'expense' => [
            'salaries' => 'Salaries & wages',
            'inventory-purchases' => 'Inventory & supplies purchases',
            'utilities' => 'Electricity & water',
            'fuel' => 'Diesel & generator',
            'gas' => 'Cooking gas',
            'repairs' => 'Repairs & maintenance',
            'cleaning' => 'Cleaning & laundry',
            'food-kitchen' => 'Food & kitchen',
            'security' => 'Security',
            'internet-tv' => 'Internet & TV subscriptions',
            'marketing' => 'Marketing & commissions',
            'bank-charges' => 'Bank charges',
            'taxes-levies' => 'Taxes & levies',
            'other-expense' => 'Other expenses',
        ],
    ];

    protected $fillable = ['property_id', 'name', 'type', 'slug'];

    public static function ensureDefaults(): void
    {
        foreach (self::DEFAULTS as $type => $categories) {
            foreach ($categories as $slug => $name) {
                self::firstOrCreate(['property_id' => null, 'slug' => $slug], ['name' => $name, 'type' => $type]);
            }
        }
    }

    public static function bySlug(string $slug): self
    {
        self::ensureDefaults();

        return self::whereNull('property_id')->where('slug', $slug)->firstOrFail();
    }

    /** Shared defaults plus the property's own categories. */
    public function scopeAvailableTo(Builder $query, Property $property): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('property_id')->orWhere('property_id', $property->id));
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(FinanceTransaction::class, 'category_id');
    }

    public function isShared(): bool
    {
        return $this->property_id === null;
    }
}
