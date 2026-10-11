@extends('layouts.app')

@section('title', 'Tag settings')

@section('content')
    @php
        $field = fn (string $key, array $extra = []) => ['field' => $fields[$key], 'canManage' => $canManage] + $extra;
    @endphp
    @include('masters.partials.head', [
        'title' => 'Barcode tag settings',
        'intro' => 'Which Zebra printer prints the jewellery tags at the counter, and what the QR code holds.',
        'actions' => [
            ['url' => route('labels.test'), 'label' => 'Print a test tag', 'icon' => 'upc-scan', 'primary' => true],
        ],
    ])
    @unless ($canManage)
        <div class="alert alert-info">You can see these settings. Only an owner or manager can change them.</div>
    @endunless
    @error('settings')
        <div class="alert alert-danger">{{ $message }}</div>
    @enderror

    <form method="POST" action="{{ route('labels.settings.update') }}">
        @csrf
        <div class="row g-3 align-items-start">
            <div class="col-xl-8">
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="weigh-section-title"><i class="bi bi-printer"></i> Printer</div>
                        <div class="row g-3">
                            @include('foundation.settings.field', $field('label.printer_name', ['maxlength' => 120]))
                            @include('foundation.settings.field', $field('label.max_copies', ['inputmode' => 'numeric']))
                            @include('foundation.settings.field', $field('label.barcode_payload'))
                            @include('foundation.settings.field', $field('label.shift_mm', ['inputmode' => 'decimal']))
                            @include('foundation.settings.field', $field('label.down_mm', ['inputmode' => 'decimal']))
                        </div>
                    </div>
                </div>
                @if ($canManage)
                    <button class="btn btn-primary" type="submit"><i class="bi bi-check2"></i> Save tag settings</button>
                @endif
            </div>
            <div class="col-xl-4">
                <div class="card">
                    <div class="card-body small">
                        <div class="weigh-section-title"><i class="bi bi-info-circle"></i> The tag</div>
                        <p>Fixed 56 × 13 mm folding tag: weights on the first flap, the fold kept blank, then piece type, shop name and QR code on the second flap.</p>
                        <ol class="ps-3 mb-0">
                            <li>Print a test tag.</li>
                            <li>If the print starts too far into the tag (towards the tail), raise "Move print to the tag start". If it starts before the tag, lower it. 1 means 1 mm.</li>
                            <li>If text is cut at the top on some tags, raise "Move print down" by 0.5. If it is cut on every tag in a different place, calibrate the printer: hold its feed button until the light flashes twice, then let go.</li>
                            <li>Check nothing is printed on the fold or the tail, then scan the code. It should type TEST-0001.</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection
