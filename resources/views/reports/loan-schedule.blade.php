<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} · {{ $loan['number'] }}</title>
    <style>
        @page { size: A4 portrait; margin: 9mm 9mm 11mm 9mm; }

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

        /* Header */
        .head { width: 100%; }
        .head td { vertical-align: top; }
        .biz-name { font-size: 13px; font-weight: bold; letter-spacing: 0.2px; }
        .biz-line { font-size: 7px; color: #6b7280; }
        .doc-title { font-size: 12px; font-weight: bold; text-align: right; letter-spacing: 0.5px; }
        .doc-sub { font-size: 7px; color: #6b7280; text-align: right; }

        .rule { border-top: 1.2px solid #111827; margin: 4px 0 5px; }

        /* Key-value bars */
        .meta { margin-bottom: 4px; }
        .meta td { padding: 2px 6px 2px 0; vertical-align: top; }
        .meta .label { font-size: 6.5px; text-transform: uppercase; color: #6b7280; letter-spacing: 0.3px; }
        .meta .value { font-size: 8.5px; font-weight: bold; }

        .amounts td {
            border: 0.5px solid #d1d5db;
            padding: 3px 6px;
            vertical-align: bottom;
            width: 14.28%;
        }
        .amounts .label { font-size: 6.5px; text-transform: uppercase; color: #6b7280; }
        .amounts .value { font-size: 9.5px; font-weight: bold; }

        .note { font-size: 6.5px; color: #6b7280; margin: 3px 0 6px; }

        /* Schedule */
        .cols { width: 100%; }
        .cols > tbody > tr > td { vertical-align: top; padding-right: 5px; }
        .cols > tbody > tr > td:last-child { padding-right: 0; }

        .sched { width: 100%; table-layout: fixed; }
        .sched th {
            font-size: 7px;
            text-transform: uppercase;
            letter-spacing: 0.2px;
            color: #374151;
            background: #f3f4f6;
            border-top: 0.5px solid #9ca3af;
            border-bottom: 0.5px solid #9ca3af;
            padding: 2.5px 3px;
            text-align: left;
        }
        .sched td {
            font-size: 7.5px;
            padding: 2.3px 3px;
            border-bottom: 0.4px solid #e5e7eb;
            white-space: nowrap;
            overflow: hidden;
        }
        .sched tr.total td {
            font-weight: bold;
            border-top: 0.6px solid #9ca3af;
            border-bottom: none;
        }
        .sched td.paid { color: #047857; }
        .sched td.partial { color: #b45309; }

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
                <div class="doc-title">{{ $title }}</div>
                <div class="doc-sub">Generated {{ $generated_at }}</div>
            </td>
        </tr>
    </table>

    <div class="rule"></div>

    <table class="meta">
        <tr>
            <td>
                <div class="label">Loan No.</div>
                <div class="value">{{ $loan['number'] }}</div>
            </td>
            <td>
                <div class="label">Borrower</div>
                <div class="value">{{ $loan['borrower_name'] }}</div>
                <div class="label">{{ $loan['borrower_number'] }}</div>
            </td>
            <td>
                <div class="label">Product</div>
                <div class="value">{{ $loan['product_name'] }}</div>
                <div class="label">{{ $loan['product_code'] }}</div>
            </td>
            <td>
                <div class="label">Release date</div>
                <div class="value">{{ $loan['release_date'] }}</div>
            </td>
            <td>
                <div class="label">Status</div>
                <div class="value">{{ $loan['status'] }}</div>
            </td>
        </tr>
    </table>

    <table class="amounts">
        <tr>
            <td>
                <div class="label">Gross loan</div>
                <div class="value num">{{ $summary['gross_loan_amount'] }}</div>
            </td>
            <td>
                <div class="label">Interest</div>
                <div class="value num">{{ $summary['interest_amount'] }}</div>
            </td>
            <td>
                <div class="label">Deductions</div>
                <div class="value num">{{ $summary['deductions_amount'] }}</div>
            </td>
            <td>
                <div class="label">Net proceeds</div>
                <div class="value num">{{ $summary['net_proceeds_amount'] }}</div>
            </td>
            <td>
                <div class="label">Total payable</div>
                <div class="value num">{{ $summary['total_amount_payable'] }}</div>
            </td>
            <td>
                <div class="label">Total paid</div>
                <div class="value num">{{ $summary['total_amount_paid'] }}</div>
            </td>
            <td>
                <div class="label">Outstanding</div>
                <div class="value num">{{ $summary['outstanding_balance'] }}</div>
            </td>
        </tr>
    </table>

    <div class="note">
        All amounts are in {{ $currency }}. {{ count($installments) }} collection
        {{ count($installments) === 1 ? 'day' : 'days' }}, read oldest first down each column.
    </div>

    <table class="cols">
        <tr>
            @foreach ($columns as $column)
                <td style="width: {{ round(100 / max(1, count($columns)), 4) }}%;">
                    <table class="sched">
                        <thead>
                            <tr>
                                <th style="width: 12%;">#</th>
                                <th style="width: {{ $has_interest ? '34%' : '36%' }};">Due date</th>
                                @if ($has_interest)
                                    <th class="num" style="width: 15%;">Prin.</th>
                                    <th class="num" style="width: 15%;">Int.</th>
                                @endif
                                <th class="num" style="width: {{ $has_interest ? '15%' : '18%' }};">Amount</th>
                                <th class="num" style="width: {{ $has_interest ? '14%' : '17%' }};">Paid</th>
                                <th class="num" style="width: {{ $has_interest ? '14%' : '17%' }};">Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($column as $row)
                                <tr>
                                    <td>{{ $row['number'] }}</td>
                                    <td>{{ $row['due_date'] }}</td>
                                    @if ($has_interest)
                                        <td class="num">{{ $row['principal'] }}</td>
                                        <td class="num">{{ $row['interest'] }}</td>
                                    @endif
                                    <td class="num">{{ $row['total'] }}</td>
                                    <td class="num {{ $row['status'] === 'Paid' ? 'paid' : ($row['status'] === 'Partial' ? 'partial' : '') }}">
                                        {{ $row['paid'] }}
                                    </td>
                                    <td class="num">{{ $row['balance'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </td>
            @endforeach
        </tr>
    </table>

    <div class="foot">
        {{ $business['name'] }} · Amortization schedule · {{ $loan['number'] }} ·
        {{ count($installments) }} installments
    </div>

    <script type="text/php">
        if (isset($pdf)) {
            $pdf->page_text(505, 828, "Page {PAGE_NUM} of {PAGE_COUNT}", 'helvetica', 6.5, [0.6, 0.6, 0.6]);
        }
    </script>
</body>
</html>
