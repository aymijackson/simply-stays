@extends(auth()->user()->portalLayout())

@section('title', 'Guest forms')

@section('content')
<div class="page page--tight">
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap;">
            <h1 style="margin: 0;">Guest registration forms</h1>
            <a href="{{ route('guest-forms.create') }}" class="btn btn-primary">New form</a>
        </div>

        @if ($properties->isNotEmpty())
            <form method="GET" class="gform-filters">
                <select name="property" class="form-control" onchange="this.form.submit()">
                    <option value="">All properties</option>
                    @foreach ($properties as $property)
                        <option value="{{ $property->getRouteKey() }}" @selected(request('property') === $property->getRouteKey())>{{ $property->name }}</option>
                    @endforeach
                </select>
                <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Search guest name">
                <button type="submit" class="btn btn-outline">Filter</button>
            </form>
        @endif

        @if ($registrations->isEmpty())
            <div class="card text-center" style="padding: 3rem; margin-top: 1.25rem;">
                <p style="margin-bottom: 1rem;">No registration forms yet.</p>
                <a href="{{ route('guest-forms.create') }}" class="btn btn-primary">Fill a registration form</a>
            </div>
        @else
            <div class="card" style="overflow-x: auto; margin-top: 1.25rem;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Ref</th>
                            <th>Guest</th>
                            <th>Property</th>
                            <th>Stay</th>
                            <th>Signed</th>
                            <th>Uploads</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($registrations as $registration)
                            <tr>
                                <td style="white-space: nowrap;"><strong>{{ $registration->referenceCode() }}</strong>@if ($registration->booking_id)<div class="form-hint" style="margin: 0;">Booking {{ $registration->booking?->referenceCode() }}</div>@endif</td>
                                <td>{{ $registration->guest_name ?? '—' }}</td>
                                <td>{{ $registration->property?->name }}</td>
                                <td style="white-space: nowrap;">
                                    @if ($registration->check_in)
                                        {{ $registration->check_in->format('M j') }} &ndash; {{ $registration->check_out?->format('M j, Y') }}
                                    @else
                                        &mdash;
                                    @endif
                                </td>
                                <td>
                                    @if ($registration->signature)
                                        <span class="badge badge--success">Signed</span>
                                    @else
                                        <span class="badge">Unsigned</span>
                                    @endif
                                </td>
                                <td>{{ $registration->media_count ?: '—' }}</td>
                                <td><a href="{{ route('guest-forms.show', $registration) }}" class="btn btn-outline btn-sm">View</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div style="margin-top: 1rem;">{{ $registrations->links() }}</div>
        @endif
    </div>
</div>
@endsection
