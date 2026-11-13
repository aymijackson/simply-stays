@extends('admin::layouts.admin')

@section('title', $vendor->exists ? 'Edit vendor' : 'Register vendor')

@section('content')
<div class="page page--tight">
    <div class="container">
        <a href="{{ route('admin.vendors.index') }}">&larr; Back to vendors</a>

        <div class="card" style="max-width: 640px; margin: 1rem auto 0; padding: 1.5rem;">
            <h1 style="margin-top: 0;">{{ $vendor->exists ? 'Edit '.$vendor->name : 'Register a vendor' }}</h1>
            @unless ($vendor->exists)
                <p class="text-muted">This creates the vendor's login. Share the email and password with them; they can then add and manage their own services and orders.</p>
            @endunless

            <form method="POST" action="{{ $vendor->exists ? route('admin.vendors.update', $vendor) : route('admin.vendors.store') }}">
                @csrf
                @if ($vendor->exists) @method('PUT') @endif

                <x-input name="name" label="Business or vendor name" :value="$vendor->name" required maxlength="150" hint="Shown to guests on the vendor's services, e.g. Sunrise Kitchen." />
                <div class="form-row">
                    <x-input name="email" label="Login email" type="email" :value="$vendor->email" required maxlength="190" />
                    <x-input name="phone" label="Phone" type="tel" :value="$vendor->phone" maxlength="40" />
                </div>

                <fieldset class="ops-fieldset">
                    <legend>{{ $vendor->exists ? 'Reset password (optional)' : 'Password' }}</legend>
                    <div class="form-row">
                        <x-input name="password" :label="$vendor->exists ? 'New password' : 'Temporary password'" type="password" minlength="8" autocomplete="new-password" :required="! $vendor->exists" />
                        <x-input name="password_confirmation" label="Repeat password" type="password" minlength="8" autocomplete="new-password" :required="! $vendor->exists" />
                    </div>
                    <p class="form-hint">At least 8 characters. Ask the vendor to keep it private.</p>
                </fieldset>

                <div class="ops-form-actions">
                    <a href="{{ route('admin.vendors.index') }}" class="btn btn-ghost">Cancel</a>
                    <button type="submit" class="btn btn-primary">{{ $vendor->exists ? 'Save changes' : 'Register vendor' }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
