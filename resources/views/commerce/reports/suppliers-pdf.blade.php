@php
    $amount = fn ($value) => number_format((float) $value, 2, '.', '');
    $owed = fn ($value) => (float) $value < -0.004 ? $money(ltrim($amount($value), '-')).' advance' : $money($amount($value));
    $date = fn (string $day) => \Illuminate\Support\Carbon::parse($day)->format('d-m-Y');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Supplier passbook</title>
    <style>
        @page { margin: 28pt 30pt 40pt 30pt; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.5pt; color: #2a1f1a; }
        h1 { font-size: 15pt; margin: 0 0 2pt; color: #7b2330; }
        .kicker { font-size: 7.5pt; letter-spacing: 1pt; text-transform: uppercase; color: #8a6d3b; }
        .muted { color: #6b5f58; }
        .head { width: 100%; border-bottom: 1.5pt solid #b8903d; padding-bottom: 6pt; margin-bottom: 8pt; }
        .head td { vertical-align: top; }
        .right { text-align: right; }
        .sums { width: 100%; border-collapse: separate; border-spacing: 4pt 0; margin: 0 -4pt 8pt; }
        .sums td { border: 0.5pt solid #d9ccb8; background: #faf6ee; padding: 5pt 6pt; width: 20%; }
        .sums .label { font-size: 7pt; color: #6b5f58; }
        .sums .value { font-size: 10pt; font-weight: bold; }
        table.rows { width: 100%; border-collapse: collapse; }
        table.rows th { text-align: left; font-size: 7.5pt; text-transform: uppercase; color: #7b2330; border-bottom: 1pt solid #b8903d; padding: 4pt 4pt; }
        table.rows td { border-bottom: 0.5pt solid #e6dccb; padding: 4pt 4pt; vertical-align: top; }
        table.rows .num { text-align: right; white-space: nowrap; }
        table.rows tr { page-break-inside: avoid; }
        table.rows tfoot td { border-top: 1pt solid #b8903d; border-bottom: 0; font-weight: bold; }
        .small { font-size: 7.5pt; color: #6b5f58; }
        .bf td { color: #6b5f58; }
        .strong { font-weight: bold; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td>
                <div class="kicker">Supplier passbook</div>
                <h1>{{ $company->displayName() }}</h1>
                <div class="muted">
                    @if ($supplier)
                        {{ $supplier->name }} ({{ $supplier->code }})
                        @php($contact = collect([$supplier->mobile, $supplier->gstin ? 'GSTIN '.$supplier->gstin : null])->filter()->join(' · '))
                        @if ($contact !== '')
                            <br>{{ $contact }}
                        @endif
                    @else
                        All suppliers
                    @endif
                </div>
            </td>
            <td class="right">
                <div class="strong">{{ $date($filters['from']) }} to {{ $date($filters['to']) }}</div>
                <div class="small">Printed {{ now()->timezone(config('app.timezone'))->format('d-m-Y h:i A') }}</div>
                <div class="strong" style="margin-top: 4pt;">We owe {{ $owed($sums['closing']) }}</div>
            </td>
        </tr>
    </table>

    <table class="sums">
        <tr>
            <td><div class="label">We owed on {{ $date($filters['from']) }}</div><div class="value">{{ $owed($sums['opening']) }}</div></td>
            <td><div class="label">Purchased</div><div class="value">{{ $money($amount($sums['purchased'])) }}</div></td>
            <td><div class="label">Paid</div><div class="value">{{ $money($amount($sums['paid'])) }}</div></td>
            <td><div class="label">Sent back</div><div class="value">{{ $money($amount($sums['returned'])) }}</div></td>
            <td><div class="label">We owe on {{ $date($filters['to']) }}</div><div class="value">{{ $owed($sums['closing']) }}</div></td>
        </tr>
    </table>

    @if ($supplier)
        <table class="rows">
            <thead>
                <tr>
                    <th style="width: 13%;">Date</th>
                    <th>Details</th>
                    <th class="num" style="width: 15%;">Purchased</th>
                    <th class="num" style="width: 13%;">Sent back</th>
                    <th class="num" style="width: 15%;">Paid</th>
                    <th class="num" style="width: 16%;">We owe</th>
                </tr>
            </thead>
            <tbody>
                <tr class="bf">
                    <td>{{ $date($filters['from']) }}</td>
                    <td>Balance brought forward</td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td class="num strong">{{ $owed($sums['opening']) }}</td>
                </tr>
                @forelse ($passbook as $row)
                    <tr>
                        <td>{{ $date($row['date']) }}</td>
                        <td>
                            <span class="strong">{{ $row['title'] }}</span>
                            @if ($row['detail'])
                                <br><span class="small">{{ $row['detail'] }}</span>
                            @endif
                        </td>
                        <td class="num">{{ $row['purchased'] !== null ? $money($amount($row['purchased'])) : '' }}</td>
                        <td class="num">{{ $row['returned'] !== null ? $money($amount($row['returned'])) : '' }}</td>
                        <td class="num">{{ $row['paid'] !== null ? $money($amount($row['paid'])) : '' }}</td>
                        <td class="num strong">{{ $owed($row['balance']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6">Nothing bought or paid between these dates.</td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2">Total for these dates</td>
                    <td class="num">{{ $money($amount($sums['purchased'])) }}</td>
                    <td class="num">{{ $money($amount($sums['returned'])) }}</td>
                    <td class="num">{{ $money($amount($sums['paid'])) }}</td>
                    <td class="num">{{ $owed($sums['closing']) }}</td>
                </tr>
            </tfoot>
        </table>
    @else
        <table class="rows">
            <thead>
                <tr>
                    <th>Supplier</th>
                    <th class="num" style="width: 15%;">We owed on {{ $date($filters['from']) }}</th>
                    <th class="num" style="width: 14%;">Purchased</th>
                    <th class="num" style="width: 12%;">Sent back</th>
                    <th class="num" style="width: 14%;">Paid</th>
                    <th class="num" style="width: 15%;">We owe on {{ $date($filters['to']) }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td><span class="strong">{{ $row->name }}</span><br><span class="small">{{ $row->code }}</span></td>
                        <td class="num">{{ $owed($row->opening) }}</td>
                        <td class="num">{{ $money($amount($row->purchased)) }}</td>
                        <td class="num">{{ $money($amount($row->returned)) }}</td>
                        <td class="num">{{ $money($amount($row->paid)) }}</td>
                        <td class="num strong">{{ $owed($row->closing) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6">No supplier has anything bought, paid or owed in these dates.</td></tr>
                @endforelse
            </tbody>
            @if ($supplierCount > 0)
                <tfoot>
                    <tr>
                        <td>Total · {{ $supplierCount }} {{ $supplierCount === 1 ? 'supplier' : 'suppliers' }}</td>
                        <td class="num">{{ $owed($sums['opening']) }}</td>
                        <td class="num">{{ $money($amount($sums['purchased'])) }}</td>
                        <td class="num">{{ $money($amount($sums['returned'])) }}</td>
                        <td class="num">{{ $money($amount($sums['paid'])) }}</td>
                        <td class="num">{{ $owed($sums['closing']) }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    @endif
</body>
</html>
