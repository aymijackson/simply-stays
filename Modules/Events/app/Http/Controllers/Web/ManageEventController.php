<?php

namespace Modules\Events\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Enums\Currency;
use Modules\Core\Support\Money;
use Modules\Events\Http\Requests\EventRequest;
use Modules\Events\Models\Event;
use Modules\Events\Models\EventSetting;
use Modules\Events\Support\EventOccurrenceGenerator;
use Modules\Listings\Models\Property;

class ManageEventController extends Controller
{
    public function index(): View
    {
        $events = Event::with('occurrences')
            ->where('created_by', Auth::id())
            ->latest()
            ->get();

        return view('events::manage.index', compact('events'));
    }

    public function create(): View
    {
        $this->authorizeCanCreate();

        $properties = Property::where('host_id', Auth::id())->orderBy('name')->get();

        return view('events::manage.create', compact('properties'));
    }

    public function store(EventRequest $request): RedirectResponse
    {
        $this->authorizeCanCreate();

        $event = Event::create($this->payload($request, recalculateStatus: true));

        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                $event->addMedia($photo)->toMediaCollection('photos');
            }
        }

        EventOccurrenceGenerator::generate($event);

        return redirect()->route('events.manage.index')->with('status', $event->status === 'published'
            ? 'Event published.'
            : 'Event submitted for admin approval.');
    }

    public function edit(Event $event): View
    {
        $this->authorizeOwner($event);

        $properties = Property::where('host_id', Auth::id())->orderBy('name')->get();

        return view('events::manage.edit', compact('event', 'properties'));
    }

    public function update(EventRequest $request, Event $event): RedirectResponse
    {
        $this->authorizeOwner($event);

        $recurrenceChanged = $event->canEditRecurrence() && (
            $event->recurrence_type !== $request->input('recurrence_type')
            || $event->starts_on->toDateString() !== $request->input('starts_on')
            || $event->ends_on?->toDateString() !== $request->input('ends_on')
            || $event->recurrence_days_of_week !== $request->input('recurrence_days_of_week')
        );

        $payload = $this->payload($request, recalculateStatus: $event->status === 'rejected');

        if (! $event->canEditRecurrence()) {
            unset($payload['recurrence_type'], $payload['recurrence_days_of_week'], $payload['starts_on'], $payload['ends_on'], $payload['start_time'], $payload['end_time']);
        }

        $event->update($payload);

        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                $event->addMedia($photo)->toMediaCollection('photos');
            }
        }

        if ($recurrenceChanged) {
            EventOccurrenceGenerator::generate($event->fresh());
        }

        return redirect()->route('events.manage.edit', $event)->with('status', 'Event updated.');
    }

    public function cancel(Event $event): RedirectResponse
    {
        $this->authorizeOwner($event);

        $event->update(['status' => 'cancelled']);
        $event->occurrences()->update(['status' => 'cancelled']);

        return redirect()->route('events.manage.index')->with('status', 'Event cancelled.');
    }

    private function payload(EventRequest $request, bool $recalculateStatus): array
    {
        $validated = $request->validated();
        $currency = Currency::from($validated['currency']);
        $isTicketed = $request->boolean('is_ticketed');
        $user = Auth::user();

        $payload = [
            'created_by' => Auth::id(),
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'property_id' => $validated['property_id'] ?? null,
            'location_address' => $validated['location_address'] ?? null,
            'category' => $validated['category'] ?? null,
            'is_ticketed' => $isTicketed,
            'capacity' => $validated['capacity'] ?? null,
            'price_amount' => $isTicketed && isset($validated['price'])
                ? Money::fromDecimal((float) $validated['price'], $currency)->minorUnits
                : 0,
            'currency' => $validated['currency'],
            'recurrence_type' => $validated['recurrence_type'],
            'recurrence_days_of_week' => isset($validated['recurrence_days_of_week'])
                ? array_map('intval', $validated['recurrence_days_of_week'])
                : null,
            'starts_on' => $validated['starts_on'],
            'ends_on' => $validated['ends_on'] ?? null,
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'] ?? null,
        ];

        if ($user->hasRole('Admin')) {
            $payload['status'] = 'published';
            $payload['approved_by'] = Auth::id();
            $payload['approved_at'] = now();
            $payload['commission_type'] = $validated['commission_type'] ?? 'none';
            $payload['commission_value'] = $validated['commission_value'] ?? null;
        } elseif ($recalculateStatus) {
            $payload['status'] = 'pending_approval';
            $payload['approved_by'] = null;
            $payload['approved_at'] = null;
            $payload['rejection_reason'] = null;
        }

        return $payload;
    }

    private function authorizeCanCreate(): void
    {
        $user = Auth::user();
        $settings = EventSetting::current();

        abort_unless(
            $user->hasRole('Admin')
                || ($user->hasRole('Host') && $settings->allowsRole('Host'))
                || ($user->hasRole('ServiceProvider') && $settings->allowsRole('ServiceProvider')),
            403,
            'Event creation is not currently available for your account.'
        );
    }

    private function authorizeOwner(Event $event): void
    {
        abort_unless(
            $event->created_by === Auth::id() || Auth::user()->hasRole('Admin'),
            403
        );
    }
}
