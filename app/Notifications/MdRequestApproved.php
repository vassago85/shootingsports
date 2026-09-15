<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MdRequestApproved extends Notification
{
    use Queueable;

    public function __construct(public User $user) {}

    /** @return array<int, string> */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('You are approved as a match director')
            ->greeting('Hi '.$this->user->name.',')
            ->line('Your match director access on Shooting Sports has been approved.')
            ->line('You can now sign in and open the match director desk to publish and manage events for your club or series.')
            ->action('Open the desk', url('/desk'))
            ->line('If a staff member has already paired your account with your club, you will see it on the desk. If not, reply to this email with the club name and we will link you up.')
            ->salutation('— Shooting Sports');
    }
}
