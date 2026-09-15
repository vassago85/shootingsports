<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MdRequestRejected extends Notification
{
    use Queueable;

    public function __construct(
        public User $user,
        public ?string $reason = null,
    ) {}

    /** @return array<int, string> */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('About your match director request')
            ->greeting('Hi '.$this->user->name.',')
            ->line('Thanks for asking to become a match director on Shooting Sports.')
            ->line('We were not able to approve the request this time.');

        if (filled($this->reason)) {
            $mail->line('Reviewer note: '.$this->reason);
        }

        return $mail
            ->line('If you think this was a mistake — for example if we could not link your name to the club you named — reply to this email and we will take another look. Your shooter account still works exactly as before.')
            ->action('Open your calendar', url('/my-calendar'))
            ->salutation('— Shooting Sports');
    }
}
