@extends('admin::layouts.admin')

@section('title', 'Vendors')

@section('content')
@php $filtered = (bool) array_filter(request()->only(['q', 'status'])); @endphp
<div class="page page--tight">
    <div class="container">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h1 style="margin: 0;">Vendors</h1>
                <p class="text-muted" style="margin: 0.25rem 0 0;">Service providers who sell through the platform. Each one gets a login to manage their own services and orders.</p>
            </div>
            <a href="{{ route('admin.vendors.create') }}" class="btn btn-primary">Register vendor</a>
        </div>

        <form method="GET" class="ops-filters" role="search">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control ops-filters__search" placeholder="Search name, email or phone" aria-label="Search vendors">
            <select name="status" class="form-control" aria-label="Status" onchange="this.form.submit()">
                <option value="">Active &amp; suspended</option>
                <option value="active" @selected(request('status') === 'active')>Active</option>
                <option value="suspended" @selected(request('status') === 'suspended')>Suspended</option>
            </select>
            <button class="btn btn-primary">Search</button>
            @if ($filtered)
                <a href="{{ route('admin.vendors.index') }}" class="ops-filters__clear">Clear filters</a>
            @endif
        </form>

        @if ($vendors->isEmpty())
            <div class="card text-center" style="padding: 3rem;">
                <p style="margin-bottom: 1rem;">{{ $filtered ? 'No vendors match.' : 'No vendors yet.' }}</p>
                @unless ($filtered)<a href="{{ route('admin.vendors.create') }}" class="btn btn-primary">Register your first vendor</a>@endunless
            </div>
        @else
            <div class="card card--flush ops-table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Vendor</th>
                            <th>Contact</th>
                            <th class="ops-num">Services</th>
                            <th class="ops-num">Orders</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($vendors as $vendor)
                            @php
                                $s = $services[$vendor->id] ?? null;
                                $o = $orders[$vendor->id] ?? null;
                            @endphp
                            <tr @class(['ops-muted-row' => $vendor->status === 'suspended'])>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.6rem;">
                                        <span class="avatar">{{ strtoupper(substr($vendor->name, 0, 1)) }}</span>
                                        <div>
                                            <strong>{{ $vendor->name }}</strong>
                                            @if (session('new_vendor_id') === $vendor->id)<span class="badge badge--success">New</span>@endif
                                            <div class="form-hint" style="margin: 0;">Since {{ $vendor->created_at->format('M Y') }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    {{ $vendor->email }}
                                    @if ($vendor->phone)<div class="form-hint" style="margin: 0;">{{ $vendor->phone }}</div>@endif
                                </td>
                                <td class="ops-num">
                                    <a href="{{ route('provider.services.index', ['provider' => $vendor]) }}">{{ (int) ($s->total ?? 0) }}</a>
                                    <div class="form-hint" style="margin: 0;">{{ (int) ($s->live ?? 0) }} live</div>
                                </td>
                                <td class="ops-num">
                                    {{ (int) ($o->total ?? 0) }}
                                    @if ((int) ($o->open ?? 0))<div class="form-hint" style="margin: 0;">{{ (int) $o->open }} open</div>@endif
                                </td>
                                <td>
                                    @if ($vendor->status === 'suspended')
                                        <span class="badge badge--danger">Suspended</span>
                                    @else
                                        <span class="badge badge--success">Active</span>
                                    @endif
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.4rem; justify-content: flex-end; flex-wrap: wrap;">
                                        <a href="{{ route('provider.services.create', ['provider' => $vendor]) }}" class="btn btn-outline btn-sm">Add service</a>
                                        <a href="{{ route('admin.vendors.edit', $vendor) }}" class="btn btn-ghost btn-sm">Edit</a>
                                        <form method="POST" action="{{ route('admin.users.status', $vendor) }}" onsubmit="return confirm('{{ $vendor->status === 'suspended' ? 'Reactivate' : 'Suspend' }} {{ addslashes($vendor->name) }}?');">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-sm {{ $vendor->status === 'suspended' ? 'btn-outline' : 'btn-danger-outline' }}">{{ $vendor->status === 'suspended' ? 'Reactivate' : 'Suspend' }}</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div style="margin-top: 1rem;">{{ $vendors->links() }}</div>
        @endif
    </div>
</div>
@endsection
