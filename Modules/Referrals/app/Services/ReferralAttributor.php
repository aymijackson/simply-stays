<?php

namespace Modules\Referrals\Services;

use App\Models\User;
use Modules\Bookings\Models\Booking;
use Modules\Referrals\Models\AgentProfile;
use Modules\Referrals\Models\Referral;

class ReferralAttributor
{
    /**
     * Attribute a newly created booking to a referring agent, if one applies.
     * Uses an explicit referral code from checkout, falling back to the guest's
     * signup-time referrer. Returns null when there's no applicable agent, or
     * when the "referral" would just be the agent booking their own listing.
     */
    public function attributeForBooking(Booking $booking, ?string $explicitCode): ?Referral
    {
        $agent = $this->resolveAgent($explicitCode, $booking->guest);

        if (! $agent || $agent->user_id === $booking->guest_id) {
            return null;
        }

        $commission = (int) round($booking->total_amount * ($agent->commission_rate / 100));

        $booking->update(['referral_code' => $agent->referral_code]);

        return Referral::create([
            'agent_id' => $agent->id,
            'referred_user_id' => $booking->guest_id,
            'referable_type' => Booking::class,
            'referable_id' => $booking->id,
            'status' => 'pending',
            'commission_amount' => $commission,
            'currency' => $booking->currency,
        ]);
    }

    private function resolveAgent(?string $explicitCode, User $guest): ?AgentProfile
    {
        if ($explicitCode) {
            return AgentProfile::where('referral_code', $explicitCode)->where('status', 'active')->first();
        }

        if ($guest->referred_by_agent_id) {
            return AgentProfile::where('id', $guest->referred_by_agent_id)->where('status', 'active')->first();
        }

        return null;
    }
}
