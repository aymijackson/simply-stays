<?php

namespace Modules\Referrals\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Modules\Referrals\Models\AgentProfile;
use Modules\Referrals\Models\Referral;

class ReferralsController extends Controller
{
    public function index(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $agent = AgentProfile::findOrProvisionFor($user);

        $referrals = $agent->referrals()->with('referredUser')->latest()->get()->map(fn (Referral $referral) => [
            'id' => $referral->id,
            'referred_user' => $referral->referredUser?->name,
            'status' => $referral->status,
            'commission_amount' => $referral->commission_amount,
            'currency' => $referral->currency,
        ]);

        return response()->json([
            'referral_code' => $agent->referral_code,
            'commission_rate' => $agent->commission_rate,
            'referrals' => $referrals,
        ]);
    }
}
