@extends('layouts.app')

@section('title', 'Settings')

@section('content')
    @php
        $field = fn (string $key, array $extra = []) => ['field' => $fields[$key], 'canManage' => $canManage] + $extra;
    @endphp
    @include('masters.partials.head', [
        'title' => 'Settings',
        'intro' => 'How bills are worked out and printed, and how amounts, weights and dates look across the shop.',
        'actions' => [
            ['url' => route('company.edit'), 'label' => 'Shop profile', 'icon' => 'shop'],
            ['url' => route('document-sequences.index'), 'label' => 'Document numbers', 'icon' => 'hash'],
        ],
    ])
    @unless ($canManage)
        <div class="alert alert-info">You can see these settings. Only an owner or manager can change them.</div>
    @endunless

    <form method="POST" action="{{ route('settings.update') }}" id="settings-form">
        @csrf
        @method('PUT')
        <div class="row g-3 align-items-start">
            <div class="col-xl-8">
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="weigh-section-title"><i class="bi bi-receipt"></i> Billing and GST</div>
                        <div class="row g-3">
                            @include('foundation.settings.field', $field('pricing.gst_percent', ['col' => 'col-sm-4', 'suffix' => '%', 'inputmode' => 'decimal', 'label' => 'Jewellery GST', 'help' => 'Usually 3 for gold jewellery. Each bill keeps the percent it used.']))
                            @include('foundation.settings.field', $field('invoice.tax_display', ['col' => 'col-sm-8', 'label' => 'Prices you enter', 'help' => 'Exclusive adds GST on top. Inclusive means the worked amount already has GST inside.']))
                            @include('foundation.settings.field', $field('pricing.making_mode', ['col' => 'col-sm-8', 'help' => 'The default for new bills. It can be changed on each bill.']))
                            @include('foundation.settings.field', $field('pricing.making_gst_percent', ['col' => 'col-sm-4', 'suffix' => '%', 'inputmode' => 'decimal', 'label' => 'Making GST', 'help' => 'Only when making has its own GST.']))
                            @include('foundation.settings.field', $field('pricing.round_rupee', ['col' => 'col-12']))
                        </div>
                        <div class="settings-example mt-3" id="gst-example" aria-live="polite"></div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-body">
                        <div class="weigh-section-title"><i class="bi bi-printer"></i> Printed bill</div>
                        <div class="row g-3">
                            @include('foundation.settings.field', $field('invoice.paper_size', ['col' => 'col-sm-6']))
                            @include('foundation.settings.field', $field('invoice.show_logo', ['col' => 'col-sm-6', 'label' => 'Show the shop logo']))
                            @include('foundation.settings.field', $field('invoice.terms', ['col' => 'col-md-7', 'label' => 'Terms printed on the bill', 'rows' => 4]))
                            @include('foundation.settings.field', $field('invoice.footer_note', ['col' => 'col-md-5', 'label' => 'Footer line', 'rows' => 4]))
                        </div>
                        <div class="form-text mt-2">The logo, signature, GSTIN and address come from the <a href="{{ route('company.edit') }}">shop profile</a>.</div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-body">
                        <div class="weigh-section-title"><i class="bi bi-currency-rupee"></i> Money</div>
                        <div class="row g-3">
                            @include('foundation.settings.field', $field('currency.code', ['col' => 'col-6 col-md-3', 'label' => 'Currency code', 'maxlength' => 3]))
                            @include('foundation.settings.field', $field('currency.symbol', ['col' => 'col-6 col-md-3', 'label' => 'Symbol', 'maxlength' => 8]))
                            @include('foundation.settings.field', $field('currency.symbol_position', ['col' => 'col-6 col-md-3', 'label' => 'Symbol goes']))
                            @include('foundation.settings.field', $field('currency.decimal_places', ['col' => 'col-6 col-md-3', 'label' => 'Paise digits', 'inputmode' => 'numeric']))
                            @include('foundation.settings.field', $field('currency.thousand_separator', ['col' => 'col-6 col-md-3', 'label' => 'Thousands mark', 'maxlength' => 1]))
                            @include('foundation.settings.field', $field('currency.decimal_separator', ['col' => 'col-6 col-md-3', 'label' => 'Decimal mark', 'maxlength' => 1]))
                        </div>

                        <div class="weigh-section-title mt-4"><i class="bi bi-123"></i> Numbers and weights</div>
                        <div class="row g-3">
                            @include('foundation.settings.field', $field('number.grouping', ['col' => 'col-md-6']))
                            @include('foundation.settings.field', $field('number.weight_decimal_places', ['col' => 'col-6 col-md-3', 'label' => 'Weight digits', 'inputmode' => 'numeric', 'help' => '3 shows 12.346 g']))
                            @include('foundation.settings.field', $field('number.decimal_places', ['col' => 'col-6 col-md-3', 'label' => 'Other number digits', 'inputmode' => 'numeric']))
                            @include('foundation.settings.field', $field('number.thousand_separator', ['col' => 'col-6 col-md-3', 'label' => 'Number thousands mark', 'maxlength' => 1]))
                            @include('foundation.settings.field', $field('number.decimal_separator', ['col' => 'col-6 col-md-3', 'label' => 'Number decimal mark', 'maxlength' => 1]))
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-body">
                        <div class="weigh-section-title"><i class="bi bi-calendar3"></i> Date and time</div>
                        <div class="row g-3">
                            @include('foundation.settings.field', $field('datetime.timezone', ['col' => 'col-md-6', 'list' => 'timezone-list', 'help' => 'India is Asia/Kolkata.']))
                            @include('foundation.settings.field', $field('datetime.date_format', ['col' => 'col-6 col-md-3']))
                            @include('foundation.settings.field', $field('datetime.time_format', ['col' => 'col-6 col-md-3']))
                        </div>
                        <datalist id="timezone-list">
                            @foreach ($timezones as $zone)
                                <option value="{{ $zone }}">
                            @endforeach
                        </datalist>
                    </div>
                </div>
            </div>

            <div class="col-xl-4 bill-side">
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="weigh-section-title"><i class="bi bi-eye"></i> How it looks</div>
                        <div class="bill-sums">
                            <div class="bill-row"><span>Amount</span><strong class="num" id="preview-money">{{ $currencyPreview }}</strong></div>
                            <div class="bill-row"><span>Weight</span><strong class="num" id="preview-weight">{{ $weightPreview }}</strong></div>
                            <div class="bill-row"><span>Date and time</span><strong class="num" id="preview-date">{{ $datePreview }}</strong></div>
                        </div>
                        <div class="form-text mt-2">Changes show here as you type. They apply after you save.</div>
                    </div>
                </div>
                @if ($canManage)
                    <div class="card">
                        <div class="card-body d-grid">
                            <button class="btn btn-primary btn-lg" type="submit"><i class="bi bi-check2"></i> Save settings</button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        (() => {
            const form = document.getElementById('settings-form');
            const input = (key) => form.querySelector(`[data-setting="${key}"] [name$="[value]"]:not([type="hidden"])`);
            const value = (key) => {
                const el = input(key);
                if (!el) return '';
                return el.type === 'checkbox' ? el.checked : el.value;
            };
            const digits = (key, fallback) => {
                const n = parseInt(value(key), 10);
                return Number.isFinite(n) && n >= 0 && n <= 6 ? n : fallback;
            };

            const group = (whole, mark, indian) => {
                if (!mark) return whole;
                if (!indian || whole.length <= 3) return whole.replace(/\B(?=(\d{3})+(?!\d))/g, mark);
                const last = whole.slice(-3);
                const rest = whole.slice(0, -3).replace(/\B(?=(\d{2})+(?!\d))/g, mark);
                return rest + mark + last;
            };
            const number = (amount, places, thousands, decimal) => {
                const [whole, fraction] = Math.abs(amount).toFixed(places).split('.');
                const text = group(whole, thousands, value('number.grouping') === 'indian');
                return (amount < 0 ? '-' : '') + (fraction ? text + decimal + fraction : text);
            };
            const money = (amount) => {
                const text = number(Math.abs(amount), digits('currency.decimal_places', 2), value('currency.thousand_separator'), value('currency.decimal_separator') || '.');
                const symbol = value('currency.symbol');
                const shown = !symbol ? text : (value('currency.symbol_position') === 'after' ? `${text} ${symbol}` : `${symbol} ${text}`);
                return (amount < 0 ? '-' : '') + shown;
            };

            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            const pad = (n) => String(n).padStart(2, '0');
            const formatDate = (format, d) => format.replace(/[dmYMHhiA]/g, (token) => ({
                d: pad(d.getDate()),
                m: pad(d.getMonth() + 1),
                Y: d.getFullYear(),
                M: months[d.getMonth()],
                H: pad(d.getHours()),
                h: pad(d.getHours() % 12 || 12),
                i: pad(d.getMinutes()),
                A: d.getHours() < 12 ? 'AM' : 'PM',
            })[token]);

            const gstExample = () => {
                const gst = parseFloat(value('pricing.gst_percent')) || 0;
                const inclusive = value('invoice.tax_display') === 'inclusive';
                const worked = 100000;
                const taxable = inclusive ? worked / (1 + gst / 100) : worked;
                const tax = inclusive ? worked - taxable : worked * gst / 100;
                let total = taxable + tax;
                if (value('pricing.round_rupee')) total = Math.round(total);
                const making = value('pricing.making_mode');
                const makingNote = {
                    inside: 'Making is added into the jewellery amount and gets the same GST.',
                    separate: `Making is shown on its own line with ${parseFloat(value('pricing.making_gst_percent')) || 0}% GST.`,
                    processing: 'Making is shown as a processing charge with no GST.',
                }[making] || '';
                document.getElementById('gst-example').innerHTML =
                    `<div class="stat-label mb-1">Example: a piece worked out at ${money(worked)}</div>`
                    + `<div class="bill-row"><span>Taxable value</span><span class="num">${money(taxable)}</span></div>`
                    + `<div class="bill-row"><span>GST ${gst}%</span><span class="num">${money(tax)}</span></div>`
                    + `<div class="bill-row bill-grand"><span>Customer pays</span><strong class="num">${money(total)}</strong></div>`
                    + `<div class="small text-secondary mt-1">${makingNote}</div>`;
            };

            const refresh = () => {
                document.getElementById('preview-money').textContent = money(1234567.5);
                document.getElementById('preview-weight').textContent = number(12.346, digits('number.weight_decimal_places', 3), value('number.thousand_separator'), value('number.decimal_separator') || '.') + ' g';
                document.getElementById('preview-date').textContent = formatDate(value('datetime.date_format'), new Date()) + ' ' + formatDate(value('datetime.time_format'), new Date());
                const makingGst = form.querySelector('[data-setting="pricing.making_gst_percent"]');
                makingGst.classList.toggle('opacity-50', value('pricing.making_mode') !== 'separate');
                gstExample();
            };

            form.addEventListener('input', refresh);
            form.addEventListener('change', refresh);
            refresh();
        })();
    </script>
@endpush
