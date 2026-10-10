@extends('layouts.app')

@section('title', $item ? 'Tag '.$item->item_code : 'Test tag')

@section('content')
    @php
        $scale = $preview ? min(3, 620 / max(1, $preview['width'])) : 1;
        $zplUrl = $item ? route('items.label.zpl', $item) : route('labels.test.zpl');
    @endphp

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        @if ($item)
            <a href="{{ route('items.show', $item) }}"><i class="bi bi-arrow-left"></i> {{ $item->item_code }}</a>
        @else
            <a href="{{ route('items.index') }}"><i class="bi bi-arrow-left"></i> All pieces</a>
        @endif
        <div class="d-flex flex-wrap gap-2">
            @if ($item)
                <a class="btn btn-outline-secondary" href="{{ route('labels.test') }}"><i class="bi bi-upc-scan"></i> Test tag</a>
            @endif
            <a class="btn btn-outline-secondary" href="{{ route('labels.settings.edit') }}"><i class="bi bi-sliders"></i> Tag settings</a>
        </div>
    </div>

    <h1 class="page-title h3 mb-1">{{ $item ? 'Print tag for '.$item->item_code : 'Print a test tag' }}</h1>
    <p class="text-secondary mb-3">
        @if ($item)
            Prints straight to the tag printer on this computer. Printing does not change stock.
        @else
            A sample tag to check size and position. It is not linked to any piece.
        @endif
    </p>

    <div class="row g-3 align-items-start">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-body">
                    <div class="weigh-section-title"><i class="bi bi-eye"></i> Tag preview</div>
                    @if ($problem)
                        <div class="alert alert-danger mb-0" role="alert">{{ $problem }}</div>
                    @else
                        <p class="small text-secondary">Close to how the tag will print. The real print uses the printer's own font, so check the first tag on paper.</p>
                        <div class="tag-preview-wrap">
                            <div class="tag-preview" style="width: {{ round($preview['width'] * $scale) }}px; height: {{ round($preview['height'] * $scale) }}px;">
                                <div class="tag-preview-fold" style="left: {{ round($preview['fold'][0] * $scale) }}px; width: {{ round(($preview['fold'][1] - $preview['fold'][0] + 1) * $scale) }}px;" title="Fold, not printed"><span>fold</span></div>
                                <img class="tag-preview-code" src="{{ $previewQr }}" alt="QR code holding {{ $preview['payload'] }}" style="left: {{ round($preview['code']['x'] * $scale) }}px; top: {{ round($preview['code']['y'] * $scale) }}px; width: {{ round($preview['code']['size'] * $scale) }}px; height: {{ round($preview['code']['size'] * $scale) }}px;">
                                @foreach ($preview['texts'] as $text)
                                    <div class="tag-preview-text" style="left: {{ round($text['x'] * $scale) }}px; top: {{ round($text['y'] * $scale) }}px; width: {{ round($text['width'] * $scale) }}px; height: {{ round($text['height'] * $scale) }}px; font-size: {{ round($text['height'] * $scale, 1) }}px;@if ($text['block'] !== null) display: flex; justify-content: center;@endif">
                                        <span style="transform: scaleX({{ round($text['font_width'] / $text['height'] * 0.88, 3) }}); transform-origin: {{ $text['block'] !== null ? 'center' : 'left' }};">{{ $text['text'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <dl class="row small mt-3 mb-0">
                            <dt class="col-sm-4">Code holds</dt>
                            <dd class="col-sm-8 font-monospace">{{ $preview['payload'] }}</dd>
                            <dt class="col-sm-4">Tag size</dt>
                            <dd class="col-sm-8">56 × 13 mm at 203 dpi: weights on the first 20 mm flap, the fold (not printed), then shop name and QR code on the second 20 mm flap. Initial sizes, not yet measured on a real tag.</dd>
                            <dt class="col-sm-4">Code type</dt>
                            <dd class="col-sm-8">QR code</dd>
                        </dl>
                        @foreach ($preview['warnings'] as $warning)
                            <div class="alert alert-warning small mt-3 mb-0">{{ $warning }}</div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card">
                <div class="card-body">
                    <div class="weigh-section-title"><i class="bi bi-printer"></i> Print</div>
                    <p class="small mb-2">Printer: <strong>{{ $printerName }}</strong></p>
                    <div class="small mb-3" id="tag-printer-status" role="status" aria-live="polite">
                        <span class="text-secondary">Checking QZ Tray on this computer…</span>
                    </div>
                    <label class="form-label" for="tag-copies">Copies</label>
                    <input class="form-control mb-1" id="tag-copies" type="number" min="1" max="{{ $maxCopies }}" step="1" value="1" inputmode="numeric" @disabled($problem)>
                    <div class="form-text mb-3">Up to {{ $maxCopies }} at a time.</div>
                    <button class="btn btn-primary w-100" type="button" id="tag-print" @disabled($problem)><i class="bi bi-printer"></i> {{ $item ? 'Print tag' : 'Print test tag' }}</button>
                    <div class="small mt-3" id="tag-print-result" role="alert" aria-live="assertive"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('vendor/qz-tray/qz-tray.js') }}?v={{ filemtime(public_path('vendor/qz-tray/qz-tray.js')) }}"></script>
    <script src="{{ asset('js/jewellery-label-printer.js') }}?v={{ filemtime(public_path('js/jewellery-label-printer.js')) }}"></script>
    <script>
        (function () {
            const printer = window.JewelleryLabelPrinter;
            const status = document.getElementById('tag-printer-status');
            const result = document.getElementById('tag-print-result');
            const button = document.getElementById('tag-print');
            const copies = document.getElementById('tag-copies');
            const maxCopies = {{ $maxCopies }};
            const zplUrl = @json($zplUrl);

            const show = function (element, tone, text) {
                element.replaceChildren();
                const line = document.createElement('span');
                line.className = 'text-' + tone;
                line.textContent = text;
                element.appendChild(line);
            };

            if (!printer) {
                show(status, 'danger', 'The printing helper did not load. Refresh the page.');
                button.disabled = true;
                return;
            }

            printer.configure({
                printerName: @json($printerName),
                certificateUrl: @json(route('labels.qz.certificate')),
                signUrl: @json(route('labels.qz.sign')),
                csrf: @json(csrf_token()),
            });

            printer.checkPrinter()
                .then(function (name) { show(status, 'success', 'Ready. QZ Tray found ' + name + '.'); })
                .catch(function (error) { show(status, 'danger', error.message); });

            button.addEventListener('click', function () {
                const count = Number(copies.value);

                if (!Number.isInteger(count) || count < 1 || count > maxCopies) {
                    show(result, 'danger', 'Copies must be a whole number from 1 to ' + maxCopies + '.');
                    copies.focus();
                    return;
                }

                if (count >= 10 && !window.confirm('Print ' + count + ' tags?')) {
                    return;
                }

                button.disabled = true;
                show(result, 'secondary', 'Sending to the printer…');

                printer.printUrl(zplUrl + '?copies=' + count)
                    .then(function (name) {
                        show(status, 'success', 'Ready. QZ Tray found ' + name + '.');
                        show(result, 'success', (count === 1 ? 'Tag sent' : count + ' tags sent') + ' to ' + name + '.');
                    })
                    .catch(function (error) { show(result, 'danger', error.message); })
                    .finally(function () { button.disabled = false; });
            });
        })();
    </script>
@endpush
