@extends('layouts.app')

@section('title', $supplier->exists ? 'Edit supplier' : 'Add supplier')

@section('content')
    <h1 class="page-title h3 mb-4">{{ $supplier->exists ? 'Edit supplier' : 'Add supplier' }}</h1>
    <form method="POST" action="{{ $supplier->exists ? route('suppliers.update', $supplier) : route('suppliers.store') }}">
        @csrf
        @if ($supplier->exists) @method('PUT') @endif
        <div class="card"><div class="card-body row">
            <div class="col-md-3 mb-3"><label class="form-label" for="code">Code</label><input class="form-control" id="code" name="code" value="{{ old('code', $supplier->code) }}" required></div>
            <div class="col-md-5 mb-3"><label class="form-label" for="name">Name</label><input class="form-control" id="name" name="name" value="{{ old('name', $supplier->name) }}" required></div>
            <div class="col-md-4 mb-3"><label class="form-label" for="contact_name">Contact</label><input class="form-control" id="contact_name" name="contact_name" value="{{ old('contact_name', $supplier->contact_name) }}"></div>
            <div class="col-md-4 mb-3"><label class="form-label" for="mobile">Mobile</label><input class="form-control" id="mobile" name="mobile" value="{{ old('mobile', $supplier->mobile) }}"></div>
            <div class="col-md-4 mb-3"><label class="form-label" for="email">Email</label><input class="form-control" id="email" name="email" value="{{ old('email', $supplier->email) }}"></div>
            <div class="col-md-4 mb-3">
                <label class="form-label" for="kyc_status">KYC</label>
                <select class="form-select" id="kyc_status" name="kyc_status">
                    @foreach ($kyc as $status)
                        <option value="{{ $status->value }}" @selected(old('kyc_status', $supplier->kyc_status?->value) === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6 mb-3"><label class="form-label" for="address_line1">Address</label><input class="form-control" id="address_line1" name="address_line1" value="{{ old('address_line1', $supplier->address_line1) }}"></div>
            <div class="col-md-3 mb-3"><label class="form-label" for="city">City</label><input class="form-control" id="city" name="city" value="{{ old('city', $supplier->city) }}"></div>
            <div class="col-md-3 mb-3"><label class="form-label" for="pan">PAN</label><input class="form-control" id="pan" name="pan" value="{{ old('pan', $supplier->pan) }}"></div>
            <div class="col-md-3 mb-3"><label class="form-label" for="gstin">GSTIN</label><input class="form-control" id="gstin" name="gstin" value="{{ old('gstin', $supplier->gstin) }}"></div>
            <div class="col-md-3 mb-3"><label class="form-label" for="bank_name">Bank</label><input class="form-control" id="bank_name" name="bank_name" value="{{ old('bank_name', $supplier->bank_name) }}"></div>
            <div class="col-md-3 mb-3"><label class="form-label" for="account_number">Account</label><input class="form-control" id="account_number" name="account_number" value="{{ old('account_number', $supplier->account_number) }}"></div>
            <div class="col-md-3 mb-3"><label class="form-label" for="ifsc">IFSC</label><input class="form-control" id="ifsc" name="ifsc" value="{{ old('ifsc', $supplier->ifsc) }}"></div>
            <input type="hidden" name="country" value="{{ old('country', $supplier->country ?: 'India') }}">
            <input type="hidden" name="is_active" value="0">
            <div class="col-12"><div class="form-check"><input class="form-check-input" id="is_active" name="is_active" type="checkbox" value="1" @checked(filter_var(old('is_active', $supplier->is_active ?? true), FILTER_VALIDATE_BOOLEAN))><label class="form-check-label" for="is_active">Active</label></div></div>
        </div></div>
        <button class="btn btn-primary mt-4" type="submit">Save supplier</button>
    </form>
@endsection
