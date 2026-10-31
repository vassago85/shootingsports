<x-layouts.public
    :title="$seo->title"
    :description="$seo->description"
    :canonical="$seo->canonical"
    :robots="$seo->robots"
    :json-ld="$jsonLd"
>
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">{{ collect([$organisation->type->getLabel(), $organisation->province?->getLabel()])->filter()->implode(' · ') }}</p>
                <div class="hero-row">
                    @if ($organisation->logoUrl())
                        <img class="hero-logo" src="{{ $organisation->logoUrl() }}" alt="{{ $organisation->name }} logo" width="96" height="96">
                    @endif
                    <div>
                        <h1>{{ $organisation->name }}</h1>
                        @if (filled($organisation->description))
                            <p>{{ $organisation->description }}</p>
                        @endif
                        <x-verification-badge :listing="$organisation" />
                    </div>
                </div>
            </div>
        </section>
        <section class="block">
            <div class="wrap">
                @php
                    $clubMeta = collect([
                        $organisation->town,
                        $organisation->province?->getLabel(),
                        $organisation->visitors_welcome ? 'Visitors welcome' : null,
                    ])->filter();
                @endphp
                @if ($clubMeta->isNotEmpty())
                    <p class="meta" style="font-family:var(--f-mono);font-size:13px;color:var(--slate)">{{ $clubMeta->implode(' · ') }}</p>
                @endif
                @if ($organisation->website_url)
                    <p><a class="label" href="{{ $organisation->website_url }}" rel="noopener noreferrer" style="border-bottom:1px solid var(--brass);text-decoration:none">{{ parse_url($organisation->website_url, PHP_URL_HOST) }} →</a></p>
                @endif
                <p style="margin:14px 0 0; display:flex; gap:10px; flex-wrap:wrap">
                    <a class="btn" href="{{ route('enquiries.listing', ['type' => 'organisation', 'id' => $organisation->id]) }}">Enquire via platform</a>
                    <livewire:follow-button
                        type="organisation"
                        :id="$organisation->id"
                        label="Follow this club"
                        :key="'follow-org-'.$organisation->id"
                    />
                </p>
                @if ($organisation->acceptsMembershipApplications())
                    <livewire:apply-for-membership
                        :organisation="$organisation"
                        :key="'apply-'.$organisation->id"
                    />
                @endif
                @if ($inferredDisciplines->isNotEmpty())
                    <div class="club-tags" style="margin:16px 0 28px">
                        @foreach ($inferredDisciplines as $discipline)
                            @if ($discipline->is_published)
                                <a class="tag" href="{{ route('disciplines.show', $discipline->slug) }}">{{ $discipline->name }}</a>
                                @if ($organisation->province)
                                    <a class="tag" href="{{ route('disciplines.province', [$discipline->slug, $organisation->province->urlSlug()]) }}">{{ $discipline->name }} in {{ $organisation->province->getLabel() }}</a>
                                @endif
                            @endif
                        @endforeach
                    </div>
                @endif

                @if ($events->isNotEmpty())
                    <div class="sec-head">
                        <p class="label">Calendar</p>
                        <h2>Upcoming matches</h2>
                    </div>
                    <div class="dope-grid">
                        @foreach ($events as $event)
                            <x-event-card :event="$event" />
                        @endforeach
                    </div>
                @endif
                <p style="margin-top:18px">
                    <a class="label" href="{{ route('ical.organisation', $organisation->slug) }}" style="text-decoration:none;border-bottom:1px solid var(--brass)">Subscribe via iCal →</a>
                </p>

                @if ($commonRanges->isNotEmpty())
                    <div class="sec-head" style="margin-top:36px">
                        <p class="label">Where they shoot</p>
                        <h2>Ranges commonly used</h2>
                    </div>
                    <ul class="club-ranges" style="list-style:none;padding:0;margin:0 0 28px;display:grid;gap:8px">
                        @foreach ($commonRanges as $range)
                            <li>
                                <a href="{{ route('ranges.show', $range->slug) }}">{{ $range->name }}</a>
                                @if ($range->town || $range->province)
                                    <span style="color:var(--slate);font-family:var(--f-mono);font-size:13px"> · {{ collect([$range->town, $range->province?->code()])->filter()->implode(' · ') }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif

                {{-- Owner tools stay at the bottom. Match directors and
                     club admins only see the club they actually run.
                     Everyone else gets the claim link, and only while
                     the listing has no owner. --}}
                @if ($canAdminister)
                    <x-owner-panel title="For club administrators">
                        <p>Manage this club — update information, publish events, or embed the calendar on your site.</p>
                        <div class="owner-actions">
                            <a class="btn ghost" href="{{ $manageUrl }}">Manage this club</a>
                        </div>
                        <div class="owner-embed">
                            <x-embed-snippet :club="$organisation->slug" />
                        </div>
                    </x-owner-panel>
                @elseif ($organisation->claimed_by === null)
                    <x-owner-panel title="For club administrators">
                        <p>Recognise this club? Claim it to update the listing and publish its matches.</p>
                        <div class="owner-actions">
                            <a class="btn ghost" href="{{ route('listings.claim', ['type' => 'club', 'slug' => $organisation->slug]) }}">This is my club</a>
                        </div>
                    </x-owner-panel>
                @endif
            </div>
        </section>
    </main>
</x-layouts.public>
