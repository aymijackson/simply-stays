@extends(auth()->user()->portalLayout())
@php use Modules\Operations\Models\StaffMember; use Modules\Operations\Support\Fmt; @endphp

@section('title', $member->exists ? 'Edit '.$member->name : 'Register staff')

@section('content')
<div class="page page--tight">
    <div class="container">
        @include('operations::partials.header', ['title' => $member->exists ? 'Edit '.$member->name : 'Register a staff member'])

        <div class="card ops-form-card">
            <form method="POST" action="{{ $member->exists ? route('ops.staff.update', $member) : route('ops.staff.store') }}">
                @csrf
                @if ($member->exists) @method('PUT') @endif

                <div class="form-row">
                    <x-input name="name" label="Full name" :value="$member->name" required maxlength="150" />
                    <x-input name="phone" label="Phone" type="tel" :value="$member->phone" maxlength="40" />
                </div>
                <div class="form-row">
                    <x-input name="position" label="Position / job title" :value="$member->position" required maxlength="100" hint="e.g. Cleaner, Store keeper, Chef, Night guard" />
                    <div class="form-group">
                        <label class="form-label" for="department">Department</label>
                        <select name="department" id="department" class="form-control">
                            <option value="">—</option>
                            @foreach (StaffMember::DEPARTMENTS as $dept)
                                <option @selected(old('department', $member->department) === $dept)>{{ $dept }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="employment_type">Employment</label>
                        <select name="employment_type" id="employment_type" class="form-control">
                            @foreach (StaffMember::EMPLOYMENT_TYPES as $key => $label)
                                <option value="{{ $key }}" @selected(old('employment_type', $member->employment_type) === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <x-input name="hired_on" label="Start date" type="date" :value="$member->hired_on?->toDateString()" />
                    <x-input name="salary" label="Monthly pay" inputmode="decimal" :value="Fmt::input($member->salary_amount)" />
                </div>
                <div class="form-row">
                    <x-input name="email" label="Email" type="email" :value="$member->email" maxlength="190" />
                    <x-input name="emergency_contact" label="Emergency contact" :value="$member->emergency_contact" maxlength="190" hint="Name and phone" />
                </div>
                <x-input name="address" label="Home address" :value="$member->address" maxlength="255" />

                <div class="form-group">
                    <label class="form-label">Access to the operations area</label>
                    <div class="ops-radio-cards">
                        @foreach ($accessLevels as $key => $label)
                            @php [$title, $hint] = array_pad(explode(' — ', $label), 2, ''); @endphp
                            <label class="ops-radio-card">
                                <input type="radio" name="access_level" value="{{ $key }}" @checked(old('access_level', $member->access_level) === $key)>
                                <span><strong>{{ $title }}</strong><small>{{ $hint }}</small></span>
                            </label>
                        @endforeach
                    </div>
                    <p class="form-hint">Access only matters for staff who have a login.</p>
                </div>

                @if (! $member->exists)
                    <fieldset class="ops-fieldset">
                        <legend>Login (optional)</legend>
                        <label class="ops-check">
                            <input type="checkbox" name="create_login" value="1" @checked(old('create_login'))>
                            Let this person sign in to clock in/out and record stock
                        </label>
                        <div class="form-row">
                            <x-input name="login_email" label="Login email" type="email" maxlength="190" hint="If an account already uses this email, it is linked instead." />
                            <x-input name="login_password" label="Temporary password" type="password" minlength="8" autocomplete="new-password" />
                        </div>
                    </fieldset>
                @else
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="status">Status</label>
                            <select name="status" id="status" class="form-control">
                                <option value="active" @selected(old('status', $member->status) === 'active')>Active</option>
                                <option value="inactive" @selected(old('status', $member->status) === 'inactive')>Inactive (left / suspended — loses access)</option>
                            </select>
                        </div>
                    </div>
                @endif

                <div class="form-group">
                    <label class="form-label" for="notes">Notes</label>
                    <textarea name="notes" id="notes" class="form-control" rows="2" maxlength="2000">{{ old('notes', $member->notes) }}</textarea>
                </div>

                <div class="ops-form-actions">
                    <a href="{{ $member->exists ? route('ops.staff.show', $member) : route('ops.staff.index') }}" class="btn btn-ghost">Cancel</a>
                    <button class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
