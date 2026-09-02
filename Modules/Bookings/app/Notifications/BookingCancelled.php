<?php

namespace Modules\Bookings\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Bookings\Models\Booking;

class BookingCancelled extends Notification implements ShouldQueue
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
            ->subject('Booking cancelled: '.$this->booking->listing->title)
            ->greeting('A booking has been cancelled.')
            ->line('The booking for '.$this->booking->listing->title.' ('.$this->booking->check_in->format('M j').' - '.$this->booking->check_out->format('M j, Y').') has been cancelled.')
            ->action('View booking', route('bookings.show', $this->booking));
    }
}
