@extends('layouts.app')

@section('title', $customer->exists ? 'Edit customer' : 'Add customer')

@section('content')
    <h1 class="page-title h3 mb-4">{{ $customer->exists ? 'Edit customer' : 'Add customer' }}</h1>
    <form method="POST" action="{{ $customer->exists ? route('customers.update', $customer) : route('customers.store') }}">
        @csrf
        @if ($customer->exists) @method('PUT') @endif
        <div class="card"><div class="card-body row">
            <div class="col-md-3 mb-3">
                <label class="form-label" for="code">Code</label>
                <input class="form-control" id="code" name="code" value="{{ old('code', $customer->code) }}" required @disabled($customer->is_system)>
                @if ($customer->is_system)
                    <input type="hidden" name="code" value="{{ $customer->code }}">
                @endif
            </div>
            <div class="col-md-5 mb-3">
                <label class="form-label" for="name">Name</label>
                <input class="form-control" id="name" name="name" value="{{ old('name', $customer->name) }}" required>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label" for="mobile">Mobile</label>
                <input class="form-control" id="mobile" name="mobile" value="{{ old('mobile', $customer->mobile) }}">
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label" for="email">Email</label>
                <input class="form-control" id="email" name="email" value="{{ old('email', $customer->email) }}">
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label" for="customer_type">Type</label>
                <select class="form-select" id="customer_type" name="customer_type">
                    @foreach ($types as $type)
                        <option value="{{ $type->value }}" @selected(old('customer_type', $customer->customer_type?->value) === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label" for="kyc_status">KYC</label>
                <select class="form-select" id="kyc_status" name="kyc_status">
                    @foreach ($kyc as $status)
                        <option value="{{ $status->value }}" @selected(old('kyc_status', $customer->kyc_status?->value) === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="address_line1">Address</label>
                <input class="form-control" id="address_line1" name="address_line1" value="{{ old('address_line1', $customer->address_line1) }}">
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label" for="city">City</label>
                <input class="form-control" id="city" name="city" value="{{ old('city', $customer->city) }}">
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label" for="state">State</label>
                <input class="form-control" id="state" name="state" value="{{ old('state', $customer->state) }}">
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label" for="pan">PAN</label>
                <input class="form-control" id="pan" name="pan" value="{{ old('pan', $customer->pan) }}">
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label" for="gstin">GSTIN</label>
                <input class="form-control" id="gstin" name="gstin" value="{{ old('gstin', $customer->gstin) }}">
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label" for="dob">Date of birth</label>
                <input class="form-control" id="dob" name="dob" type="date" value="{{ old('dob', $customer->dob?->format('Y-m-d')) }}">
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label" for="anniversary">Anniversary</label>
                <input class="form-control" id="anniversary" name="anniversary" type="date" value="{{ old('anniversary', $customer->anniversary?->format('Y-m-d')) }}">
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label" for="id_proof_type">ID proof</label>
                <input class="form-control" id="id_proof_type" name="id_proof_type" value="{{ old('id_proof_type', $customer->id_proof_type) }}" placeholder="Aadhaar">
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label" for="id_proof_number">ID number</label>
                <input class="form-control" id="id_proof_number" name="id_proof_number" value="{{ old('id_proof_number', $customer->id_proof_number) }}">
            </div>
            <input type="hidden" name="country" value="{{ old('country', $customer->country ?: 'India') }}">
            <input type="hidden" name="is_active" value="0">
            <div class="col-12">
                <div class="form-check">
                    <input class="form-check-input" id="is_active" name="is_active" type="checkbox" value="1" @checked(filter_var(old('is_active', $customer->is_active ?? true), FILTER_VALIDATE_BOOLEAN)) @disabled($customer->is_system)>
                    <label class="form-check-label" for="is_active">Active</label>
                </div>
            </div>
        </div></div>
        <button class="btn btn-primary mt-4" type="submit">Save customer</button>
    </form>
@endsection
