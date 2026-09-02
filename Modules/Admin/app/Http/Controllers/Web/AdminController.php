<?php

namespace Modules\Admin\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Modules\Bookings\Models\Booking;
use Modules\Events\Models\Event;
use Modules\Listings\Models\Listing;
use Modules\Payments\Models\PaymentTransaction;
use Modules\Payments\Models\Wallet;
use Modules\Referrals\Models\Referral;
use Modules\Reviews\Models\Review;
use Modules\Services\Models\Service;
use Modules\Services\Models\ServiceOrder;

class AdminController extends Controller
{
    public function index(): View
    {
        $usersByRole = User::query()
            ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->selectRaw('roles.name as role, count(*) as total')
            ->groupBy('roles.name')
            ->pluck('total', 'role');

        $bookingsByStatus = Booking::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $serviceOrdersByStatus = ServiceOrder::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $revenueByCurrency = PaymentTransaction::query()
            ->where('type', 'charge')
            ->where('status', 'succeeded')
            ->selectRaw('currency, sum(amount) as total')
            ->groupBy('currency')
            ->pluck('total', 'currency');

        $commissionByCurrency = Referral::query()
            ->where('status', 'paid')
            ->selectRaw('currency, sum(commission_amount) as total')
            ->groupBy('currency')
            ->pluck('total', 'currency');

        $platformUser = User::where('email', config('payments.platform_account_email'))->first();
        $platformRevenueByCurrency = $platformUser
            ? Wallet::where('holder_type', User::class)
                ->where('holder_id', $platformUser->id)
                ->pluck('balance', 'currency')
            : collect();

        return view('admin::index', [
            'usersTotal' => User::count(),
            'usersSuspended' => User::where('status', 'suspended')->count(),
            'usersByRole' => $usersByRole,
            'listingsTotal' => Listing::count(),
            'listingsPublished' => Listing::where('status', 'published')->count(),
            'bookingsTotal' => Booking::count(),
            'bookingsByStatus' => $bookingsByStatus,
            'servicesTotal' => Service::count(),
            'servicesPublished' => Service::where('status', 'published')->count(),
            'serviceOrdersByStatus' => $serviceOrdersByStatus,
            'revenueByCurrency' => $revenueByCurrency,
            'commissionByCurrency' => $commissionByCurrency,
            'platformRevenueByCurrency' => $platformRevenueByCurrency,
            'reviewsTotal' => Review::count(),
            'reviewsHidden' => Review::where('status', 'hidden')->count(),
            'eventsPublished' => Event::where('status', 'published')->count(),
            'eventsPending' => Event::where('status', 'pending_approval')->count(),
        ]);
    }
}
