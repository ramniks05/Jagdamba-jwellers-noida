@extends('layouts.app')

@section('title', 'Shop profile')

@section('content')
    <h1 class="page-title h3 mb-4">Shop profile</h1>
    <form method="POST" action="{{ route('company.update') }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header bg-white">Identity</div>
                    <div class="card-body row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="name">Shop name</label>
                            <input class="form-control" id="name" name="name" value="{{ old('name', $company->name) }}" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="legal_name">Legal name</label>
                            <input class="form-control" id="legal_name" name="legal_name" value="{{ old('legal_name', $company->legal_name) }}">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label" for="code">Shop code</label>
                            <input class="form-control" id="code" name="code" value="{{ old('code', $company->code) }}" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label" for="gstin">GSTIN</label>
                            <input class="form-control" id="gstin" name="gstin" value="{{ old('gstin', $company->gstin) }}" maxlength="15">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label" for="pan">PAN</label>
                            <input class="form-control" id="pan" name="pan" value="{{ old('pan', $company->pan) }}" maxlength="10">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label" for="email">Email</label>
                            <input class="form-control" id="email" name="email" type="email" value="{{ old('email', $company->email) }}">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label" for="phone">Phone</label>
                            <input class="form-control" id="phone" name="phone" value="{{ old('phone', $company->phone) }}">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label" for="mobile">Mobile</label>
                            <input class="form-control" id="mobile" name="mobile" value="{{ old('mobile', $company->mobile) }}">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="website">Website</label>
                            <input class="form-control" id="website" name="website" value="{{ old('website', $company->website) }}">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label" for="currency_code">Currency</label>
                            <input class="form-control" id="currency_code" name="currency_code" value="{{ old('currency_code', $company->currency_code) }}" required maxlength="3">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label" for="fy_start_month">Financial year starts</label>
                            <select class="form-select" id="fy_start_month" name="fy_start_month" required>
                                @foreach ($months as $number => $label)
                                    <option value="{{ $number }}" @selected((int) old('fy_start_month', $company->fy_start_month) === $number)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label" for="timezone">Timezone</label>
                            <input class="form-control" id="timezone" name="timezone" value="{{ old('timezone', $company->timezone) }}" required>
                        </div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header bg-white">Address</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label" for="address_line1">Address line 1</label>
                            <input class="form-control" id="address_line1" name="address_line1" value="{{ old('address_line1', $company->address_line1) }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="address_line2">Address line 2</label>
                            <input class="form-control" id="address_line2" name="address_line2" value="{{ old('address_line2', $company->address_line2) }}">
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label" for="city">City</label>
                                <input class="form-control" id="city" name="city" value="{{ old('city', $company->city) }}" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label" for="state">State</label>
                                <input class="form-control" id="state" name="state" value="{{ old('state', $company->state) }}" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label" for="postal_code">Postal code</label>
                                <input class="form-control" id="postal_code" name="postal_code" value="{{ old('postal_code', $company->postal_code) }}" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="country">Country</label>
                                <input class="form-control" id="country" name="country" value="{{ old('country', $company->country) }}" required>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header bg-white">Logo</div>
                    <div class="card-body">
                        @if ($company->logo_url)
                            <img src="{{ $company->logo_url }}" alt="Shop logo" class="img-fluid rounded mb-3" style="max-height: 140px;">
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="remove_logo" name="remove_logo" value="1">
                                <label class="form-check-label" for="remove_logo">Remove logo</label>
                            </div>
                        @endif
                        <label class="form-label" for="logo">Upload JPEG, PNG, or WebP (max 2 MB)</label>
                        <input class="form-control" id="logo" name="logo" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                        <div class="form-text">Status: {{ $company->status->label() }}. Suspension is managed later by the platform, not from this form.</div>
                    </div>
                </div>
            </div>
        </div>
        <button class="btn btn-primary mt-4" type="submit">Save profile</button>
    </form>
@endsection
