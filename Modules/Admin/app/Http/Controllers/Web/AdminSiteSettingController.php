<?php

namespace Modules\Admin\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Admin\Models\SiteSetting;

class AdminSiteSettingController extends Controller
{
    public function edit(): View
    {
        $settings = SiteSetting::current();

        return view('admin::settings.edit', compact('settings'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'contact_address' => ['nullable', 'string', 'max:255'],
            'footer_about' => ['nullable', 'string', 'max:1000'],
            'facebook_url' => ['nullable', 'url', 'max:255'],
            'instagram_url' => ['nullable', 'url', 'max:255'],
            'twitter_url' => ['nullable', 'url', 'max:255'],
            'terms_content' => ['nullable', 'string'],
            'privacy_content' => ['nullable', 'string'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'color_theme' => ['required', 'in:'.implode(',', array_keys(SiteSetting::COLOR_THEMES))],
            'layout_style' => ['required', 'in:'.implode(',', array_keys(SiteSetting::LAYOUT_STYLES))],
        ]);

        $settings = SiteSetting::current();
        $settings->update(collect($data)->except('logo')->all());

        if ($request->hasFile('logo')) {
            $settings->addMediaFromRequest('logo')->toMediaCollection('logo');
        }

        return back()->with('status', 'Site settings updated.');
    }

    public function destroyLogo(): RedirectResponse
    {
        SiteSetting::current()->clearMediaCollection('logo');

        return back()->with('status', 'Logo removed.');
    }
}
