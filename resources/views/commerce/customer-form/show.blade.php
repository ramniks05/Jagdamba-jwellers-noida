@extends('layouts.guest')

@section('title', 'Billing details')
@section('guest_class', 'guest-card-wide')

@section('content')
    <div class="card">
        <div class="card-body p-4">
            <div class="mb-1" style="color: var(--maroon); font-weight: 700;">{{ $company->displayName() }}</div>
            <h1 class="h4 mb-2">Billing details</h1>
            <p class="text-secondary">Fill this once. The counter will use these details and will not ask for them again.</p>
            @if (session('form_result') === 'confirmed')
                <div class="alert alert-success">These details are already correct. Nothing was changed.</div>
            @elseif (session('form_result') === 'updated')
                <div class="alert alert-success">Changes sent. The shop will update your details after approval. Show your mobile at the counter.</div>
            @elseif (session('form_result') === 'waiting')
                <div class="alert alert-success">These details are already waiting for the shop to approve. Show your mobile at the counter.</div>
            @elseif (session('form_result') === 'submitted')
                <div class="alert alert-success">Details sent. The shop will add you as a customer after approval. Show your mobile at the counter.</div>
            @endif
            @include('partials.alerts')
            @php
                $preview = session('customer_preview');
                $editing = is_array($preview) || old('intent') === 'update';
            @endphp
            @if ($editing)
                @php
                    $details = [
                        'name' => old('name', $preview['name'] ?? ''),
                        'mobile' => old('mobile', $preview['mobile'] ?? ''),
                        'email' => old('email', $preview['email'] ?? ''),
                        'address_line1' => old('address_line1', $preview['address_line1'] ?? ''),
                        'city' => old('city', $preview['city'] ?? ''),
                        'state' => old('state', $preview['state'] ?? ''),
                        'postal_code' => old('postal_code', $preview['postal_code'] ?? ''),
                        'gstin' => old('gstin', $preview['gstin'] ?? ''),
                        'pan' => old('pan', $preview['pan'] ?? ''),
                        'dob' => old('dob', $preview['dob'] ?? ''),
                        'anniversary' => old('anniversary', $preview['anniversary'] ?? ''),
                    ];
                @endphp
                <div class="border rounded p-3 mb-4">
                    <h2 class="h6">Your details</h2>
                    <p class="small text-secondary">If these are correct, leave them. Change anything that is wrong and send it to the shop.</p>
                    <form method="POST" action="{{ route('customer-form.store', $company) }}">
                        @csrf
                        <input type="hidden" name="intent" value="confirm">
                        <input type="hidden" name="mobile" value="{{ $details['mobile'] }}">
                        <button class="btn btn-outline-primary btn-sm" type="submit">These are correct</button>
                    </form>
                    <form class="mt-3" method="POST" action="{{ route('customer-form.store', $company) }}">
                        @csrf
                        <input type="hidden" name="intent" value="update">
                        <input type="hidden" name="mobile" value="{{ $details['mobile'] }}">
                        <div class="mb-2"><label class="form-label">Name</label><input class="form-control" name="name" value="{{ $details['name'] }}" required></div>
                        <div class="mb-2"><label class="form-label">Mobile</label><input class="form-control" value="{{ $details['mobile'] }}" readonly></div>
                        <div class="mb-2"><label class="form-label">Email</label><input class="form-control" name="email" type="email" value="{{ $details['email'] }}"></div>
                        <div class="mb-2"><label class="form-label">Address</label><input class="form-control" name="address_line1" value="{{ $details['address_line1'] }}" required></div>
                        <div class="row g-2 mb-2">
                            <div class="col-md-4"><input class="form-control" name="city" placeholder="City" value="{{ $details['city'] }}" required></div>
                            <div class="col-md-4"><input class="form-control" name="state" placeholder="State" value="{{ $details['state'] }}" required></div>
                            <div class="col-md-4"><input class="form-control" name="postal_code" placeholder="PIN" value="{{ $details['postal_code'] }}" required></div>
                        </div>
                        <div class="mb-2"><label class="form-label">GSTIN, if any</label><input class="form-control" name="gstin" value="{{ $details['gstin'] }}"></div>
                        <div class="mb-2"><label class="form-label">PAN, if any</label><input class="form-control" name="pan" value="{{ $details['pan'] }}"></div>
                        <div class="row g-2 mb-3">
                            <div class="col-md-6"><label class="form-label">Date of birth</label><input class="form-control" name="dob" type="date" value="{{ $details['dob'] }}"></div>
                            <div class="col-md-6"><label class="form-label">Anniversary</label><input class="form-control" name="anniversary" type="date" value="{{ $details['anniversary'] }}"></div>
                        </div>
                        <button class="btn btn-primary" type="submit">Send changes</button>
                    </form>
                    <a class="small" href="{{ route('customer-form.create', $company) }}">Check another mobile</a>
                </div>
            @endif
            @unless ($editing)
            <div class="row g-4">
                <div class="col-md-5">
                    <h2 class="h6">Already a customer</h2>
                    <p class="small text-secondary">Enter your mobile number to see your details.</p>
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
                        <div class="mb-2"><label class="form-label">PAN, if any</label><input class="form-control" name="pan" value="{{ old('pan') }}"></div>
                        <div class="row g-2 mb-3">
                            <div class="col-md-6"><label class="form-label">Date of birth</label><input class="form-control" name="dob" type="date" value="{{ old('dob') }}"></div>
                            <div class="col-md-6"><label class="form-label">Anniversary</label><input class="form-control" name="anniversary" type="date" value="{{ old('anniversary') }}"></div>
                        </div>
                        <button class="btn btn-primary" type="submit">Send details</button>
                    </form>
                </div>
            </div>
            @endunless
        </div>
    </div>
@endsection
