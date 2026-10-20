<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Dedicated-status activity record — {{ $user->name }}</title>
    <style>
        /* Deliberately self-contained. This page must print cleanly
           from any browser without our normal app stylesheet — it is
           the artefact a shooter hands to an accrediting body or
           attaches to a firearm licence application. */
        * { box-sizing: border-box; }
        html, body {
            margin: 0; padding: 0;
            font-family: Georgia, 'Times New Roman', serif;
            color: #111; background: #fff;
            font-size: 12px; line-height: 1.45;
        }
        .sheet { max-width: 190mm; margin: 0 auto; padding: 20mm 15mm; }
        header { border-bottom: 2px solid #111; padding-bottom: 12px; margin-bottom: 18px; }
        header .eyebrow {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            font-size: 10px; letter-spacing: .18em; text-transform: uppercase;
            color: #666; margin: 0 0 4px;
        }
        header h1 { font-size: 22px; margin: 0 0 4px; }
        header .lede { margin: 0; font-size: 12px; color: #444; }

        .meta {
            display: grid; grid-template-columns: 1fr 1fr; gap: 8px 24px;
            padding: 14px 0; border-bottom: 1px solid #ccc;
            margin-bottom: 18px;
        }
        .meta div span {
            display: block; font-size: 10px; letter-spacing: .14em;
            text-transform: uppercase; color: #666; margin-bottom: 2px;
        }
        .meta div b { font-size: 13px; font-weight: 600; }

        .total {
            padding: 10px 14px;
            background: #faf6ea;
            border: 1px solid #d9cfa6;
            margin: 0 0 14px;
            font-weight: 600; font-size: 13px;
        }
        .total.short { background: #fdeeee; border-color: #e2b5b5; }

        table { width: 100%; border-collapse: collapse; margin: 4px 0 16px; }
        th, td {
            border-bottom: 1px solid #ccc;
            padding: 7px 8px; text-align: left; vertical-align: top;
            font-size: 11.5px;
        }
        th {
            background: #efefe8;
            font-family: 'Helvetica Neue', Arial, sans-serif;
            font-size: 10px; letter-spacing: .08em;
            text-transform: uppercase; color: #333;
            border-bottom: 1px solid #999;
        }
        tbody tr:nth-child(even) td { background: #fafaf7; }
        td.date { white-space: nowrap; }

        .empty {
            padding: 24px; text-align: center;
            color: #666; font-style: italic;
            border: 1px dashed #ccc;
        }

        .sig {
            display: grid; grid-template-columns: 1fr 1fr; gap: 32px;
            margin-top: 36px;
        }
        .sig > div {
            border-top: 1px solid #111;
            padding-top: 6px;
            font-size: 11px; color: #444;
            font-family: 'Helvetica Neue', Arial, sans-serif;
        }

        footer {
            margin-top: 24px; padding-top: 12px;
            border-top: 1px solid #ccc;
            font-size: 10.5px; color: #555; line-height: 1.5;
            font-family: 'Helvetica Neue', Arial, sans-serif;
        }
        footer strong { color: #111; }

        .toolbar {
            padding: 10px 0; margin: 0 0 18px;
            display: flex; gap: 8px; flex-wrap: wrap;
            font-family: 'Helvetica Neue', Arial, sans-serif;
        }
        .toolbar a, .toolbar button {
            padding: 8px 14px; font-size: 12px;
            background: #111; color: #fff;
            border: 1px solid #111; border-radius: 4px;
            text-decoration: none; cursor: pointer;
        }
        .toolbar a.ghost, .toolbar button.ghost {
            background: #fff; color: #111;
        }

        @media print {
            .toolbar { display: none !important; }
            .sheet { padding: 15mm 12mm; }
            tbody tr:nth-child(even) td { background: #fafaf7 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            th { background: #efefe8 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .total { background: #faf6ea !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .total.short { background: #fdeeee !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="toolbar">
            <button type="button" onclick="window.print()">Print / Save as PDF</button>
            <a class="ghost" href="{{ route('my-log') }}">Back to log</a>
            <a class="ghost" href="{{ route('my-log.export.dedicated') }}">Download CSV</a>
        </div>

        <header>
            <p class="eyebrow">Dedicated-status activity record</p>
            <h1>{{ $user->name }}</h1>
            <p class="lede">
                {{ $progress['body_label'] }} ·
                {{ $windowStart->format('j F Y') }} to {{ $windowEnd->format('j F Y') }}
                @if ($customRange) (custom range) @endif
            </p>
        </header>

        <div class="meta">
            <div><span>Accrediting body</span><b>{{ $progress['body_label'] }}</b></div>
            <div><span>Category</span><b>{{ $categoryLabel }}</b></div>
            <div><span>Membership number</span><b>{{ $user->dedicated_membership_number ?: '—' }}</b></div>
            <div><span>Dedicated-status number</span><b>{{ $user->dedicated_status_number ?: '—' }}</b></div>
            <div><span>Cycle start</span><b>{{ $windowStart->format('j F Y') }}</b></div>
            <div><span>Cycle end (renewal)</span><b>{{ $windowEnd->format('j F Y') }}</b></div>
        </div>

        <div class="total {{ $progress['logged'] >= $progress['required'] ? '' : 'short' }}">
            {{ $progress['logged'] }} of {{ $progress['required'] }} required activities logged in this period
            @if ($progress['logged'] >= $progress['required'])
                — requirement met.
            @else
                — {{ $progress['remaining'] }} more needed.
            @endif
        </div>

        @if ($entries->isEmpty())
            <div class="empty">
                No activity-counting matches logged in this period.
            </div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Match</th>
                        <th>Discipline</th>
                        <th>Host club</th>
                        <th>Venue</th>
                        <th>Division</th>
                        <th>Placing</th>
                        <th>Score</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($entries as $entry)
                        <tr>
                            <td class="date">{{ $entry->event_date?->format('Y-m-d') }}</td>
                            <td>{{ $entry->event_name_snapshot }}</td>
                            <td>{{ $entry->discipline_name_snapshot }}</td>
                            <td>{{ $entry->host_snapshot }}</td>
                            <td>{{ $entry->venue_snapshot }}</td>
                            <td>{{ $entry->division }}</td>
                            <td>{{ $entry->placing }}</td>
                            <td>{{ $entry->score }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <div class="sig">
            <div>Shooter signature &amp; date</div>
            <div>Association endorsement, stamp &amp; date</div>
        </div>

        <footer>
            Generated from shootingsports.co.za on {{ $generatedAt->format('j F Y, H:i') }} (SAST).
            Match records are self-logged by the account holder unless marked as verified by the host club.
            This document is provided as a convenience; the accrediting body and the SAPS Designated Firearm Officer
            remain the authority on what counts as supporting evidence for a firearm licence application.
            <br><br>
            <strong>DRAFT:</strong> Please verify the above wording with NRAPA and your SAPS DFO before relying on it as supporting evidence.
        </footer>
    </div>
</body>
</html>
