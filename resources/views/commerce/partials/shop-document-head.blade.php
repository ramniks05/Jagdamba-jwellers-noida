<header class="invoice-head">
    <div class="invoice-brand">
        @if ($showLogo)
            <img class="invoice-logo" src="{{ $company->brandLogoUrl() }}" alt="{{ $company->displayName() }}">
        @endif
        <div>
            <div class="invoice-kicker">{{ $kicker }}</div>
            <h1>{{ $company->displayName() }}</h1>
            <p>{{ $company->formattedAddress() }}</p>
            @php($phones = collect([$company->phone, $company->mobile])->filter()->implode(' · '))
            @if ($phones !== '')
                <p>Phone {{ $phones }}@if ($company->email) · {{ $company->email }}@endif</p>
            @endif
        </div>
    </div>
    <div class="invoice-gstin">
        <span>GSTIN {{ $company->gstin ?: '—' }}</span>
        <span>PAN {{ $company->pan ?: '—' }}</span>
        <span>State {{ $company->state ?: '—' }}</span>
    </div>
</header>
