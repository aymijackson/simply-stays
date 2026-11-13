<?php

namespace Modules\Operations\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Operations\Models\InventoryMovement;
use Modules\Operations\Models\Shift;
use Modules\Operations\Models\StaffMember;
use Modules\Operations\Services\FinanceReport;
use Modules\Operations\Support\OpsAccess;
use Modules\Operations\Support\StockAlerts;

class DashboardController extends OpsController
{
    public function index(Request $request): View
    {
        $property = $this->property($request);
        $user = $request->user();
        $today = CarbonImmutable::today();

        $me = OpsAccess::staffMember($user, $property);
        $myShifts = $me
            ? $me->shifts()->where('ends_at', '>=', now()->subHours(12))->where('status', '!=', 'cancelled')->orderBy('starts_at')->limit(5)->get()
            : collect();

        $todayShifts = Shift::with('staffMember')
            ->where('property_id', $property->id)
            ->where('status', '!=', 'cancelled')
            ->where('starts_at', '<', $today->endOfDay())
            ->where('ends_at', '>', $today->startOfDay())
            ->orderBy('starts_at')
            ->get();

        $data = [
            'property' => $property,
            'me' => $me,
            'myShifts' => $myShifts,
            'todayShifts' => $todayShifts,
            'onDuty' => $todayShifts->filter->isOnDuty(),
            'canInventory' => $this->can($request, $property, 'inventory'),
            'canManage' => $this->can($request, $property, 'manager'),
        ];

        if ($data['canInventory']) {
            $data['lowStock'] = StockAlerts::lowStock($property)->take(8);
            $data['lowStockCount'] = StockAlerts::lowStock($property)->count();
            $data['belowPar'] = StockAlerts::belowPar($property)->count();
            $data['recentMovements'] = InventoryMovement::with(['item', 'fromLocation', 'toLocation'])
                ->where('property_id', $property->id)
                ->latest('occurred_at')->latest('id')
                ->limit(8)->get();
        }

        if ($data['canManage']) {
            $data['month'] = FinanceReport::summary($property, $today->startOfMonth(), $today->endOfMonth());
            $data['staffCount'] = StaffMember::where('property_id', $property->id)->active()->count();
            $data['missedShifts'] = Shift::where('property_id', $property->id)
                ->where('status', 'scheduled')
                ->whereNull('clock_in_at')
                ->where('ends_at', '<', now())
                ->where('ends_at', '>', now()->subDays(7))
                ->count();
        }

        return view('operations::dashboard', $data);
    }
}
