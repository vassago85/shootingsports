@php $asOrganiser = $asOrganiser ?? false; @endphp
@if ($asOrganiser)
    <x-mockups.layout title="List an event" active="matches">
        <div class="wrap" style="padding-bottom:80px">
            @include('mockups.partials.match-editor', ['match' => $match, 'sports' => $sports, 'ranges' => $ranges, 'clubs' => $clubs, 'asOrganiser' => true])
        </div>
    </x-mockups.layout>
@else
    <x-mockups.admin-layout title="Edit match" active="matches">
        @include('mockups.partials.match-editor', ['match' => $match, 'sports' => $sports, 'ranges' => $ranges, 'clubs' => $clubs, 'asOrganiser' => false])
    </x-mockups.admin-layout>
@endif
