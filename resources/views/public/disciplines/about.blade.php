<x-layouts.public
    :title="$seo->title"
    :description="$seo->description"
    :canonical="$seo->canonical"
    :json-ld="$jsonLd"
>
    <main id="main" class="sport-page">
        <section @class(['dhero', 'has-photo' => filled($discipline->imageUrl())]) data-family="{{ $discipline->family->value }}" @if ($discipline->imageUrl()) style="--hero-image: url('{{ str_replace(['\\', "'"], ['/', ''], $discipline->imageUrl()) }}')" @endif>
            <div class="dhero-in">
                <p class="crumb">
                    <a href="{{ route('disciplines.index') }}">Disciplines</a>
                    &nbsp;/&nbsp;
                    <a href="{{ route('disciplines.show', $discipline->slug) }}">{{ $discipline->name }}</a>
                    &nbsp;/&nbsp; <b>More information</b>
                </p>
                <h1>About {{ $discipline->name }}</h1>
                @if (filled($discipline->short_blurb))
                    <p class="lede">{{ $discipline->short_blurb }}</p>
                @endif
            </div>
        </section>

        <div class="wrap">
            <div class="cols">
                <div class="body">
                    @php
                        $paragraphs = array_values(array_filter(array_map(
                            trim(...),
                            preg_split('/\n\s*\n/', (string) $discipline->body) ?: [],
                        )));
                    @endphp
                    @if ($paragraphs !== [] || $discipline->typical_distances || $discipline->equipment_rules)
                    <section id="about">
                        <div class="sec-head">
                            <p class="label">The sport</p>
                            <h2>More information</h2>
                        </div>
                        @if ($paragraphs !== [])
                            <div class="prose">
                                @foreach ($paragraphs as $paragraph)
                                    <p>{{ $paragraph }}</p>
                                @endforeach
                            </div>
                        @endif
                        @if ($discipline->typical_distances || $discipline->equipment_rules)
                            <dl class="fmt" style="margin-top:22px">
                                @if ($discipline->typical_distances)
                                    <div><dt>Typical distance</dt><dd>{{ $discipline->typical_distances }}</dd></div>
                                @endif
                                @if ($discipline->equipment_rules)
                                    <div><dt>Equipment</dt><dd>{{ $discipline->equipment_rules }}</dd></div>
                                @endif
                            </dl>
                        @endif
                    </section>
                    @endif

                    @if ($discipline->federation)
                        @php
                            $organisationName = $discipline->federation->short_name ?: $discipline->federation->name;
                            $organisationUrl = $discipline->federation->website_url;
                        @endphp
                        <hr class="rule-off">
                        <section id="federation">
                            <div class="sec-head">
                                <p class="label">South Africa</p>
                                <h2>Federation</h2>
                            </div>
                            <p>
                                @if (filled($organisationUrl))
                                    <a href="{{ $organisationUrl }}" target="_blank" rel="noopener noreferrer">{{ $organisationName }}</a>
                                @else
                                    <a href="{{ route('federations.show', $discipline->federation->slug) }}">{{ $organisationName }}</a>
                                @endif
                                @if ($discipline->federation->short_name && $discipline->federation->short_name !== $discipline->federation->name)
                                    — {{ $discipline->federation->name }}
                                @endif
                            </p>
                        </section>
                    @endif

                    @if ($discipline->children->isNotEmpty())
                        <hr class="rule-off">
                        <section id="formats">
                            <div class="sec-head">
                                <p class="label">Formats</p>
                                <h2>Ways this sport is shot</h2>
                            </div>
                            <div class="formats">
                                @foreach ($discipline->children as $child)
                                    <div class="fmt">
                                        <div class="fmt-top">
                                            <h3>{{ $child->name }}</h3>
                                            @if (filled($child->short_blurb))
                                                <span>{{ $child->short_blurb }}</span>
                                            @endif
                                        </div>
                                        @if ($child->typical_distances || $child->equipment_rules)
                                            <dl>
                                                @if ($child->typical_distances)
                                                    <div><dt>Distance</dt><dd>{{ $child->typical_distances }}</dd></div>
                                                @endif
                                                @if ($child->equipment_rules)
                                                    <div><dt>Equipment</dt><dd>{{ $child->equipment_rules }}</dd></div>
                                                @endif
                                            </dl>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endif

                    @if ($clubs->isNotEmpty())
                    <hr class="rule-off">

                    <section id="clubs">
                        <div class="sec-head">
                            <p class="label">Where it is shot</p>
                            <h2>Clubs &amp; series running {{ $discipline->name }}</h2>
                        </div>
                        <div class="clubs">
                            @foreach ($clubs as $club)
                                <article class="club-card">
                                    <div>
                                        <h3><a href="{{ route('clubs.show', $club->slug) }}">{{ $club->name }}</a></h3>
                                        @php $clubPlace = collect([$club->province?->getLabel(), $club->town])->filter()->implode(' · '); @endphp
                                        @if ($clubPlace !== '')
                                            <p class="where">{{ $clubPlace }}</p>
                                        @endif
                                    </div>
                                    <div>
                                        <x-verification-badge :listing="$club" />
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </section>
                    @endif
                </div>

                <aside>
                    <div class="side-box">
                        <h4>By province</h4>
                        <ul>
                            @foreach (\App\Enums\Province::cases() as $item)
                                <li>
                                    <a href="{{ route('clubs.landing', [$item->urlSlug(), $discipline->slug]) }}">{{ $item->getLabel() }}</a>
                                    <span>{{ $item->code() }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </aside>
            </div>
        </div>
    </main>
</x-layouts.public>
