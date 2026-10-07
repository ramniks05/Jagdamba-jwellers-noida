@extends('layouts.guest')

@section('title', 'Billing details')
@section('guest_class', 'guest-card-wide')

@section('content')
    <div class="card">
        <div class="card-body p-4">
            <div class="mb-1" style="color: var(--maroon); font-weight: 700;">{{ $company->displayName() }}</div>
            <h1 class="h4 mb-2">Billing details</h1>
            <p class="text-secondary">Fill this once. The counter will use these details and will not ask for them again.</p>
            @if (session('form_result') === 'known')
                <div class="alert alert-success">This mobile number is already with the shop. You do not need to fill the form again.</div>
            @elseif (session('form_result') === 'waiting')
                <div class="alert alert-success">These details are already waiting for the shop to approve. Show your mobile at the counter.</div>
            @elseif (session('form_result') === 'submitted')
                <div class="alert alert-success">Details sent. The shop will add you as a customer after approval. Show your mobile at the counter.</div>
            @endif
            @include('partials.alerts')
            <div class="row g-4">
                <div class="col-md-5">
                    <h2 class="h6">Already a customer</h2>
                    <p class="small text-secondary">Enter the same mobile number the shop already has.</p>
                    <form method="POST" action="{{ route('customer-form.store', $company) }}">
                        @csrf
                        <input type="hidden" name="intent" value="known">
                        <label class="form-label" for="known-mobile">Mobile</label>
                        <input class="form-control mb-3" id="known-mobile" name="mobile" value="{{ old('intent') === 'known' ? old('mobile') : '' }}" inputmode="tel" required>
                        <button class="btn btn-outline-primary" type="submit">Check mobile</button>
                    </form>
                </div>
                <div class="col-md-7">
                    <h2 class="h6">New customer</h2>
                    <p class="small text-secondary">The shop reviews this and then adds you as a customer.</p>
                    <form method="POST" action="{{ route('customer-form.store', $company) }}">
                        @csrf
                        <input type="hidden" name="intent" value="new">
                        <div class="mb-2"><label class="form-label">Name</label><input class="form-control" name="name" value="{{ old('intent') === 'new' ? old('name') : '' }}" required></div>
                        <div class="mb-2"><label class="form-label">Mobile</label><input class="form-control" name="mobile" value="{{ old('intent') === 'new' ? old('mobile') : '' }}" inputmode="tel" required></div>
                        <div class="mb-2"><label class="form-label">Email</label><input class="form-control" name="email" type="email" value="{{ old('email') }}"></div>
                        <div class="mb-2"><label class="form-label">Address</label><input class="form-control" name="address_line1" value="{{ old('address_line1') }}" required></div>
                        <div class="row g-2 mb-2">
                            <div class="col-md-4"><input class="form-control" name="city" placeholder="City" value="{{ old('city') }}" required></div>
                            <div class="col-md-4"><input class="form-control" name="state" placeholder="State" value="{{ old('state') }}" required></div>
                            <div class="col-md-4"><input class="form-control" name="postal_code" placeholder="PIN" value="{{ old('postal_code') }}" required></div>
                        </div>
                        <div class="mb-2"><label class="form-label">GSTIN, if any</label><input class="form-control" name="gstin" value="{{ old('gstin') }}"></div>
                        <div class="mb-3"><label class="form-label">PAN, if any</label><input class="form-control" name="pan" value="{{ old('pan') }}"></div>
                        <button class="btn btn-primary" type="submit">Send details</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
