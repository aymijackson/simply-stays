<?php

namespace Modules\Bookings\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Bookings\Models\Booking;

class BookingConfirmed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Booking $booking) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Booking confirmed: '.$this->booking->listing->title)
            ->greeting('Your booking is confirmed!')
            ->line('Your stay at '.$this->booking->listing->title.' has been confirmed by the host.')
            ->line('Check-in: '.$this->booking->check_in->format('D, M j, Y'))
            ->line('Check-out: '.$this->booking->check_out->format('D, M j, Y'))
            ->action('View booking', route('bookings.show', $this->booking));
    }
}
