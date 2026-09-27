<x-layouts.public :title="$discipline->name.' rankings'" :description="'Results recorded on ShootingSports for '.$discipline->name.'. Not a federation or SAPRF classification.'">
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label"><a href="{{ route('disciplines.show', $discipline) }}">{{ $discipline->name }}</a></p>
                <h1>Rankings {{ $year }}</h1>
                <p>From results recorded on this site. This is not a SAPRF or federation classification.</p>
            </div>
        </section>
        <section class="block">
            <div class="wrap">
                @if ($rows->isEmpty())
                    <p class="empty">No linked results for {{ $year }} yet.</p>
                @else
                    <table>
                        <thead>
                            <tr>
                                <th>Shooter</th>
                                <th>Wins</th>
                                <th>Podiums</th>
                                <th>Starts</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    <td><a href="{{ route('shooters.show', $row->user->calendar_slug) }}">{{ $row->user->name }}</a></td>
                                    <td>{{ $row->wins }}</td>
                                    <td>{{ $row->podiums }}</td>
                                    <td>{{ $row->starts }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </section>
    </main>
</x-layouts.public>
