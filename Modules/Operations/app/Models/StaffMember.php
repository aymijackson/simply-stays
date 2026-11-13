<?php

namespace Modules\Operations\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Enums\Currency;
use Modules\Core\Support\Money;
use Modules\Core\Traits\HasPublicId;
use Modules\Listings\Models\Property;

class StaffMember extends Model
{
    use HasPublicId;

    protected $table = 'ops_staff_members';

    public const ACCESS_LEVELS = [
        'basic' => 'Basic — own shifts only',
        'inventory' => 'Store keeper — shifts + inventory',
        'manager' => 'Manager — full operations access',
    ];

    public const DEPARTMENTS = [
        'Front desk', 'Housekeeping', 'Kitchen', 'Laundry', 'Store', 'Security', 'Maintenance', 'Management', 'Other',
    ];

    public const EMPLOYMENT_TYPES = [
        'full_time' => 'Full time',
        'part_time' => 'Part time',
        'contract' => 'Contract',
        'casual' => 'Casual',
    ];

    protected $fillable = [
        'property_id', 'user_id', 'name', 'phone', 'email', 'position', 'department', 'access_level',
        'employment_type', 'hired_on', 'salary_amount', 'currency', 'emergency_contact', 'address', 'notes', 'status',
    ];

    protected function casts(): array
    {
        return [
            'hired_on' => 'date',
            'salary_amount' => 'integer',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(FinanceTransaction::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function salary(): ?Money
    {
        return $this->salary_amount === null ? null : Money::fromMinorUnits($this->salary_amount, Currency::from($this->currency));
    }

    public function accessLabel(): string
    {
        return explode(' — ', self::ACCESS_LEVELS[$this->access_level] ?? $this->access_level)[0];
    }
}
