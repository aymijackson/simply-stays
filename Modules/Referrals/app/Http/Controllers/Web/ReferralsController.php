<?php

namespace Modules\Referrals\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Modules\Payments\Models\Wallet;
use Modules\Referrals\Models\AgentProfile;

class ReferralsController extends Controller
{
    public function index(): View
    {
        /** @var User $user */
        $user = Auth::user();

        $agent = AgentProfile::findOrProvisionFor($user);

        $referrals = $agent->referrals()->with(['referredUser', 'referable'])->latest()->get();

        $wallets = Wallet::where('holder_type', User::class)
            ->where('holder_id', $user->id)
            ->get();

        return view('referrals::index', compact('agent', 'referrals', 'wallets'));
    }
}
