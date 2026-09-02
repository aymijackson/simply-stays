<?php

namespace Modules\Auth\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Modules\Referrals\Models\AgentProfile;

class RegisterController extends Controller
{
    public function create(Request $request): View
    {
        if ($request->filled('ref')) {
            $request->session()->put('referral_code', $request->string('ref'));
        }

        return view('auth::register', [
            'referralCode' => $request->session()->get('referral_code'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'account_type' => ['required', 'in:guest,host'],
            'referral_code' => ['nullable', 'string', 'exists:agent_profiles,referral_code'],
        ]);

        $agent = ! empty($validated['referral_code'])
            ? AgentProfile::where('referral_code', $validated['referral_code'])->where('status', 'active')->first()
            : null;

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => $validated['password'],
            'referred_by_agent_id' => $agent?->id,
        ]);

        $user->assignRole($validated['account_type'] === 'host' ? 'Host' : 'Guest');

        $request->session()->forget('referral_code');

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
