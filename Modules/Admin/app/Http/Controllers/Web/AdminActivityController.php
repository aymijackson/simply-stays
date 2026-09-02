<?php

namespace Modules\Admin\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Spatie\Activitylog\Models\Activity;

class AdminActivityController extends Controller
{
    public function index(): View
    {
        $activities = Activity::with(['causer', 'subject'])
            ->latest()
            ->paginate(30);

        return view('admin::activity.index', compact('activities'));
    }
}
