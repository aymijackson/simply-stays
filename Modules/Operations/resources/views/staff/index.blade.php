@extends(auth()->user()->portalLayout())
@php use Modules\Operations\Models\StaffMember; use Modules\Operations\Support\Fmt; @endphp

@section('title', 'Staff')

@section('content')
<div class="page page--tight">
    <div class="container">
        @include('operations::partials.header', ['title' => 'Staff'])

        <div class="ops-toolbar ops-toolbar--end">
            <div class="ops-toolbar__actions">
                <a href="{{ route('ops.shifts.attendance') }}" class="btn btn-outline">Attendance</a>
                <a href="{{ route('ops.staff.create') }}" class="btn btn-primary">Register staff</a>
            </div>
        </div>

        <form method="GET" class="ops-filters" role="search">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control ops-filters__search" placeholder="Search name">
            <select name="department" class="form-control">
                <option value="">All departments</option>
                @foreach (StaffMember::DEPARTMENTS as $dept)<option @selected(request('department') === $dept)>{{ $dept }}</option>@endforeach
            </select>
            <select name="status" class="form-control">
                <option value="active" @selected(request('status', 'active') === 'active')>Active</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                <option value="all" @selected(request('status') === 'all')>Everyone</option>
            </select>
            <button class="btn btn-primary">Search</button>
        </form>

        @if ($staff->isEmpty())
            <div class="card text-center" style="padding: 3rem;">
                <p>No staff registered yet.</p>
                <a href="{{ route('ops.staff.create') }}" class="btn btn-primary">Register your first staff member</a>
            </div>
        @else
            <div class="card card--flush ops-table-wrap">
                <table class="data-table">
                    <thead>
                        <tr><th>Name</th><th>Position</th><th>Department</th><th>Phone</th><th>Access</th><th class="ops-num">Monthly pay</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($staff as $member)
                            <tr @class(['ops-muted-row' => ! $member->isActive()])>
                                <td>
                                    <a href="{{ route('ops.staff.show', $member) }}"><strong>{{ $member->name }}</strong></a>
                                    @if ($member->user_id)<span class="badge" title="Can sign in">Login</span>@endif
                                    @unless ($member->isActive())<span class="badge badge--dark">Inactive</span>@endunless
                                </td>
                                <td>{{ $member->position }}</td>
                                <td class="text-muted">{{ $member->department ?? '—' }}</td>
                                <td class="ops-nowrap">{{ $member->phone ?? '—' }}</td>
                                <td><span class="badge @if ($member->access_level === 'manager') badge--gold @endif">{{ $member->accessLabel() }}</span></td>
                                <td class="ops-num">{{ Fmt::money($member->salary_amount, $member->currency) }}</td>
                                <td><a href="{{ route('ops.shifts.create', ['staff' => $member]) }}" class="btn btn-ghost btn-sm">Schedule</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr><td colspan="5"><strong>Monthly payroll (active staff)</strong></td><td class="ops-num"><strong>{{ Fmt::money($monthlyPayroll) }}</strong></td><td></td></tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
