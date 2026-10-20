<?php

namespace App\Jobs;

use App\Models\Claim;
use App\Models\DeviceToken;
use App\Models\User;
use App\Observers\EventObserver;
use App\Services\Push\ExpoPushClient;
use Illuminate\Bus\Queueable as BusQueueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Fans one notification out to every registered iOS/Android device token for
 * a user. Web tokens are skipped — the Web Push protocol is a different
 * transport and will land in its own job if we ever add it.
 *
 * Dispatched (queued) from behavioural hooks like {@see Claim::approve}
 * and {@see EventObserver::updated}. Kept intentionally
 * generic (title / body / data) so a new trigger is a one-line dispatch,
 * not another job class.
 */
class SendPushNotification implements ShouldQueue
{
    use BusQueueable, Dispatchable, InteractsWithQueue, SerializesModels;

    /**
     * @param  array<string, mixed>  $data  Arbitrary JSON payload — the mobile
     *                                      client reads `data.type` and
     *                                      `data.url` to deep-link.
     */
    public function __construct(
        public readonly int $userId,
        public readonly string $title,
        public readonly string $body,
        public readonly array $data = [],
    ) {}

    public function handle(ExpoPushClient $client): void
    {
        $user = User::query()->find($this->userId);

        if ($user === null) {
            return;
        }

        /** @var Collection<int, DeviceToken> $tokens */
        $tokens = $user->deviceTokens()
            ->whereIn('platform', ['ios', 'android'])
            ->get();

        if ($tokens->isEmpty()) {
            return;
        }

        $messages = $tokens->map(fn (DeviceToken $token): array => [
            'to' => $token->token,
            'title' => $this->title,
            'body' => $this->body,
            'sound' => 'default',
            'priority' => 'high',
            'data' => $this->data,
        ])->values()->all();

        $tickets = $client->sendBatch($messages);
        $deadTokens = $client->deadTokensFromTickets($messages, $tickets);

        if ($deadTokens !== []) {
            DeviceToken::query()
                ->where('user_id', $user->id)
                ->whereIn('token', $deadTokens)
                ->delete();
        }
    }
}
