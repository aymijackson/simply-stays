<?php

namespace Modules\Bookings\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Bookings\Models\Booking;

class BookingRequested extends Notification implements ShouldQueue
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
            ->subject('New booking request for '.$this->booking->listing->title)
            ->greeting('You have a new booking request!')
            ->line($this->booking->guest->name.' wants to book '.$this->booking->listing->title.'.')
            ->line('Check-in: '.$this->booking->check_in->format('D, M j, Y'))
            ->line('Check-out: '.$this->booking->check_out->format('D, M j, Y'))
            ->action('View booking', route('bookings.show', $this->booking));
    }
}
