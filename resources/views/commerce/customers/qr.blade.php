@extends('layouts.app')

@section('title', 'Customer QR')

@section('content')
    <div class="d-flex justify-content-between align-items-start mb-4 no-print">
        <div>
            <h1 class="page-title h3 mb-1">Customer form</h1>
            <div class="text-secondary">Customers scan this code and fill billing details on their phone.</div>
        </div>
        <button class="btn btn-outline-secondary" type="button" onclick="window.print()">Print</button>
    </div>
    <div class="card">
        <div class="card-body text-center">
            <div class="fw-semibold mb-3">{{ $company->displayName() }}</div>
            <img src="{{ $qr }}" alt="QR code for the customer form" width="280" height="280">
            <div class="small text-secondary mt-3">{{ $url }}</div>
        </div>
    </div>
@endsection
