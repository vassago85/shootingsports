<x-filament-panels::page>
    @php
        $subscriptionsByClub = $this->subscriptions()->keyBy('organisation_id');
        $clubs = $this->managedClubs();
    @endphp

    <div class="space-y-6" data-test="billing-dashboard">
        @forelse ($clubs as $club)
            @php
                $subscription = $subscriptionsByClub->get($club->id);
            @endphp

            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900"
                 data-test="billing-club-{{ $club->id }}">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-lg font-semibold">{{ $club->name }}</h2>

                    @if ($subscription)
                        <x-filament::badge :color="match ($subscription->status) {
                            \App\Enums\PlatformSubscriptionStatus::Active => 'success',
                            \App\Enums\PlatformSubscriptionStatus::Trial => 'info',
                            \App\Enums\PlatformSubscriptionStatus::PastDue,
                            \App\Enums\PlatformSubscriptionStatus::TrialExpired => 'warning',
                            \App\Enums\PlatformSubscriptionStatus::Suspended,
                            \App\Enums\PlatformSubscriptionStatus::Cancelled => 'danger',
                        }">
                            {{ $subscription->status->getLabel() }}
                        </x-filament::badge>
                    @endif
                </div>

                @if ($subscription)
                    @php
                        $cap = $subscription->tier->memberCap();
                        $usage = (int) $club->active_member_count;
                        $percent = $cap > 0 ? min(100, (int) round($usage / $cap * 100)) : 0;
                        $nextAmountCents = $subscription->tier->priceCents($subscription->interval);
                    @endphp

                    <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Tier</dt>
                            <dd class="mt-1 font-semibold">{{ $subscription->tier->getLabel() }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Next billing amount</dt>
                            <dd class="mt-1 font-semibold">R{{ number_format($nextAmountCents / 100, 2) }}</dd>
                        </div>
                        @if ($subscription->trial_ends_at)
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">Trial ends</dt>
                                <dd class="mt-1">{{ $subscription->trial_ends_at->format('j M Y') }}</dd>
                            </div>
                        @endif
                        @if ($subscription->current_period_end)
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">Current period ends</dt>
                                <dd class="mt-1">{{ $subscription->current_period_end->format('j M Y') }}</dd>
                            </div>
                        @endif
                        @if ($club->tier_grace_ends_at)
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">Tier grace ends</dt>
                                <dd class="mt-1">{{ \Illuminate\Support\Carbon::parse($club->tier_grace_ends_at)->format('j M Y') }}</dd>
                            </div>
                        @endif
                    </dl>

                    <div class="mt-4" data-test="usage-bar">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500 dark:text-gray-400">Active members</span>
                            <span class="font-mono">{{ $usage }} / {{ $cap }}</span>
                        </div>
                        <div class="mt-1 h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                            <div class="h-2 rounded-full {{ $percent >= 100 ? 'bg-danger-500' : 'bg-primary-500' }}"
                                 style="width: {{ $percent }}%"></div>
                        </div>
                    </div>
                @else
                    <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">No subscription yet.</p>
                @endif

                <div class="mt-4 flex flex-wrap gap-2">
                    @if ($club->paystack_subaccount_id === null)
                        <x-filament::button tag="a" :href="url('/desk/billing-paystack?organisation='.$club->id)" size="sm">
                            Enable Paystack subaccount
                        </x-filament::button>
                    @endif

                    @if ($subscription && $subscription->status === \App\Enums\PlatformSubscriptionStatus::Active)
                        <x-filament::button color="gray" size="sm" wire:click="changeTier({{ $club->id }})">
                            Change tier
                        </x-filament::button>
                        <x-filament::button color="danger" outlined size="sm" wire:click="cancelSubscription({{ $club->id }})">
                            Cancel subscription
                        </x-filament::button>
                    @elseif (! $subscription)
                        <x-filament::button color="gray" size="sm" wire:click="requestSubscription({{ $club->id }})">
                            Set up subscription
                        </x-filament::button>
                    @endif
                </div>
            </div>
        @empty
            <div class="rounded-lg border border-gray-200 bg-white p-6 text-sm text-gray-500 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400">
                You do not manage any clubs yet.
            </div>
        @endforelse
    </div>
</x-filament-panels::page>
