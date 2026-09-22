@php $group = null; @endphp
@foreach ($visible as $item)
    @if ($group !== $item['group'])
        @php $group = $item['group']; @endphp
        <div class="grp">{{ $group }}</div>
    @endif
    <a href="{{ $mk($item['route'], ['role' => $role]) }}" @class(['on' => $active === $item['key']])>{{ $item['label'] }}</a>
@endforeach
