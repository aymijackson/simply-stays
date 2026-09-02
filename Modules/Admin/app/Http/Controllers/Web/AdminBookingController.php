<?php

namespace Modules\Admin\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Modules\Bookings\Models\Booking;
use Modules\Payments\Exceptions\PaymentException;
use Modules\Payments\Services\RefundProcessor;

class AdminBookingController extends Controller
{
    public function index(): View
    {
        $bookings = Booking::with(['listing.property', 'guest', 'transactions'])
            ->latest()
            ->paginate(20);

        return view('admin::bookings.index', compact('bookings'));
    }

    public function refund(Booking $booking, RefundProcessor $refunds): RedirectResponse
    {
        $charge = $booking->transactions()->where('type', 'charge')->where('status', 'succeeded')->latest()->first();

        abort_unless($charge, 422, 'This booking has no successful charge to refund.');

        try {
            $refunds->refund($charge);
        } catch (PaymentException $exception) {
            return back()->with('status', 'Refund failed: '.$exception->getMessage());
        }

        return back()->with('status', 'Booking refunded and cancelled.');
    }
}
