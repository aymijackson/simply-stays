@extends(auth()->user()->portalLayout())
@php use Modules\Operations\Models\Shift; use Modules\Operations\Models\StaffMember; use Modules\Operations\Models\FinanceTransaction; use Modules\Operations\Support\Fmt; @endphp

@section('title', $member->name)

@section('content')
<div class="page page--tight">
    <div class="container">
        @include('operations::partials.header', ['title' => $member->name])

        <div class="ops-toolbar">
            <div>
                <strong>{{ $member->position }}</strong>
                @if ($member->department)<span class="text-muted">&middot; {{ $member->department }}</span>@endif
                <span class="badge @if ($member->access_level === 'manager') badge--gold @endif">{{ $member->accessLabel() }}</span>
                @unless ($member->isActive())<span class="badge badge--dark">Inactive</span>@endunless
            </div>
            <div class="ops-toolbar__actions">
                <a href="{{ route('ops.shifts.create', ['staff' => $member->id]) }}" class="btn btn-outline">Schedule shift</a>
                <a href="{{ route('ops.staff.edit', $member) }}" class="btn btn-primary">Edit</a>
            </div>
        </div>

        <div class="module-grid ops-stats">
            <div class="card stat-tile"><p>Hours, last 30 days</p><h2>{{ Shift::formatMinutes($workedMinutes) }}</h2></div>
            <div class="card stat-tile"><p>Shifts worked</p><h2>{{ $shiftsWorked }}</h2></div>
            <div class="card stat-tile"><p>Shifts missed</p><h2 class="{{ $shiftsMissed ? 'ops-neg' : '' }}">{{ $shiftsMissed }}</h2></div>
            <div class="card stat-tile"><p>Monthly pay</p><h2>{{ Fmt::money($member->salary_amount, $member->currency) }}</h2></div>
        </div>

        <div class="ops-columns">
            <div class="card ops-card">
                <h3 class="ops-card__title">Details</h3>
                <dl class="ops-dl">
                    <dt>Phone</dt><dd>{{ $member->phone ?? '—' }}</dd>
                    <dt>Email</dt><dd>{{ $member->email ?? '—' }}</dd>
                    <dt>Employment</dt><dd>{{ StaffMember::EMPLOYMENT_TYPES[$member->employment_type] ?? $member->employment_type }}@if ($member->hired_on), since {{ $member->hired_on->format('j M Y') }}@endif</dd>
                    <dt>Emergency contact</dt><dd>{{ $member->emergency_contact ?? '—' }}</dd>
                    <dt>Address</dt><dd>{{ $member->address ?? '—' }}</dd>
                    <dt>Login</dt><dd>{{ $member->user ? $member->user->email : 'No login' }}</dd>
                </dl>
                @if ($member->notes)<p class="text-muted">{{ $member->notes }}</p>@endif
            </div>

            <div class="card ops-card">
                <h3 class="ops-card__title">Upcoming shifts</h3>
                @forelse ($upcoming as $shift)
                    <div class="ops-row">
                        <span>{{ $shift->starts_at->format('D j M, g:i A') }} – {{ $shift->ends_at->format('g:i A') }} @if ($shift->area)<span class="text-muted">· {{ $shift->area }}</span>@endif</span>
                        <a href="{{ route('ops.shifts.edit', $shift) }}" class="btn btn-ghost btn-sm">Edit</a>
                    </div>
                @empty
                    <p class="text-muted">Nothing scheduled.</p>
                @endforelse
            </div>

            <div class="card ops-card">
                <h3 class="ops-card__title">Pay salary</h3>
                <form method="POST" action="{{ route('ops.staff.pay', $member) }}">
                    @csrf
                    <div class="form-row">
                        <x-input name="amount" label="Amount" inputmode="decimal" :value="Fmt::input($member->salary_amount)" required />
                        <x-input name="occurred_on" label="Paid on" type="date" :value="today()->toDateString()" required />
                    </div>
                    <div class="form-row">
                        <x-input name="period" label="For" :value="now()->format('F Y')" maxlength="60" />
                        <div class="form-group">
                            <label class="form-label" for="payment_method">Method</label>
                            <select name="payment_method" id="payment_method" class="form-control">
                                @foreach (FinanceTransaction::PAYMENT_METHODS as $key => $label)<option value="{{ $key }}" @selected($key === 'transfer')>{{ $label }}</option>@endforeach
                            </select>
                        </div>
                    </div>
                    <x-input name="reference" label="Reference" maxlength="100" />
                    <button class="btn btn-primary btn-block">Record payment</button>
                </form>

                @if ($payments->isNotEmpty())
                    <h4 style="margin-top:1.25rem;">Recent payments</h4>
                    @foreach ($payments as $p)
                        <div class="ops-row"><span>{{ $p->occurred_on->format('j M Y') }} <span class="text-muted">{{ $p->description }}</span></span><strong>{{ $p->money()->format() }}</strong></div>
                    @endforeach
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
