<div style="display:grid;gap:.75rem">
    <p style="margin:0;font-size:.875rem;opacity:.7">
        Captain {{ $entry->captain_name }} · {{ $entry->captain_email }} · {{ $entry->captain_phone }}
    </p>
    <table style="width:100%;border-collapse:collapse;font-size:.875rem">
        <thead>
            <tr style="text-align:left;border-bottom:1px solid rgba(127,127,127,.3)">
                <th style="padding:.4rem .5rem">#</th>
                <th style="padding:.4rem .5rem">Name</th>
                <th style="padding:.4rem .5rem">ID number</th>
                <th style="padding:.4rem .5rem">Contact number</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($entry->members as $index => $member)
                <tr style="border-bottom:1px solid rgba(127,127,127,.15)">
                    <td style="padding:.4rem .5rem">{{ $index + 1 }}</td>
                    <td style="padding:.4rem .5rem">{{ $member['name'] }}</td>
                    <td style="padding:.4rem .5rem">{{ $member['id_number'] }}</td>
                    <td style="padding:.4rem .5rem">{{ $member['phone'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
