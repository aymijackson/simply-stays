<?php

namespace Modules\Operations\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Core\Support\RouteKey;
use Modules\Listings\Models\Property;
use Modules\Operations\Models\FinanceCategory;
use Modules\Operations\Models\FinanceTransaction;
use Modules\Operations\Models\StaffMember;
use Modules\Operations\Services\BookingRevenueSync;
use Modules\Operations\Services\FinanceReport;
use Modules\Operations\Support\OpsContext;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceController extends OpsController
{
    public function index(Request $request): View
    {
        $property = $this->property($request, 'manager');
        [$from, $to] = $this->period($request);

        $query = $this->filtered($request, $property, $from, $to);
        $totals = (clone $query)->reorder()->groupBy('type')->selectRaw('type, SUM(amount) as total')->pluck('total', 'type');

        return view('operations::finance.index', [
            'property' => $property,
            'from' => $from,
            'to' => $to,
            'transactions' => $query->with(['category', 'staffMember'])->withCount('media')->paginate(40)->withQueryString(),
            'income' => (int) ($totals['income'] ?? 0),
            'expense' => (int) ($totals['expense'] ?? 0),
            'categories' => FinanceCategory::availableTo($property)->orderBy('type')->orderBy('name')->get(),
            'pendingBookings' => BookingRevenueSync::pending($property)->count(),
        ]);
    }

    public function create(Request $request): View
    {
        $property = $this->property($request, 'manager');

        return view('operations::finance.form', $this->formData($property, new FinanceTransaction([
            'type' => $request->input('type') === 'income' ? 'income' : 'expense',
            'occurred_on' => today(),
            'payment_method' => 'cash',
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $property = $this->property($request, 'manager');
        $data = $this->validated($request, $property);

        $transaction = FinanceTransaction::create($data + [
            'property_id' => $property->id,
            'currency' => OpsContext::currency(),
            'recorded_by' => $request->user()->id,
        ]);
        $this->attachReceipt($request, $transaction);

        return redirect()->route($request->boolean('add_another') ? 'ops.finance.create' : 'ops.finance.index', $request->boolean('add_another') ? ['type' => $transaction->type] : [])
            ->with('status', ucfirst($transaction->type).' of '.$transaction->money()->format().' recorded.');
    }

    public function edit(Request $request, FinanceTransaction $transaction): View
    {
        $property = $this->propertyOf($request, $transaction, 'manager');

        return view('operations::finance.form', $this->formData($property, $transaction) + [
            'receipts' => $transaction->getMedia('receipts'),
        ]);
    }

    public function update(Request $request, FinanceTransaction $transaction): RedirectResponse
    {
        $property = $this->propertyOf($request, $transaction, 'manager');
        $transaction->update($this->validated($request, $property));
        $this->attachReceipt($request, $transaction);

        return redirect()->route('ops.finance.index')->with('status', 'Entry updated.');
    }

    public function destroy(Request $request, FinanceTransaction $transaction): RedirectResponse
    {
        $this->propertyOf($request, $transaction, 'manager');
        $transaction->delete();

        return redirect()->route('ops.finance.index')->with('status', 'Entry deleted.');
    }

    public function receipt(Request $request, FinanceTransaction $transaction, Media $media): StreamedResponse
    {
        $this->propertyOf($request, $transaction, 'manager');
        abort_unless($media->model_type === $transaction->getMorphClass() && $media->model_id === $transaction->id, 404);

        return $media->toInlineResponse($request);
    }

    public function destroyReceipt(Request $request, FinanceTransaction $transaction, Media $media): RedirectResponse
    {
        $this->propertyOf($request, $transaction, 'manager');
        abort_unless($media->model_type === $transaction->getMorphClass() && $media->model_id === $transaction->id, 404);
        $media->delete();

        return redirect()->route('ops.finance.edit', $transaction)->with('status', 'Receipt removed.');
    }

    public function report(Request $request): View
    {
        $property = $this->property($request, 'manager');
        [$from, $to] = $this->period($request);

        return view('operations::finance.report', [
            'property' => $property,
            'from' => $from,
            'to' => $to,
            'summary' => FinanceReport::summary($property, $from, $to),
            'monthly' => FinanceReport::monthly($property, 12, $to),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $property = $this->property($request, 'manager');
        [$from, $to] = $this->period($request);
        $query = $this->filtered($request, $property, $from, $to)->with(['category', 'staffMember']);

        $filename = sprintf('books-%s-%s-to-%s.csv', str($property->name)->slug(), $from->toDateString(), $to->toDateString());

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Type', 'Category', 'Amount', 'Currency', 'Payment method', 'Payee / payer', 'Reference', 'Description', 'Booking', 'Staff']);
            $query->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $t) {
                    fputcsv($out, [
                        $t->occurred_on->toDateString(), $t->type, $t->category?->name, number_format($t->amount / 100, 2, '.', ''),
                        $t->currency, FinanceTransaction::PAYMENT_METHODS[$t->payment_method] ?? $t->payment_method,
                        $t->counterparty, $t->reference, $t->description, $t->booking_id, $t->staffMember?->name,
                    ]);
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function syncBookings(Request $request): RedirectResponse
    {
        $property = $this->property($request, 'manager');
        $posted = BookingRevenueSync::sync($property, $request->user()->id);

        return redirect()->route('ops.finance.index')->with('status', $posted
            ? "{$posted} booking(s) posted as room revenue."
            : 'All confirmed bookings are already in the books.');
    }

    public function categories(Request $request): View
    {
        $property = $this->property($request, 'manager');
        FinanceCategory::ensureDefaults();

        return view('operations::finance.categories', [
            'property' => $property,
            'categories' => FinanceCategory::availableTo($property)->withCount(['transactions' => fn ($q) => $q->where('property_id', $property->id)])->orderBy('type')->orderBy('name')->get()->groupBy('type'),
        ]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $property = $this->property($request, 'manager');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'type' => ['required', Rule::in(['income', 'expense'])],
        ]);

        FinanceCategory::create($data + ['property_id' => $property->id]);

        return redirect()->route('ops.finance.categories')->with('status', 'Category added.');
    }

    public function destroyCategory(Request $request, FinanceCategory $category): RedirectResponse
    {
        abort_if($category->isShared(), 403, 'Default categories cannot be deleted.');
        $this->propertyOf($request, $category, 'manager');

        if ($category->transactions()->exists()) {
            return back()->withErrors(['category' => 'This category has entries. Move them to another category first.']);
        }

        $category->delete();

        return redirect()->route('ops.finance.categories')->with('status', 'Category deleted.');
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function period(Request $request): array
    {
        $from = $request->filled('from') ? CarbonImmutable::parse($request->input('from')) : CarbonImmutable::today()->startOfMonth();
        $to = $request->filled('to') ? CarbonImmutable::parse($request->input('to')) : CarbonImmutable::today()->endOfMonth();

        return $from->gt($to) ? [$to->startOfDay(), $from->startOfDay()] : [$from->startOfDay(), $to->startOfDay()];
    }

    private function filtered(Request $request, Property $property, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return FinanceTransaction::query()
            ->where('property_id', $property->id)
            ->whereBetween('occurred_on', [$from->toDateString(), $to->toDateString()])
            ->when(in_array($request->input('type'), ['income', 'expense'], true), fn ($q) => $q->where('type', $request->input('type')))
            ->when($request->filled('category'), fn ($q) => $q->where('category_id', RouteKey::id(FinanceCategory::class, $request->query('category')) ?? 0))
            ->when($request->filled('method'), fn ($q) => $q->where('payment_method', $request->string('method')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('description', 'like', '%'.$request->string('q').'%')
                ->orWhere('counterparty', 'like', '%'.$request->string('q').'%')
                ->orWhere('reference', 'like', '%'.$request->string('q').'%')))
            ->orderByDesc('occurred_on')->orderByDesc('id');
    }

    private function validated(Request $request, Property $property): array
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['income', 'expense'])],
            'category_id' => ['required', Rule::exists('ops_finance_categories', 'id')->where(fn ($q) => $q->whereNull('property_id')->orWhere('property_id', $property->id))],
            'amount' => ['required', self::MONEY],
            'occurred_on' => ['required', 'date'],
            'payment_method' => ['required', Rule::in(array_keys(FinanceTransaction::PAYMENT_METHODS))],
            'counterparty' => ['nullable', 'string', 'max:150'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'staff_member_id' => ['nullable', Rule::exists('ops_staff_members', 'id')->where('property_id', $property->id)],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ]);

        $category = FinanceCategory::find($data['category_id']);
        if ($category->type !== $data['type']) {
            $this->fail('category_id', "Pick an {$data['type']} category.");
        }

        $amount = $this->toMinor($data['amount']);
        if ($amount <= 0) {
            $this->fail('amount', 'Enter an amount greater than zero.');
        }

        unset($data['receipt']);

        return ['amount' => $amount] + $data;
    }

    private function attachReceipt(Request $request, FinanceTransaction $transaction): void
    {
        if ($request->hasFile('receipt')) {
            $transaction->addMediaFromRequest('receipt')->toMediaCollection('receipts');
        }
    }

    private function formData(Property $property, FinanceTransaction $transaction): array
    {
        FinanceCategory::ensureDefaults();

        return [
            'property' => $property,
            'transaction' => $transaction,
            'categories' => FinanceCategory::availableTo($property)->orderBy('name')->get()->groupBy('type'),
            'staff' => StaffMember::where('property_id', $property->id)->orderBy('name')->get(['id', 'name']),
            'receipts' => collect(),
        ];
    }
}
