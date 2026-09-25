@extends('admin::layouts.admin')

@section('title', 'Event settings')

@section('content')
<div class="page page--tight">
    <div class="container">
        <div class="card" style="max-width: 560px; margin: 0 auto;">
            <h1>Event settings</h1>
            <p>Control which account types are currently allowed to submit new events. Admins can always create events.</p>

            <form method="POST" action="{{ route('admin.events.settings.update') }}">
                @csrf
                @method('PATCH')

                <label class="radio-card" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.6rem;">
                    <span>Hosts can create events</span>
                    <input type="checkbox" name="hosts_can_create" value="1" @checked($settings->hosts_can_create)>
                </label>

                <label class="radio-card" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                    <span>Service providers can create events</span>
                    <input type="checkbox" name="providers_can_create" value="1" @checked($settings->providers_can_create)>
                </label>

                <button type="submit" class="btn btn-primary btn-block">Save settings</button>
            </form>
        </div>
    </div>
</div>
@endsection
