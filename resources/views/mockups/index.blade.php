<x-mockups.layout title="Mockup index" active="home">
    <div class="wrap" style="padding-bottom:48px">
        <header class="mk-pagehead">
            <p class="label">Review</p>
            <h1>Redesign mockups</h1>
            <p class="mk-lede">A separate review surface for the ShootingSports redesign. Production pages are unchanged. Open each mockup on desktop, then use Mobile to check the narrow layout.</p>
        </header>

        @php
            $sample = $samples;
            $public = [
                ['Homepage', 'Find somewhere to shoot, with a match search and a short register overview.', 'mockups.home', []],
                ['Find a match', 'List of upcoming matches with filters, sort and badges.', 'mockups.matches', []],
                ['Calendar', 'Month calendar, with an agenda on small screens.', 'mockups.matches.calendar', []],
                ['Match map', 'Map of ranges with the matches at each pin.', 'mockups.matches.map', []],
                ['Match detail', 'One real upcoming match, with entry, venue and organiser.', 'mockups.match', $sample['match'] ? ['slug' => $sample['match']] : []],
                ['Shooting sports', 'Discipline directory.', 'mockups.sports', []],
                ['Sport detail', 'What the sport is, and where to shoot it.', 'mockups.sport', $sample['sport'] ? ['slug' => $sample['sport']] : []],
                ['Find a club', 'Club directory.', 'mockups.clubs', []],
                ['Club detail', 'What the club shoots and when.', 'mockups.club', $sample['club'] ? ['slug' => $sample['club']] : []],
                ['Find a range', 'Range directory, list or map.', 'mockups.ranges', []],
                ['Range detail', 'Distance, access, facilities and upcoming matches.', 'mockups.range', $sample['range'] ? ['slug' => $sample['range']] : []],
                ['Shooting industry', 'Suppliers, with sponsored listings labelled.', 'mockups.industry', []],
                ['Business profile', 'One industry listing.', 'mockups.business', $sample['business'] ? ['slug' => $sample['business']] : []],
                ['Search', 'Matches, sports, clubs, ranges and industry in one search.', 'mockups.search', []],
            ];
            $account = [
                ['My Shooting', 'Follows, coming up, and the account features already on the live site.', 'mockups.account', []],
                ['Following', 'Toggle sports, clubs and one province. Query params drive My Shooting.', 'mockups.account.following', []],
                ['Onboarding', 'Pick sports, a province and clubs, then build the feed.', 'mockups.onboarding', []],
            ];
            $manage = [
                ['Club desk', 'Overview a club committee sees. Read-only.', 'mockups.manage.index', []],
                ['Club matches', 'Matches organised by the club, with filters.', 'mockups.manage.matches', []],
                ['New match', 'The shared match editor, in organiser mode. Nothing saves.', 'mockups.manage.matches.new', []],
                ['Club profile', 'Editor with completeness. Public page stays in sync.', 'mockups.manage.profile', []],
            ];
            $visual = [
                ['Home', 'Forest-green header, register hero, divisions and coming up.', 'mockups.v2.home', []],
                ['Matches', 'Match list with the shared filter bar and compact rows.', 'mockups.v2.matches', []],
                ['Calendar', 'The same matches on the month calendar.', 'mockups.v2.matches.calendar', []],
                ['Map', 'Match map, with light or dark tiles.', 'mockups.v2.matches.map', []],
                ['Match', 'One real upcoming match.', 'mockups.v2.match', $sample['match'] ? ['slug' => $sample['match']] : []],
                ['Sports', 'Discipline directory.', 'mockups.v2.sports', []],
                ['Sport', 'One sport, activity first.', 'mockups.v2.sport', $sample['sport'] ? ['slug' => $sample['sport']] : []],
                ['Clubs', 'Club list with the register sidebar.', 'mockups.v2.clubs', []],
                ['Club', 'One club.', 'mockups.v2.club', $sample['club'] ? ['slug' => $sample['club']] : []],
                ['Ranges', 'Range list beside the map.', 'mockups.v2.ranges', []],
                ['Range', 'One range.', 'mockups.v2.range', $sample['range'] ? ['slug' => $sample['range']] : []],
                ['Industry', 'Suppliers, with sponsored listings still labelled.', 'mockups.v2.industry', []],
                ['Business', 'One industry listing.', 'mockups.v2.business', $sample['business'] ? ['slug' => $sample['business']] : []],
            ];
            $apps = [
                ['iOS and Android', 'Phone screens. Each sport has a packing list, and you add items when packing for a match.', 'mockups.apps', ['screen' => 'pack']],
            ];
            $admin = [
                ['Admin dashboard', 'What needs attention, upcoming activity, quick actions.', 'mockups.admin.dashboard', []],
                ['Match management', 'Operational table of matches.', 'mockups.admin.matches', []],
                ['Match editor', 'Sectioned editor with a completeness indicator. Nothing is saved.', 'mockups.admin.matches.edit', $sample['match'] ? ['slug' => $sample['match']] : []],
                ['Club management', 'Clubs, verification and upcoming matches.', 'mockups.admin.clubs', []],
                ['Range management', 'GPS, distance and completeness.', 'mockups.admin.ranges', []],
                ['Sports management', 'Which discipline pages are thin.', 'mockups.admin.sports', []],
                ['Industry management', 'Verification and featured status.', 'mockups.admin.industry', []],
                ['Submissions', 'Moderation queue. Empty until someone submits.', 'mockups.admin.submissions', []],
                ['Needs attention', 'Data-quality centre built from the live register.', 'mockups.admin.quality', []],
                ['Duplicates', 'Name and coordinate collisions, plus a labelled example card.', 'mockups.admin.duplicates', []],
                ['Reports', 'Matches by month, sport and province. No invented analytics.', 'mockups.admin.reports', []],
                ['Advertising', 'Placements, impressions and clicks. Sponsored stays labelled.', 'mockups.admin.advertising', []],
            ];
        @endphp

        @foreach (['Public' => $public, 'Public — visual V2' => $visual, 'Account' => $account, 'Club management' => $manage, 'Apps' => $apps, 'Admin' => $admin] as $heading => $items)
            <section class="mk-index-group">
                <h2>{{ $heading }}</h2>
                @foreach ($items as [$name, $description, $route, $params])
                    <div class="mk-index-item">
                        <strong>{{ $name }}</strong>
                        <span>{{ $description }}</span>
                        <span>
                            <a class="mk-textlink" href="{{ $mk($route, $params, false) }}">Desktop</a>
                            <a class="mk-textlink" href="{{ $mk($route, array_merge($params, ['device' => 'mobile']), false) }}">Mobile</a>
                        </span>
                    </div>
                @endforeach
            </section>
        @endforeach
    </div>
</x-mockups.layout>
