@extends('layouts.app')

@section('title', 'Manage users')

@section('content')
<div class="page page--tight">
    <div class="container">
        <a href="{{ route('admin.index') }}">&larr; Back to overview</a>

        <h1 style="margin-top: 1rem;">Manage users</h1>

        <form method="GET" action="{{ route('admin.users.index') }}" class="card" style="margin-bottom: 1.5rem;">
            <div class="form-row">
                <x-input name="search" label="Search" value="{{ request('search') }}" />
                <div class="form-group">
                    <label class="form-label" for="role">Role</label>
                    <select name="role" id="role" class="form-control">
                        <option value="">All roles</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role }}" @selected(request('role') === $role)>{{ $role }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-sm"><x-icon name="search" style="width:14px;height:14px;" /> Filter</button>
        </form>

        <div class="entity-grid">
            @foreach ($users as $user)
                <div class="card">
                    <div style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.6rem;">
                        <span class="avatar">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                        <div>
                            <div class="entity-card__title" style="margin: 0;">{{ $user->name }}</div>
                            <div class="entity-card__meta" style="margin: 0;">{{ $user->email }}</div>
                        </div>
                    </div>
                    <div style="display: flex; gap: 0.4rem; margin-bottom: 0.75rem;">
                        <span class="badge">{{ $user->primaryRole() ?? 'No role' }}</span>
                        <x-status-badge :status="$user->status" />
                    </div>

                    <form method="POST" action="{{ route('admin.users.role', $user) }}" style="display: flex; gap: 0.5rem;">
                        @csrf
                        @method('PATCH')
                        <select name="role" class="form-control">
                            @foreach ($roles as $role)
                                <option value="{{ $role }}" @selected($user->primaryRole() === $role)>{{ $role }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-outline btn-sm">Save</button>
                    </form>

                    @if ($user->id !== auth()->id())
                        <form method="POST" action="{{ route('admin.users.status', $user) }}" style="margin-top: 0.5rem;">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn {{ $user->status === 'active' ? 'btn-danger-outline' : 'btn-outline' }} btn-sm btn-block">
                                {{ $user->status === 'active' ? 'Suspend' : 'Reactivate' }}
                            </button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>

        <div style="margin-top: 2.5rem;">
            {{ $users->links() }}
        </div>
    </div>
</div>
@endsection
