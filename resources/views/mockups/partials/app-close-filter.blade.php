@php
    $closeScreen = $closeScreen ?? 'today';
    $closeExtra = $closeExtra ?? [];
    $closeOn = ($close['on'] ?? false) === true;
    $closeKm = $close['km'] ?? 100;
    $closeProvince = $close['province'] ?? null;
    $closePlace = $close['place'] ?? null;
    $home = $close['home'] ?? null;
    $homePlace = $close['home_place'] ?? 'your account';
    $usingHome = ($close['using_home'] ?? false) === true;
    $anywhere = ($close['anywhere'] ?? false) === true;
    $lookAnywhere = ['province' => 'all', 'km' => null, 'near' => null, 'lat' => null, 'lng' => null];
    $backHome = ['province' => null, 'km' => null, 'near' => null, 'lat' => null, 'lng' => null];
@endphp
<div class="app-chips" role="group" aria-label="Location">
    <a href="{{ $app($closeScreen, array_merge($closeExtra, $backHome)) }}" @class(['on' => $usingHome])>{{ $homePlace }}</a>
    @foreach ($places ?? [] as $province)
        @if ($province->value !== $home)
            <a href="{{ $app($closeScreen, array_merge($closeExtra, ['province' => $province->value])) }}" @class(['on' => ! $usingHome && $closeProvince === $province->value])>{{ $province->getLabel() }}</a>
        @endif
    @endforeach
    <a href="{{ $app($closeScreen, array_merge($closeExtra, $lookAnywhere)) }}" @class(['on' => $anywhere])>Anywhere</a>
</div>
<div class="app-chips" role="group" aria-label="Distance">
    @if ($closeProvince !== null || $closeOn)
        <a href="{{ $app($closeScreen, array_merge($closeExtra, ['km' => null])) }}" @class(['on' => ! $closeOn])>Any distance</a>
        @foreach ([50, 100, 150] as $option)
            <a href="{{ $app($closeScreen, array_merge($closeExtra, ['km' => $option])) }}" @class(['on' => $closeOn && $closeKm === $option])>{{ $option }} km</a>
        @endforeach
    @endif
    <a href="{{ $app($closeScreen, array_merge($closeExtra, ['near' => 'denied'])) }}" @class(['on' => ($close['gps'] ?? false) === true]) data-app-near="{{ $app($closeScreen, array_merge($closeExtra, ['near' => '1', 'km' => $closeOn ? $closeKm : 100])) }}">Close to me</a>
</div>
@if (($close['gps'] ?? false) === true && $closeOn)
    <p class="app-banner">Within {{ $closeKm }} km. Used once. Nothing was saved.</p>
@elseif ($usingHome && $closeOn)
    <p class="app-banner">Within {{ $closeKm }} km of {{ $closePlace }}. From your account.</p>
@elseif ($usingHome)
    <p class="app-banner">{{ $closePlace }}, from your account. Distance is from the middle of the province.</p>
@elseif ($anywhere)
    <p class="app-banner">All of South Africa. Your account is still {{ $homePlace }}. <a href="{{ $app($closeScreen, array_merge($closeExtra, $backHome)) }}">Back to {{ $homePlace }}</a></p>
@elseif ($closePlace !== null)
    <p class="app-banner">{{ $closePlace }}{{ $closeOn ? ', within '.$closeKm.' km' : '' }}. Your account is still {{ $homePlace }}. <a href="{{ $app($closeScreen, array_merge($closeExtra, $backHome)) }}">Back to {{ $homePlace }}</a></p>
@elseif ($closeOn)
    <p class="app-banner">Within {{ $closeKm }} km. Used once. Nothing was saved.</p>
@endif
@if (($close['denied'] ?? false) === true && ! $usingHome && $closeProvince === null)
    <p class="app-banner">Location wasn't allowed. The list stays on your account province unless you choose Anywhere.</p>
@endif
