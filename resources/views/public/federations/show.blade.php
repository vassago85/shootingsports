<x-layouts.public
    :title="$organisation->name"
    :description="$organisation->description ?: $organisation->name.' — national or provincial body.'"
    :json-ld="$jsonLd"
>
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">{{ $organisation->type->getLabel() }}</p>
                <h1>{{ $organisation->name }}</h1>
                <p>{{ $organisation->description }}</p>
                <x-verification-badge :listing="$organisation" />
            </div>
        </section>
        <section class="block">
            <div class="wrap">
                @if ($organisation->federatedDisciplines->isNotEmpty())
                    <div class="sec-head">
                        <p class="label">Governs</p>
                        <h2>Disciplines</h2>
                    </div>
                    <div class="disc-grid">
                        @foreach ($organisation->federatedDisciplines as $discipline)
                            <a class="disc" href="{{ route('disciplines.show', $discipline->slug) }}">
                                <span class="fam">{{ $discipline->family->getLabel() }}</span>
                                <span class="nm">{{ $discipline->name }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif

                @if ($organisation->children->isNotEmpty())
                    <div class="sec-head" style="margin-top:40px">
                        <p class="label">Affiliated</p>
                        <h2>Member bodies</h2>
                    </div>
                    @foreach ($organisation->children as $child)
                        <a class="listing" href="{{ $child->isFederationListing() ? route('federations.show', $child->slug) : route('clubs.show', $child->slug) }}">
                            <h3>{{ $child->name }}</h3>
                            <p class="meta">{{ $child->province?->getLabel() }}</p>
                        </a>
                    @endforeach
                @endif

                @if ($events->isNotEmpty())
                    <div class="sec-head" style="margin-top:40px">
                        <p class="label">Calendar</p>
                        <h2>Hosted matches</h2>
                    </div>
                    @foreach ($events as $event)
                        <x-event-card :event="$event" />
                    @endforeach
                @endif
            </div>
        </section>
    </main>
</x-layouts.public>
