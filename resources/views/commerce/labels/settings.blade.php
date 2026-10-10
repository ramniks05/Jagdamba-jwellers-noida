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
                        <p>Fixed 56 × 13 mm folding tag: weights on the left 20 mm, a 4 mm fold kept blank, then piece type, shop name and QR code on the right 32 mm.</p>
                        <ol class="ps-3 mb-0">
                            <li>Print a test tag and fold it.</li>
                            <li>Check nothing is printed on the fold and nothing is cut at the edges.</li>
                            <li>Scan the code. It should type TEST-0001.</li>
                            <li>If the fold or edges are off, the layout is adjusted in the code, not here.</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection
