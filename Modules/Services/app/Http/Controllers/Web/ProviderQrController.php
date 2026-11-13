<?php

namespace Modules\Services\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Modules\Operations\Models\InventoryLocation;
use Modules\Services\Models\Service;
use Modules\Services\Models\ServiceQrCode;
use Modules\Services\Support\QrSvg;
use Symfony\Component\HttpFoundation\Response;

/** QR codes customers scan to order a service: one generic code plus one per room. */
class ProviderQrController extends Controller
{
    public function index(Service $service): View
    {
        $this->authorizeOwner($service);

        $generic = ServiceQrCode::for($service);
        $rooms = $this->rooms($service);
        $codes = ServiceQrCode::where('service_id', $service->id)->whereNotNull('location_id')->get()->keyBy('location_id');

        return view('services::provider.qr.index', [
            'service' => $service,
            'generic' => $generic,
            'roomsBySuite' => $rooms->groupBy(fn ($l) => $l->property->name.' · '.$l->name),
            'codes' => $codes,
            'roomOrdersAllowed' => $service->audience !== 'non_guests_only',
        ]);
    }

    /** Printable sheet: the chosen room codes and/or the generic code, a few to an A4 page. */
    public function print(Request $request, Service $service): View
    {
        $this->authorizeOwner($service);

        $data = $request->validate([
            'generic' => ['nullable', 'boolean'],
            'locations' => ['nullable', 'array'],
            'locations.*' => ['integer'],
            'copies' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $cards = collect();
        if ($request->boolean('generic')) {
            $cards->push(ServiceQrCode::for($service));
        }

        $allowed = $this->rooms($service)->keyBy('id');
        foreach ($data['locations'] ?? [] as $id) {
            if ($location = $allowed->get((int) $id)) {
                $cards->push(ServiceQrCode::for($service, $location)->setRelation('location', $location));
            }
        }

        $copies = (int) ($data['copies'] ?? 1);
        $cards = $cards->flatMap(fn ($code) => array_fill(0, $copies, $code));

        return view('services::provider.qr.print', compact('service', 'cards'));
    }

    public function download(Service $service, ServiceQrCode $code): Response
    {
        $this->authorizeOwner($service);
        abort_unless($code->service_id === $service->id, 404);

        $name = str($service->title.' '.($code->location?->label() ?? 'any location'))->slug().'-qr.svg';

        return response(QrSvg::make($code->url(), 600), 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
        ]);
    }

    /** Toggle a code off (e.g. a card went missing) or back on. */
    public function toggle(Service $service, ServiceQrCode $code)
    {
        $this->authorizeOwner($service);
        abort_unless($code->service_id === $service->id, 404);

        $code->update(['is_active' => ! $code->is_active]);

        return back()->with('status', $code->is_active ? 'QR code switched back on.' : 'QR code switched off. Scanning it now shows a "not available" page.');
    }

    /**
     * Rooms inside suites (apartment-type locations) at properties this service serves.
     *
     * @return Collection<int, InventoryLocation>
     */
    private function rooms(Service $service): Collection
    {
        $service->loadMissing('properties');

        return InventoryLocation::with('property')
            ->where('type', 'apartment')
            ->when($service->properties->isNotEmpty(), fn ($q) => $q->whereIn('property_id', $service->properties->pluck('id')))
            ->orderBy('property_id')->orderBy('sort_order')->orderBy('name')->orderBy('area')
            ->get();
    }

    private function authorizeOwner(Service $service): void
    {
        abort_unless($service->provider_id === Auth::id() || Auth::user()->hasRole('Admin'), 403);
    }
}
