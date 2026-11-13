<?php

namespace Modules\Operations\Providers;

use Illuminate\Support\Facades\View;
use Modules\Operations\Support\OpsAccess;
use Nwidart\Modules\Support\ModuleServiceProvider;

class OperationsServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Operations';

    protected string $nameLower = 'operations';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        // Every operations page gets the working property, the switcher list and the user's access level.
        View::composer('operations::*', function ($view) {
            $request = request();
            $user = $request->user();

            if (! $user || $view->offsetExists('opsProperties')) {
                return;
            }

            $view->with([
                'opsProperty' => $request->attributes->get('ops.property'),
                'opsLevel' => $request->attributes->get('ops.level'),
                'opsProperties' => OpsAccess::properties($user),
            ]);
        });
    }
}
