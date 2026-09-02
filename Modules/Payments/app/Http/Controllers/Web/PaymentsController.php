<?php

namespace Modules\Payments\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Modules\Payments\Models\Wallet;

class PaymentsController extends Controller
{
    public function index(): View
    {
        $wallets = Wallet::where('holder_type', User::class)
            ->where('holder_id', Auth::id())
            ->with(['transactions' => fn ($query) => $query->latest()->take(10)])
            ->get();

        return view('payments::index', compact('wallets'));
    }
}
