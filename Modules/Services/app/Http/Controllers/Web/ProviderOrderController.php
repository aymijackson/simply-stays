<?php

namespace Modules\Services\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
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

    public const OPEN = ['pending', 'confirmed', 'in_progress'];

    public function index(Request $request): View
    {
        $view = $request->input('view', 'open');

        $orders = $this->visible()
            ->with(['service.provider.roles', 'customer', 'items.options', 'location'])
            ->when($view === 'open', fn ($q) => $q->whereIn('status', self::OPEN))
            ->when($view === 'done', fn ($q) => $q->whereNotIn('status', self::OPEN))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        // Opening the board counts as seeing these orders; they keep a "New" tag for this visit.
        $newIds = $orders->getCollection()->whereNull('seen_at')->pluck('id');
        if ($newIds->isNotEmpty()) {
            ServiceOrder::whereIn('id', $newIds)->update(['seen_at' => now()]);
        }

        return view('services::provider.orders', [
            'orders' => $orders,
            'newIds' => $newIds,
            'view' => $view,
            'openCount' => $this->visible()->whereIn('status', self::OPEN)->count(),
            'isAdmin' => Auth::user()->hasRole('Admin'),
        ]);
    }

    /**
     * Polled every few seconds by the portal so staff hear about new orders
     * straight away. Returns orders newer than ?after=<id>.
     */
    public function feed(Request $request): JsonResponse
    {
        $after = $request->integer('after');

        $new = $after > 0
            ? $this->visible()->with(['service'])->where('id', '>', $after)->orderBy('id')->limit(20)->get()
            : collect();

        return response()->json([
            'latest_id' => (int) $this->visible()->max('id'),
            'unseen' => $this->visible()->whereNull('seen_at')->whereIn('status', self::OPEN)->count(),
            'orders' => $new->map(fn (ServiceOrder $o) => [
                'id' => $o->id,
                'title' => $o->service->title,
                'who' => $o->contactName(),
                'where' => $o->deliverTo(),
                'total' => $o->total()->format(),
                'url' => route('provider.services.orders').'#order-'.$o->id,
            ])->values(),
        ]);
    }

    public function markSeen(Request $request): RedirectResponse|JsonResponse
    {
        $this->visible()->whereNull('seen_at')->update(['seen_at' => now()]);

        return $request->expectsJson() ? response()->json(['ok' => true]) : back();
    }

    public function updateStatus(Request $request, ServiceOrder $order): RedirectResponse
    {
        abort_unless($order->service->provider_id === Auth::id() || Auth::user()->hasRole('Admin'), 403);

        $validated = $request->validate([
            'status' => ['required', 'in:confirmed,in_progress,completed,cancelled'],
        ]);

        $allowed = self::TRANSITIONS[$order->status] ?? [];

        abort_unless(in_array($validated['status'], $allowed, true), 422, "Cannot move an order from {$order->status} to {$validated['status']}.");

        $order->update(['status' => $validated['status'], 'seen_at' => $order->seen_at ?? now()]);

        $order->notifyCustomer(new ServiceOrderStatusChanged($order));

        return back()->with('status', 'Order updated.');
    }

    /** Admins see every order; vendors see orders for their own services. */
    private function visible(): Builder
    {
        return ServiceOrder::query()
            ->when(! Auth::user()->hasRole('Admin'), fn ($q) => $q->whereHas('service', fn ($s) => $s->where('provider_id', Auth::id())));
    }
}
