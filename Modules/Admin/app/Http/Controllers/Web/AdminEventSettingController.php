<?php

namespace Modules\Admin\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Events\Models\EventSetting;

class AdminEventSettingController extends Controller
{
    public function edit(): View
    {
        $settings = EventSetting::current();

        return view('admin::events.settings', compact('settings'));
    }

    public function update(Request $request): RedirectResponse
    {
        $settings = EventSetting::current();

        $settings->update([
            'hosts_can_create' => $request->boolean('hosts_can_create'),
            'providers_can_create' => $request->boolean('providers_can_create'),
        ]);

        return back()->with('status', 'Event settings updated.');
    }
}
