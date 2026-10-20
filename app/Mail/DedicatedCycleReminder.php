<?php

namespace App\Mail;

use App\Enums\EmailCategory;
use App\Models\User;
use App\Support\EmailPreferences;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Dedicated-status cycle reminder — "you still need N activities
 * before 31 October". Dispatched by `dedicated:send-reminders` on the
 * days configured in `config/dedicated.php` (default: 60, 30 and 7
 * days before cycle end).
 *
 * Gated on EmailCategory::DedicatedReminders via
 * {@see EmailPreferences::canSend()} at the dispatch site — users who
 * have cycle-reminder email switched off get skipped.
 */
class DedicatedCycleReminder extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array{logged:int,required:int,remaining:int,days_to_renewal:int,window_start:CarbonInterface,window_end:CarbonInterface,body:string,body_label:string}  $progress
     */
    public function __construct(
        public User $user,
        public array $progress,
    ) {}

    public function envelope(): Envelope
    {
        $daysLeft = (int) $this->progress['days_to_renewal'];
        $subject = $daysLeft <= 7
            ? 'Final reminder: '.$this->progress['remaining'].' more activity to go before 31 October'
            : $this->progress['remaining'].' more '.($this->progress['remaining'] === 1 ? 'activity' : 'activities').' before your dedicated-status cycle closes';

        return new Envelope(
            subject: $subject,
            to: [$this->user->email],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.dedicated-cycle-reminder',
            with: [
                'user' => $this->user,
                'progress' => $this->progress,
            ],
        );
    }
}
