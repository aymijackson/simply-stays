<?php

namespace Modules\Services\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Services\Models\ServiceOrder;
use Modules\Services\Notifications\ServiceOrderStatusChanged;

class ProviderOrderController extends Controller
{
    private const TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['in_progress', 'cancelled'],
        'in_progress' => ['completed', 'cancelled'],
    ];

    public function index(): View
    {
        $orders = ServiceOrder::with(['service.provider', 'customer'])
            ->when(! Auth::user()->hasRole('Admin'), fn ($query) => $query->whereHas('service', fn ($q) => $q->where('provider_id', Auth::id())))
            ->latest()
            ->get();

        return view('services::provider.orders', compact('orders'));
    }

    public function updateStatus(Request $request, ServiceOrder $order): RedirectResponse
    {
        abort_unless($order->service->provider_id === Auth::id() || Auth::user()->hasRole('Admin'), 403);

        $validated = $request->validate([
            'status' => ['required', 'in:confirmed,in_progress,completed,cancelled'],
        ]);

        $allowed = self::TRANSITIONS[$order->status] ?? [];

        abort_unless(in_array($validated['status'], $allowed, true), 422, "Cannot move an order from {$order->status} to {$validated['status']}.");

        $order->update(['status' => $validated['status']]);

        $order->customer->notify(new ServiceOrderStatusChanged($order));

        return back()->with('status', 'Order updated.');
    }
}
