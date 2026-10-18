<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Notes Collection Report · {{ $label }}</title>
    <style>
        @page { size: A4 landscape; margin: 8mm 8mm 10mm 8mm; }

        * { box-sizing: border-box; }

        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 7.5px;
            color: #111827;
            margin: 0;
        }

        .muted { color: #6b7280; }
        .num { text-align: right; font-variant-numeric: tabular-nums; }
        .nowrap { white-space: nowrap; }

        table { border-collapse: collapse; width: 100%; }

        .head { width: 100%; }
        .head td { vertical-align: top; }
        .biz-name { font-size: 13px; font-weight: bold; letter-spacing: 0.2px; }
        .biz-line { font-size: 7px; color: #6b7280; }
        .doc-title { font-size: 12px; font-weight: bold; text-align: right; letter-spacing: 0.5px; }
        .doc-sub { font-size: 7px; color: #6b7280; text-align: right; }

        .stamp {
            display: inline-block;
            padding: 1px 5px;
            font-size: 7px;
            font-weight: bold;
            letter-spacing: 0.6px;
            color: #166534;
            border: 0.8px solid #166534;
            border-radius: 2px;
        }

        .rule { border-top: 1.2px solid #111827; margin: 4px 0 5px; }

        .period { font-size: 10px; font-weight: bold; margin-bottom: 4px; }
        .period .meta { font-size: 7px; font-weight: normal; color: #6b7280; }

        .ledger thead th {
            font-size: 6.5px;
            text-transform: uppercase;
            letter-spacing: 0.2px;
            color: #374151;
            background: #f3f4f6;
            border-top: 0.5px solid #9ca3af;
            border-bottom: 0.5px solid #9ca3af;
            padding: 2.5px 4px;
            text-align: left;
        }

        .ledger thead th.num { text-align: right; }

        .ledger td {
            font-size: 7px;
            padding: 1.8px 4px;
            border-bottom: 0.4px solid #e5e7eb;
            vertical-align: top;
        }

        .ledger tbody tr.alt > td {
            background: #f3f4f6;
        }

        .member .name { font-weight: bold; }
        .member .sub { font-size: 6px; color: #6b7280; margin-left: 3px; white-space: nowrap; }

        .area-row td {
            font-size: 7.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.35px;
            background: #eef2ff;
            border-top: 0.5px solid #c7d2fe;
            border-bottom: 0.5px solid #c7d2fe;
            padding: 2.2px 4px;
        }

        .ledger tr.subtotal td {
            background: #f9fafb;
            border-top: 0.5px solid #d1d5db;
            font-weight: bold;
        }

        .ledger tfoot td {
            font-weight: bold;
            border-top: 0.8px solid #111827;
            background: #f3f4f6;
            padding: 2.6px 4px;
        }

        .owed { color: #b91c1c; }
        .due { color: #b45309; }
        .zero { color: #9ca3af; }

        .foot { font-size: 6.5px; color: #9ca3af; margin-top: 6px; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td>
                <div class="biz-name">{{ $business['name'] }}</div>
                @if ($business['address'])
                    <div class="biz-line">{{ $business['address'] }}</div>
                @endif
                @php($contact = array_filter([$business['contact_phone'], $business['contact_email']]))
                @if ($contact)
                    <div class="biz-line">{{ implode(' · ', $contact) }}</div>
                @endif
            </td>
            <td>
                <div class="doc-title">Notes Collection Report</div>
                <div class="doc-sub">Generated {{ $generated_at }}</div>
                @if (($posting['status'] ?? null) === 'posted')
                    <div class="doc-sub" style="margin-top: 2px;"><span class="stamp">POSTED</span></div>
                @endif
            </td>
        </tr>
    </table>

    <div class="rule"></div>

    <div class="period">
        Period {{ $label }}
        <span class="meta">
            · {{ $grand['count'] }} {{ $grand['count'] === 1 ? 'member' : 'members' }}
            · collected {{ $grand['total_collected'] }}
            @if ($period['is_current'])
                · collectibles {{ $grand['total_collectibles'] }}
            @endif
            · overdue {{ $grand['total_overdue'] }}
            · all amounts in {{ $currency }}
        </span>
    </div>

    @php($span = count($day_headers) + ($period['is_current'] ? 4 : 3))

    <table class="ledger">
        <colgroup>
            <col style="width: 30%;">
            @foreach ($day_headers as $day)
                <col style="width: {{ 50 / max(count($day_headers), 1) }}%;">
            @endforeach
            <col style="width: 10%;">
            @if ($period['is_current'])
                <col style="width: 10%;">
            @endif
            <col style="width: 10%;">
        </colgroup>
        <thead>
            <tr>
                <th>Member</th>
                @foreach ($day_headers as $day)
                    <th class="num">{{ $day['short'] }}<br>{{ \Illuminate\Support\Carbon::parse($day['date'])->format('n/j') }}</th>
                @endforeach
                <th class="num">Total</th>
                @if ($period['is_current'])
                    <th class="num">Collectible</th>
                @endif
                <th class="num">Overdue</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($areas as $area)
                <tr class="area-row">
                    <td colspan="{{ $span }}">{{ $area['area'] }} · {{ $area['count'] }} {{ $area['count'] === 1 ? 'member' : 'members' }}</td>
                </tr>
                @foreach ($area['members'] as $member)
                    <tr class="member-row {{ $loop->even ? 'alt' : '' }}">
                        <td class="member">
                            <span class="name">{{ $member['borrower_name'] }}</span>
                            <span class="sub">{{ $member['borrower_number'] }} · {{ $member['loan_number'] }}</span>
                        </td>
                        @foreach ($day_headers as $day)
                            <td class="num">{{ $member['days'][$day['date']] }}</td>
                        @endforeach
                        <td class="num">{{ $member['total_collected'] }}</td>
                        @if ($period['is_current'])
                            <td class="num {{ $member['has_collectibles'] ? 'due' : 'zero' }}">{{ $member['collectibles'] ?? '' }}</td>
                        @endif
                        <td class="num {{ $member['is_overdue'] ? 'owed' : 'zero' }}">{{ $member['overdue'] }}</td>
                    </tr>
                @endforeach
                <tr class="subtotal">
                    <td class="member">{{ $area['area'] }} subtotal</td>
                    <td colspan="{{ count($day_headers) }}"></td>
                    <td class="num">{{ $area['subtotal_collected'] }}</td>
                    @if ($period['is_current'])
                        <td class="num {{ $area['has_collectibles'] ? 'due' : 'zero' }}">{{ $area['subtotal_collectibles'] ?? '' }}</td>
                    @endif
                    <td class="num {{ $area['has_overdue'] ? 'owed' : 'zero' }}">{{ $area['subtotal_overdue'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $span }}" class="muted">No members were expected to pay in this period.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td>Grand total</td>
                <td colspan="{{ count($day_headers) }}"></td>
                <td class="num">{{ $grand['total_collected'] }}</td>
                @if ($period['is_current'])
                    <td class="num {{ $grand['has_collectibles'] ? 'due' : 'zero' }}">{{ $grand['total_collectibles'] ?? '' }}</td>
                @endif
                <td class="num {{ $grand['has_overdue'] ? 'owed' : 'zero' }}">{{ $grand['total_overdue'] }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="foot">
        {{ $business['name'] }} · Notes Collection Report · {{ $label }} · grouped by area, one row per member ·
        @if ($period['is_current'])
            overdue is everything due before the period started still unpaid; collectibles is what is due inside the period and still unpaid
        @else
            overdue is everything due on or before {{ \Illuminate\Support\Carbon::parse($week_end)->format('M j, Y') }} still unpaid
        @endif
    </div>

    <script type="text/php">
        if (isset($pdf)) {
            $pdf->page_text(772, 560, "Page {PAGE_NUM} of {PAGE_COUNT}", 'helvetica', 6.5, [0.6, 0.6, 0.6]);
        }
    </script>
</body>
</html>
