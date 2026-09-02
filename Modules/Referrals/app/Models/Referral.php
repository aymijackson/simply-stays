<?php

namespace Modules\Referrals\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Core\Enums\Currency;
use Modules\Core\Support\Money;

class Referral extends Model
{
    protected $fillable = [
        'agent_id',
        'referred_user_id',
        'referable_type',
        'referable_id',
        'status',
        'commission_amount',
        'currency',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(AgentProfile::class, 'agent_id');
    }

    public function referredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }

    public function referable(): MorphTo
    {
        return $this->morphTo();
    }

    public function total(): Money
    {
        return Money::fromMinorUnits($this->commission_amount, Currency::from($this->currency));
    }
}
