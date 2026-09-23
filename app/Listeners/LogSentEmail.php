<?php

namespace App\Listeners;

use App\Models\EmailLog;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

class LogSentEmail
{
    public function handle(MessageSent $event): void
    {
        $message = $event->message;

        if (! $message instanceof Email) {
            return;
        }

        try {
            EmailLog::query()->create([
                'sent_at' => now(),
                'mailer' => is_string($event->data['mailer'] ?? null) ? $event->data['mailer'] : null,
                'from' => $this->addresses($message->getFrom()),
                'to' => $this->addresses($message->getTo()) ?? 'unknown',
                'subject' => Str::limit((string) $message->getSubject(), 250, ''),
                'body' => $this->body($message),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * @param  array<int, Address>  $addresses
     */
    private function addresses(array $addresses): ?string
    {
        $emails = [];

        foreach ($addresses as $address) {
            if ($address instanceof Address) {
                $emails[] = $address->getAddress();
            }
        }

        if ($emails === []) {
            return null;
        }

        return implode(', ', $emails);
    }

    private function body(Email $message): ?string
    {
        $text = $message->getTextBody();

        if (! is_string($text) || trim($text) === '') {
            $html = $message->getHtmlBody();
            $text = is_string($html) ? html_entity_decode(strip_tags($html)) : '';
        }

        $text = trim($text);

        if ($text === '') {
            return null;
        }

        return Str::limit($text, 20000, '');
    }
}
