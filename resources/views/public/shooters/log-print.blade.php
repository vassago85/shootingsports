<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Annual attendance record — {{ $user->name }} — {{ $year }}</title>
    <style>
        /* Deliberately self-contained. This page must print cleanly
           from any browser without our normal app stylesheet — it is
           the artefact a shooter hands to an accrediting body. */
        * { box-sizing: border-box; }
        html, body {
            margin: 0; padding: 0;
            font-family: Georgia, 'Times New Roman', serif;
            color: #111; background: #fff;
            font-size: 12px; line-height: 1.4;
        }
        .sheet {
            max-width: 190mm; margin: 0 auto; padding: 20mm 15mm;
        }
        header { border-bottom: 2px solid #111; padding-bottom: 12px; margin-bottom: 20px; }
        header h1 { font-size: 22px; margin: 0 0 4px; }
        header .meta { font-size: 12px; color: #444; }
        header .meta b { color: #111; }
        .summary {
            display: flex; flex-wrap: wrap; gap: 24px;
            padding: 12px 0; border-bottom: 1px solid #ccc;
            margin-bottom: 18px;
        }
        .summary div { min-width: 130px; }
        .summary .k {
            font-size: 10px; text-transform: uppercase; letter-spacing: .12em;
            color: #666; margin: 0 0 2px;
        }
        .summary .v { font-size: 18px; font-weight: bold; margin: 0; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 22px; }
        th, td {
            padding: 6px 8px; text-align: left;
            border-bottom: 1px solid #ddd; vertical-align: top;
        }
        th {
            font-size: 10px; text-transform: uppercase; letter-spacing: .1em;
            color: #666; font-weight: normal;
            border-bottom: 2px solid #111;
        }
        td.num { text-align: right; font-variant-numeric: tabular-nums; }
        tr { page-break-inside: avoid; }

        .signature {
            margin-top: 40px; padding-top: 14px; border-top: 1px solid #111;
        }
        .signature-line {
            display: inline-block; border-bottom: 1px solid #111;
            min-width: 70mm; height: 20px; margin: 0 8px;
        }
        .signature p { margin: 0 0 12px; }

        footer {
            margin-top: 24px; padding-top: 12px; border-top: 1px solid #ccc;
            font-size: 10px; color: #666;
        }

        .print-only { display: none; }
        .no-print {
            background: #fffbe6; border: 1px solid #d6a300; padding: 10px 14px;
            margin: 0 auto 20px; max-width: 190mm; font-size: 12px;
        }
        .no-print button {
            background: #111; color: #fff; border: 0; padding: 6px 14px;
            font-size: 12px; cursor: pointer; margin-left: 8px;
        }
        @media print {
            .no-print { display: none; }
            .print-only { display: block; }
            @page { size: A4; margin: 15mm; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <b>Save this as PDF:</b> press <kbd>Ctrl</kbd>+<kbd>P</kbd> (or <kbd>⌘</kbd>+<kbd>P</kbd> on a Mac), then choose "Save as PDF" as the printer.
        <button type="button" onclick="window.print()">Print now</button>
    </div>

    <main class="sheet">
        <header>
            <h1>Annual attendance record</h1>
            <p class="meta">
                <b>Shooter:</b> {{ $user->name }}<br>
                @if ($user->association_membership_number)
                    <b>Membership number:</b> {{ $user->association_membership_number }}<br>
                @endif
                <b>Year:</b> {{ $year }} (1 January – 31 December)<br>
                <b>Total matches:</b> {{ $entries->count() }}
            </p>
        </header>

        @if ($totals->isNotEmpty())
            <section class="summary">
                @foreach ($totals as $name => $count)
                    <div>
                        <p class="k">{{ $name }}</p>
                        <p class="v">{{ $count }}</p>
                    </div>
                @endforeach
            </section>
        @endif

        @if ($entries->isEmpty())
            <p><em>No matches logged for {{ $year }}.</em></p>
        @else
            <table>
                <thead>
                    <tr>
                        <th style="width:60px">Date</th>
                        <th>Match</th>
                        <th style="width:90px">Discipline</th>
                        <th style="width:100px">Host</th>
                        <th style="width:60px">Div</th>
                        <th style="width:44px" class="num">Place</th>
                        <th style="width:60px">Score</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($entries as $e)
                        <tr>
                            <td>{{ $e->event_date?->format('j M') }}</td>
                            <td>
                                {{ $e->event_name_snapshot }}
                                @if ($e->venue_snapshot)
                                    <br><small style="color:#666">{{ $e->venue_snapshot }}</small>
                                @endif
                            </td>
                            <td>{{ $e->discipline_name_snapshot ?? '—' }}</td>
                            <td>{{ $e->host_snapshot ?? '—' }}</td>
                            <td>
                                @if ($e->division || $e->classification)
                                    {{ $e->division }}@if ($e->division && $e->classification) · @endif{{ $e->classification }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="num">
                                @if ($e->placing)
                                    {{ $e->placing }}@if ($e->field_size)/{{ $e->field_size }}@endif
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $e->score ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <section class="signature">
            <p>I certify that the above record accurately reflects my shooting sport activity for the calendar year {{ $year }}.</p>
            <p style="margin-top:24px">
                Shooter's signature: <span class="signature-line"></span> &nbsp; Date: <span class="signature-line" style="min-width:35mm"></span>
            </p>
            <p style="margin-top:22px">Verified and counter-signed by (association / accrediting body):</p>
            <p style="margin-top:24px">
                Signature: <span class="signature-line"></span> &nbsp; Date: <span class="signature-line" style="min-width:35mm"></span>
            </p>
            <p style="margin-top:16px">
                Association name: <span class="signature-line" style="min-width:100mm"></span>
            </p>
        </section>

        <footer>
            Generated {{ $generatedAt->format('j F Y H:i') }} · shootingsports.co.za · Source data is the shooter's personal log.
        </footer>
    </main>
</body>
</html>
