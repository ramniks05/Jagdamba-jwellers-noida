<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Bill {{ $sale->number }} — {{ $company->displayName() }}</title>
    <link rel="icon" href="{{ asset('favicon.png') }}" type="image/png">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ @filemtime(public_path('css/app.css')) }}">
</head>
<body class="public-bill">
    <div class="public-bill-bar no-print">
        <div>
            <strong>{{ $company->displayName() }}</strong>
            <div class="small text-secondary">Bill {{ $sale->number }} · {{ $sale->sold_at->timezone(config('app.timezone'))->format('d-m-Y') }}</div>
        </div>
        <button class="btn btn-primary" type="button" onclick="window.print()"><i class="bi bi-download"></i> Download PDF</button>
    </div>
    <p class="public-bill-hint no-print">To keep a copy, tap <strong>Download PDF</strong> and choose <strong>Save as PDF</strong>.</p>
    <main class="public-bill-sheet">
        @include('commerce.sales.partials.invoice')
    </main>
</body>
</html>
