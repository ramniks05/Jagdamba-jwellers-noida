@extends('layouts.app')

@section('title', $exchange->number)

@section('content')
    <h1 class="page-title h3 mb-1">{{ $exchange->number }}</h1>
    <div class="text-secondary mb-4">{{ $exchange->customer?->name }} · {{ $exchange->metalType?->name }} {{ $exchange->purity?->name }}</div>
    <ul class="list-group">
        <li class="list-group-item d-flex justify-content-between"><span>Gross</span><span>{{ $weight((string) $exchange->gross_weight) }}</span></li>
        <li class="list-group-item d-flex justify-content-between"><span>Net</span><span>{{ $weight((string) $exchange->net_weight) }}</span></li>
        <li class="list-group-item d-flex justify-content-between"><span>After melting loss</span><span>{{ $weight((string) $exchange->melted_weight) }}</span></li>
        <li class="list-group-item d-flex justify-content-between"><span>Rate / g</span><span>{{ $money((string) $exchange->rate_per_gram) }}</span></li>
        <li class="list-group-item d-flex justify-content-between"><span>Deduction</span><span>{{ $money((string) $exchange->deduction_amount) }}</span></li>
        <li class="list-group-item d-flex justify-content-between fw-semibold"><span>Gold value</span><span>{{ $money((string) $exchange->exchange_value) }}</span></li>
        <li class="list-group-item d-flex justify-content-between"><span>Cash refund</span><span>{{ $money($refund) }}</span></li>
        <li class="list-group-item d-flex justify-content-between"><span>Kept on the account</span><span>{{ $money($kept) }}</span></li>
        <li class="list-group-item">{{ $exchange->testing_result ?: 'No testing note' }}</li>
    </ul>
@endsection
