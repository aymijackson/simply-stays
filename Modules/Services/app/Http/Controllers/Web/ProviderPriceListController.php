<?php

namespace Modules\Services\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Modules\Services\Models\Service;
use Modules\Services\Support\PriceList;

/** Laundry price list editor: garments x treatments grid. */
class ProviderPriceListController extends Controller
{
    private const MONEY = 'regex:/^\s*(\d[\d,]*(\.\d{1,2})?|-|—)?\s*$/';

    public function edit(Request $request, Service $service): View
    {
        $this->authorizeOwner($service);
        abort_unless($service->isLaundry(), 404);

        $list = PriceList::for($service);

        // An empty list can start from a typical laundry price list.
        if ($list['garments']->isEmpty() && $request->boolean('template')) {
            $list = [
                'treatments' => array_slice(PriceList::DEFAULT_TREATMENTS, 0, 3),
                'garments' => collect(PriceList::DEFAULT_GARMENTS)->flatMap(fn ($names, $category) => collect($names)->map(fn ($name) => [
                    'item' => (object) ['id' => null, 'name' => $name, 'category' => $category, 'is_available' => true],
                    'prices' => [],
                ]))->values(),
            ];
        }

        return view('services::provider.price-list', compact('service', 'list'));
    }

    public function update(Request $request, Service $service): RedirectResponse
    {
        $this->authorizeOwner($service);
        abort_unless($service->isLaundry(), 404);

        $data = $request->validate([
            'treatments' => ['required', 'array', 'min:1', 'max:8'],
            'treatments.*' => ['required', 'string', 'max:40', 'distinct:ignore_case'],
            'rows' => ['nullable', 'array', 'max:200'],
            'rows.*.id' => ['nullable', 'integer'],
            'rows.*.name' => ['required', 'string', 'max:100'],
            'rows.*.category' => ['nullable', 'string', 'max:60'],
            'rows.*.available' => ['nullable', 'boolean'],
            'rows.*.prices' => ['nullable', 'array'],
            'rows.*.prices.*' => ['nullable', self::MONEY],
        ], [
            'treatments.*.distinct' => 'Each column needs a different name.',
            'rows.*.prices.*.regex' => 'Prices must be numbers, e.g. 1500 or 1,500.',
        ]);

        // Columns can be removed in the browser, leaving gaps in their keys (0, 2, 3...).
        // Renumber them, carrying each row's prices along by the original key.
        $columnKeys = array_keys($data['treatments']);
        $treatments = array_values(array_map('trim', $data['treatments']));
        $rows = collect($data['rows'] ?? [])->map(fn ($row) => [
            'id' => $row['id'] ?? null,
            'name' => trim($row['name']),
            'category' => isset($row['category']) ? trim($row['category']) ?: null : null,
            'available' => (bool) ($row['available'] ?? true),
            'prices' => collect($columnKeys)->values()->mapWithKeys(fn ($key, $position) => [$position => PriceList::toMinor($row['prices'][$key] ?? null)])->all(),
        ])->values();

        if ($rows->pluck('name')->map(fn ($name) => mb_strtolower($name))->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['rows' => 'Each item can only appear once. Remove the duplicate rows.']);
        }

        PriceList::save($service, $treatments, $rows->all());

        return redirect()->route('provider.services.price-list.edit', $service)->with('status', 'Price list saved.');
    }

    private function authorizeOwner(Service $service): void
    {
        abort_unless($service->provider_id === Auth::id() || Auth::user()->hasRole('Admin'), 403);
    }
}
