<div>
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">Find your discipline</p>
                <h1>What should you try?</h1>
                <p>Six questions. Then nearby clubs and the next events.</p>
            </div>
        </section>
        <section class="block">
            <div class="wrap" style="max-width:720px">
                @if ($step < 7)
                    @php
                        $questions = [
                            1 => ['field' => 'firearm', 'prompt' => 'What firearm do you own?', 'options' => ['rifle' => 'Rifle', 'pistol' => 'Pistol', 'shotgun' => 'Shotgun', 'none' => 'None yet']],
                            2 => ['field' => 'pace', 'prompt' => 'Do you prefer speed or precision?', 'options' => ['speed' => 'Speed', 'precision' => 'Precision']],
                            3 => ['field' => 'setting', 'prompt' => 'Indoor or outdoor?', 'options' => ['indoor' => 'Indoor', 'outdoor' => 'Outdoor']],
                            4 => ['field' => 'distance', 'prompt' => 'How far do you want to shoot?', 'options' => ['up-close' => 'Up close', '100-300' => '100–300 m', 'past-300' => 'Past 300 m']],
                            5 => ['field' => 'company', 'prompt' => 'On your own, or with other people?', 'options' => ['individual' => 'On my own', 'together' => 'With other people']],
                            6 => ['field' => 'budget', 'prompt' => 'How much do you want to spend getting started?', 'options' => ['low' => 'Keep it cheap', 'medium' => 'A moderate start', 'high' => 'I can spend more']],
                        ];
                        $question = $questions[$step];
                    @endphp
                    <p class="label">Question {{ $step }} of 6</p>
                    <h2>{{ $question['prompt'] }}</h2>
                    <div style="display:grid;gap:10px;margin-top:18px">
                        @foreach ($question['options'] as $value => $label)
                            <button type="button" class="btn ghost" wire:click="choose('{{ $question['field'] }}', '{{ $value }}')">{{ $label }}</button>
                        @endforeach
                    </div>
                    @if ($step > 1)
                        <p style="margin-top:16px"><button type="button" class="btn ghost" wire:click="back">Back</button></p>
                    @endif
                @else
                    <h2>Start with these</h2>
                    @forelse ($sports as $sport)
                        <article class="club-card" style="margin-top:14px">
                            <h3><a href="{{ route('disciplines.show', $sport->slug) }}">{{ $sport->name }}</a></h3>
                            @if ($sport->short_blurb)
                                <p>{{ $sport->short_blurb }}</p>
                            @endif
                        </article>
                    @empty
                        <p class="empty">No published sports matched those answers yet.</p>
                    @endforelse

                    <label class="field" style="margin-top:22px">
                        <span>Province, if you want nearby clubs</span>
                        <select wire:model.live="province">
                            <option value="">Anywhere</option>
                            @foreach ($provinces as $item)
                                <option value="{{ $item->value }}">{{ $item->getLabel() }}</option>
                            @endforeach
                        </select>
                    </label>

                    @if ($clubs->isNotEmpty())
                        <h3 style="margin-top:22px">Clubs</h3>
                        <ul>
                            @foreach ($clubs as $club)
                                <li><a href="{{ route('clubs.show', $club) }}">{{ $club->name }}</a></li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($events->isNotEmpty())
                        <h3 style="margin-top:22px">Next events</h3>
                        <ul>
                            @foreach ($events as $event)
                                <li><a href="{{ route('events.show', $event->slug) }}">{{ $event->title }}</a></li>
                            @endforeach
                        </ul>
                    @endif

                    <p style="margin-top:18px"><button type="button" class="btn ghost" wire:click="back">Change an answer</button></p>
                @endif
            </div>
        </section>
    </main>
</div>
