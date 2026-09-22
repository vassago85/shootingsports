@if ($sponsors->isNotEmpty())
    <p class="app-kicker">Sponsored</p>
    <p class="app-fine">Supplier ads. They do not change the match order.</p>
    @foreach ($sponsors->take(2) as $sponsor)
        @include('mockups.partials.app-sponsor')
    @endforeach
@endif
