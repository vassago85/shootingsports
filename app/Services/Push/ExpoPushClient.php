<?php

namespace App\Services\Push;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper around Expo's push service (https://exp.host/--/api/v2/push/send).
 * Single HTTP endpoint, well-defined JSON contract, no auth header required for
 * public Expo projects — chosen over the Expo SDK composer package to avoid
 * pulling a dependency for one POST call.
 *
 * Returns the Expo tickets so the caller can act on
 * {@see https://docs.expo.dev/push-notifications/sending-notifications/#individual-errors
 * individual errors}, chiefly `DeviceNotRegistered` which means the device
 * uninstalled the app and the token row should be deleted so we stop paying
 * for a quota slot that will never deliver.
 */
class ExpoPushClient
{
    private const ENDPOINT = 'https://exp.host/--/api/v2/push/send';

    /**
     * Expo caps a single request at 100 messages. Batching above that is the
     * caller's job (SendPushNotification only ever fans out to one user's
     * devices, so the batch is naturally small).
     */
    public const int MAX_BATCH = 100;

    public function __construct(private readonly int $timeoutSeconds = 10) {}

    /**
     * Fire a batch of messages and return the per-message ticket array in
     * input order. On a transport failure returns an empty collection and
     * logs — never throws — so an upstream job retry (if configured) is the
     * only way to retry.
     *
     * @param  list<array{to: string, title: string, body: string, data?: array<string, mixed>, sound?: string|null, priority?: 'default'|'normal'|'high', channelId?: string}>  $messages
     * @return Collection<int, array{status: string, id?: string, message?: string, details?: array<string, mixed>}>
     */
    public function sendBatch(array $messages): Collection
    {
        if ($messages === []) {
            return collect();
        }

        if (count($messages) > self::MAX_BATCH) {
            throw new \InvalidArgumentException(
                sprintf('Expo batch size %d exceeds the %d limit.', count($messages), self::MAX_BATCH),
            );
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout($this->timeoutSeconds)
                ->post(self::ENDPOINT, $messages);
        } catch (ConnectionException $e) {
            Log::warning('Expo push transport failed', [
                'count' => count($messages),
                'error' => $e->getMessage(),
            ]);

            return collect();
        }

        if (! $response->successful()) {
            Log::warning('Expo push HTTP non-2xx', [
                'count' => count($messages),
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return collect();
        }

        /** @var array{data?: list<array<string, mixed>>, errors?: list<array<string, mixed>>} $payload */
        $payload = (array) $response->json();

        /** @var list<array<string, mixed>> $tickets */
        $tickets = $payload['data'] ?? [];

        return collect($tickets);
    }

    /**
     * Scan a ticket collection for tokens Expo reports as dead so the caller
     * can delete them. Matches on `DeviceNotRegistered` (uninstalled app)
     * only — `MessageTooBig`, `MessageRateExceeded` and friends are
     * ephemeral and should not drop the row.
     *
     * @param  list<array{to: string, title: string, body: string}>  $messages
     * @param  Collection<int, array{status: string, details?: array<string, mixed>}>  $tickets
     * @return list<string>
     */
    public function deadTokensFromTickets(array $messages, Collection $tickets): array
    {
        $dead = [];

        foreach ($tickets as $index => $ticket) {
            if (($ticket['status'] ?? null) !== 'error') {
                continue;
            }

            if (($ticket['details']['error'] ?? null) !== 'DeviceNotRegistered') {
                continue;
            }

            $to = $messages[$index]['to'] ?? null;

            if (is_string($to) && $to !== '') {
                $dead[] = $to;
            }
        }

        return $dead;
    }
}
