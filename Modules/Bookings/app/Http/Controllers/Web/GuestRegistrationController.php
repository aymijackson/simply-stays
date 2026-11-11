<?php

namespace Modules\Bookings\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Admin\Models\SiteSetting;
use Modules\Bookings\Models\Booking;
use Modules\Bookings\Models\GuestRegistration;
use Modules\Bookings\Support\GuestRegistrationForm;
use Modules\Bookings\Support\GuestRegistrationPrefill;
use Modules\Listings\Models\Property;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GuestRegistrationController extends Controller
{
    /** A drawn signature is a small PNG; anything much larger is not a signature. */
    private const SIGNATURE_MAX_LENGTH = 400_000;

    public function index(Request $request): View
    {
        $user = Auth::user();

        $query = $this->visibleTo($user)
            ->with(['property', 'booking'])
            ->withCount('media')
            ->latest();

        if ($request->filled('property')) {
            $query->where('property_id', $request->integer('property'));
        }

        if ($request->filled('q')) {
            $query->where('guest_name', 'like', '%'.$request->string('q').'%');
        }

        $registrations = $query->paginate(20)->withQueryString();
        $properties = $this->staffProperties($user);

        return view('bookings::guest-forms.index', compact('registrations', 'properties'));
    }

    public function create(Request $request): View
    {
        $user = Auth::user();
        $booking = null;
        $property = null;

        if ($request->filled('booking')) {
            $booking = Booking::with(['listing.property', 'guest', 'transactions'])->findOrFail($request->integer('booking'));
            $property = $booking->listing->property;
            abort_unless($booking->guest_id === $user->id || $this->isStaffFor($user, $property), 403);
        } elseif ($request->filled('property')) {
            $property = Property::findOrFail($request->integer('property'));
        }

        $asStaff = $property ? $this->isStaffFor($user, $property) : false;
        $prefillGuest = $booking?->guest ?? ($asStaff ? null : $user);

        return view('bookings::guest-forms.create', [
            'property' => $property,
            'booking' => $booking,
            'asStaff' => $asStaff,
            'propertyOptions' => $this->selectableProperties($user),
            'bookingOptions' => $this->selectableBookings($user, $property),
            'data' => $property ? GuestRegistrationPrefill::build($booking, $prefillGuest) : [],
            'settings' => SiteSetting::current(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $request->validate(['property_id' => ['required', 'integer', Rule::exists('properties', 'id')->whereNull('deleted_at')]]);
        $property = Property::findOrFail($request->integer('property_id'));
        $asStaff = $this->isStaffFor($user, $property);

        $validated = $request->validate($this->rules($asStaff, signatureRequired: ! $asStaff));

        $booking = null;
        if (! empty($validated['booking_id'])) {
            $booking = Booking::with('listing')->findOrFail($validated['booking_id']);
            abort_unless($booking->listing->property_id === $property->id, 422, 'That booking is not for the selected property.');
            abort_unless($booking->guest_id === $user->id || $asStaff, 403);
        }

        $registration = new GuestRegistration([
            'property_id' => $property->id,
            'booking_id' => $booking?->id,
            'guest_id' => $booking?->guest_id ?? ($asStaff ? null : $user->id),
            'created_by' => $user->id,
        ]);

        $this->fill($registration, $validated, $asStaff);
        $registration->save();

        return redirect()->route('guest-forms.show', $registration)->with('status', 'Registration form saved.');
    }

    public function show(GuestRegistration $guestRegistration): View
    {
        $user = Auth::user();
        abort_unless($guestRegistration->canView($user), 403);

        $guestRegistration->load(['property.host', 'booking', 'creator']);

        return view('bookings::guest-forms.show', [
            'registration' => $guestRegistration,
            'asStaff' => $guestRegistration->isStaff($user),
            'scans' => $guestRegistration->getMedia('scans'),
            'settings' => SiteSetting::current(),
        ]);
    }

    public function edit(GuestRegistration $guestRegistration): View
    {
        $user = Auth::user();
        abort_unless($guestRegistration->canView($user), 403);

        return view('bookings::guest-forms.edit', [
            'registration' => $guestRegistration,
            'property' => $guestRegistration->property,
            'booking' => $guestRegistration->booking,
            'asStaff' => $guestRegistration->isStaff($user),
            'data' => $guestRegistration->data ?? [],
            'settings' => SiteSetting::current(),
        ]);
    }

    public function update(Request $request, GuestRegistration $guestRegistration): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($guestRegistration->canView($user), 403);
        $asStaff = $guestRegistration->isStaff($user);

        $validated = $request->validate($this->rules($asStaff, signatureRequired: false, withBooking: false));

        $this->fill($guestRegistration, $validated, $asStaff);
        $guestRegistration->save();

        return redirect()->route('guest-forms.show', $guestRegistration)->with('status', 'Registration form updated.');
    }

    public function destroy(GuestRegistration $guestRegistration): RedirectResponse
    {
        abort_unless($guestRegistration->isStaff(Auth::user()), 403);

        $guestRegistration->delete();

        return redirect()->route('guest-forms.index')->with('status', 'Registration form deleted.');
    }

    public function storeScans(Request $request, GuestRegistration $guestRegistration): RedirectResponse
    {
        abort_unless($guestRegistration->isStaff(Auth::user()), 403);

        $request->validate([
            'scans' => ['required', 'array', 'max:10'],
            'scans.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ]);

        foreach ($request->file('scans') as $file) {
            $guestRegistration->addMedia($file)
                ->withCustomProperties(['uploaded_by' => Auth::id()])
                ->toMediaCollection('scans');
        }

        return redirect()->route('guest-forms.show', $guestRegistration)->with('status', 'Hand-filled form uploaded.');
    }

    public function showScan(GuestRegistration $guestRegistration, Media $media): StreamedResponse
    {
        abort_unless($guestRegistration->canView(Auth::user()), 403);
        $this->assertScanBelongsTo($media, $guestRegistration);

        return $media->toInlineResponse(request());
    }

    public function destroyScan(GuestRegistration $guestRegistration, Media $media): RedirectResponse
    {
        abort_unless($guestRegistration->isStaff(Auth::user()), 403);
        $this->assertScanBelongsTo($media, $guestRegistration);

        $media->delete();

        return redirect()->route('guest-forms.show', $guestRegistration)->with('status', 'Upload removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(bool $asStaff, bool $signatureRequired, bool $withBooking = true): array
    {
        $rules = GuestRegistrationForm::rules($asStaff) + [
            'signature' => [$signatureRequired ? 'required' : 'nullable', 'string', 'max:'.self::SIGNATURE_MAX_LENGTH, 'regex:/^data:image\/png;base64,[A-Za-z0-9+\/=]+$/'],
            'acknowledged' => [$signatureRequired ? 'accepted' : 'nullable'],
        ];

        if ($withBooking) {
            $rules['booking_id'] = ['nullable', 'integer', 'exists:bookings,id'];
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function fill(GuestRegistration $registration, array $validated, bool $asStaff): void
    {
        $existing = $registration->data ?? [];
        $submitted = $validated['data'] ?? [];
        $data = [];

        foreach (GuestRegistrationForm::fields() as $key => $field) {
            // Guests cannot see or change staff-only fields, so keep whatever staff recorded.
            $data[$key] = $field['staff'] && ! $asStaff
                ? ($existing[$key] ?? null)
                : ($submitted[$key] ?? null);
        }

        $registration->data = $data;
        $registration->guest_name = $data['full_name'] ?? null;
        $registration->check_in = $data['check_in'] ?: null;
        $registration->check_out = $data['check_out'] ?: null;

        if (! empty($validated['signature'])) {
            $registration->signature = $validated['signature'];
            $registration->signed_at = now();
        }
    }

    private function assertScanBelongsTo(Media $media, GuestRegistration $registration): void
    {
        abort_unless(
            $media->model_type === $registration->getMorphClass()
                && $media->model_id === $registration->id
                && $media->collection_name === 'scans',
            404
        );
    }

    private function isStaffFor(User $user, Property $property): bool
    {
        return $user->hasRole('Admin') || ($property->host_id !== null && $property->host_id === $user->id);
    }

    private function visibleTo(User $user): Builder
    {
        $query = GuestRegistration::query();

        if ($user->hasRole('Admin')) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->where('guest_id', $user->id)
                ->orWhere('created_by', $user->id)
                ->orWhereHas('property', fn (Builder $p) => $p->where('host_id', $user->id));
        });
    }

    /** Properties the user manages — used for the list filter. */
    private function staffProperties(User $user): Collection
    {
        return Property::query()
            ->when(! $user->hasRole('Admin'), fn (Builder $q) => $q->where('host_id', $user->id))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /** Staff pick from what they manage; guests can register at any property with a live listing. */
    private function selectableProperties(User $user): Collection
    {
        $managed = $this->staffProperties($user);

        if ($user->hasRole('Admin')) {
            return $managed;
        }

        $public = Property::query()
            ->whereHas('listings', fn (Builder $q) => $q->where('status', 'published'))
            ->orderBy('name')
            ->get(['id', 'name']);

        return $managed->merge($public)->unique('id')->sortBy('name')->values();
    }

    private function selectableBookings(User $user, ?Property $property): Collection
    {
        $query = Booking::with(['listing.property', 'guest'])
            ->whereIn('status', ['pending', 'confirmed', 'completed'])
            ->latest()
            ->limit(50);

        if ($property && $this->isStaffFor($user, $property)) {
            return $query->whereHas('listing', fn (Builder $q) => $q->where('property_id', $property->id))->get();
        }

        return $query->where('guest_id', $user->id)
            ->when($property, fn (Builder $q) => $q->whereHas('listing', fn (Builder $l) => $l->where('property_id', $property->id)))
            ->get();
    }
}
