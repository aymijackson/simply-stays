<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Modules\Admin\Models\SiteSetting;

class PageController extends Controller
{
    public function terms(): View
    {
        return view('pages.terms', ['settings' => SiteSetting::current()]);
    }

    public function privacy(): View
    {
        return view('pages.privacy', ['settings' => SiteSetting::current()]);
    }

    public function contact(): View
    {
        return view('pages.contact', ['settings' => SiteSetting::current()]);
    }
}
