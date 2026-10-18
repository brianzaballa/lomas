<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Daily Collection Report · {{ $label }}</title>
    <style>
        @page { size: A4 portrait; margin: 10mm 10mm 12mm 10mm; }

        * { box-sizing: border-box; }

        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 9px;
            color: #111827;
            margin: 0;
        }

        .muted { color: #6b7280; }
        .num { text-align: right; font-variant-numeric: tabular-nums; }
        .nowrap { white-space: nowrap; }

        table { border-collapse: collapse; width: 100%; }

        .head { width: 100%; }
        .head td { vertical-align: top; }
        .biz-name { font-size: 15px; font-weight: bold; letter-spacing: 0.2px; }
        .biz-line { font-size: 7.5px; color: #6b7280; }
        .doc-title { font-size: 14px; font-weight: bold; text-align: right; letter-spacing: 0.5px; }
        .doc-sub { font-size: 7.5px; color: #6b7280; text-align: right; }

        .rule { border-top: 1.4px solid #111827; margin: 5px 0 6px; }

        .period {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: 6px;
        }
        .period .date { font-size: 12px; font-weight: bold; }
        .period .day { font-size: 9px; color: #6b7280; }

        .headline {
            display: flex;
            gap: 0;
            margin-bottom: 8px;
        }
        .headline .box {
            flex: 1;
            border: 0.6px solid #9ca3af;
            background: #f9fafb;
            padding: 5px 7px;
        }
        .headline .box + .box { border-left: none; }
        .headline .label {
            font-size: 6.5px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #6b7280;
        }
        .headline .value { font-size: 15px; font-weight: bold; }

        .section-title {
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #374151;
            margin: 0 0 3px;
        }

        th {
            font-size: 7px;
            text-transform: uppercase;
            letter-spacing: 0.2px;
            color: #374151;
            background: #f3f4f6;
            border-top: 0.6px solid #9ca3af;
            border-bottom: 0.6px solid #9ca3af;
            padding: 3px 6px;
            text-align: left;
        }
        td {
            font-size: 8px;
            padding: 3px 6px;
            border-bottom: 0.4px solid #e5e7eb;
        }
        tr.total td {
            font-weight: bold;
            border-top: 0.6px solid #9ca3af;
            border-bottom: none;
            background: #f9fafb;
        }

        .area-group {
            margin-bottom: 8px;
            page-break-inside: avoid;
        }

        .area-head {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            background: #eef2ff;
            border-top: 0.6px solid #c7d2fe;
            border-bottom: 0.6px solid #c7d2fe;
            padding: 3px 6px;
        }
        .area-head .name {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .area-head .meta { font-size: 7.5px; color: #4b5563; }

        .area-total td {
            font-weight: bold;
            background: #f9fafb;
            border-top: 0.6px solid #9ca3af;
            border-bottom: none;
        }

        .two-col {
            display: flex;
            gap: 10px;
            margin-top: 8px;
        }
        .two-col > div { flex: 1; }

        .sign {
            margin-top: 22px;
            display: flex;
            gap: 24px;
        }
        .sign .line {
            flex: 1;
            border-top: 0.6px solid #111827;
            padding-top: 3px;
            font-size: 7px;
            color: #6b7280;
            text-align: center;
        }

        .foot { font-size: 6.5px; color: #9ca3af; margin-top: 8px; }
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
                <div class="doc-title">Daily Collection Report</div>
                <div class="doc-sub">Generated {{ $generated_at }}</div>
            </td>
        </tr>
    </table>

    <div class="rule"></div>

    <div class="period">
        <span class="date">{{ $label }}</span>
        <span class="day">{{ $weekday }}</span>
    </div>

    <div class="headline">
        <div class="box">
            <div class="label">Total collected</div>
            <div class="value">{{ $total }}</div>
        </div>
        <div class="box">
            <div class="label">Payments</div>
            <div class="value">{{ $total_count }}</div>
        </div>
        <div class="box">
            <div class="label">Areas</div>
            <div class="value">{{ count($areas) }}</div>
        </div>
    </div>

    <div class="section-title">Payments by area</div>

    @forelse ($areas as $area)
        <div class="area-group">
            <div class="area-head">
                <span class="name">{{ $area['area'] }}</span>
                <span class="meta">{{ $area['count'] }} {{ $area['count'] === 1 ? 'payment' : 'payments' }} · {{ $area['total'] }}</span>
            </div>

            <table>
                <thead>
                    <tr>
                        <th style="width: 13%;">Payment #</th>
                        <th style="width: 16%;">Loan #</th>
                        <th style="width: 27%;">Client</th>
                        <th style="width: 12%;">Method</th>
                        <th style="width: 15%;">Reference</th>
                        <th class="num" style="width: 17%;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($area['payments'] as $payment)
                        <tr>
                            <td>{{ $payment['payment_number'] }}</td>
                            <td>{{ $payment['loan_number'] }}</td>
                            <td>{{ $payment['borrower_name'] }}</td>
                            <td>{{ $payment['payment_method_label'] }}</td>
                            <td>{{ $payment['reference_number'] ?? '—' }}</td>
                            <td class="num">{{ $payment['amount'] }}</td>
                        </tr>
                    @endforeach
                    <tr class="area-total">
                        <td colspan="5">{{ $area['area'] }} subtotal</td>
                        <td class="num">{{ $area['total'] }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    @empty
        <table>
            <tbody>
                <tr>
                    <td class="muted">No payments were posted on this date.</td>
                </tr>
            </tbody>
        </table>
    @endforelse

    <table>
        <tbody>
            <tr class="total">
                <td>Grand total</td>
                <td class="num" style="width: 24%;">{{ $total_count }} {{ $total_count === 1 ? 'payment' : 'payments' }}</td>
                <td class="num" style="width: 30%;">{{ $total }}</td>
            </tr>
        </tbody>
    </table>

    <div class="two-col">
        <div>
            <div class="section-title">Collection by payment method</div>
            <table>
                <thead>
                    <tr>
                        <th style="width: 50%;">Method</th>
                        <th class="num" style="width: 20%;">Payments</th>
                        <th class="num" style="width: 30%;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($methods as $method)
                        <tr>
                            <td>{{ $method['label'] }}</td>
                            <td class="num">{{ $method['count'] }}</td>
                            <td class="num">{{ $method['total'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="muted">No payments.</td>
                        </tr>
                    @endforelse
                    <tr class="total">
                        <td>Total</td>
                        <td class="num">{{ $total_count }}</td>
                        <td class="num">{{ $total }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="sign">
        <div class="line">Prepared by</div>
        <div class="line">Verified by</div>
        <div class="line">Received by</div>
    </div>

    <div class="foot">
        {{ $business['name'] }} · Daily Collection Report · {{ $label }} · grouped by area ·
        {{ $total_count }} {{ $total_count === 1 ? 'payment' : 'payments' }} · all amounts in
        {{ $currency }}
    </div>

    <script type="text/php">
        if (isset($pdf)) {
            $pdf->page_text(505, 828, "Page {PAGE_NUM} of {PAGE_COUNT}", 'helvetica', 6.5, [0.6, 0.6, 0.6]);
        }
    </script>
</body>
</html>
