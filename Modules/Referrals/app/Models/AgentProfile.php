<?php

namespace Modules\Referrals\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AgentProfile extends Model
{
    protected $fillable = ['user_id', 'referral_code', 'commission_rate', 'status'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class, 'agent_id');
    }

    public static function findOrProvisionFor(User $user): self
    {
        return static::firstOrCreate(
            ['user_id' => $user->id],
            [
                'referral_code' => static::generateUniqueCode($user->name),
                'commission_rate' => config('referrals.default_commission_rate', 5.00),
                'status' => 'active',
            ],
        );
    }

    public static function generateUniqueCode(string $name): string
    {
        $base = Str::upper(Str::slug(Str::before($name, ' '), ''));
        $base = $base !== '' ? $base : 'AGENT';

        do {
            $code = $base.random_int(100, 999);
        } while (static::where('referral_code', $code)->exists());

        return $code;
    }
}
