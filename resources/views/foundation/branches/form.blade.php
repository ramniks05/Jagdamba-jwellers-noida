@extends('layouts.app')

@section('title', $branch->exists ? 'Edit branch' : 'Add branch')

@section('content')
    <h1 class="page-title h3 mb-4">{{ $branch->exists ? 'Edit branch' : 'Add branch' }}</h1>
    <form method="POST" action="{{ $branch->exists ? route('branches.update', $branch) : route('branches.store') }}">
        @csrf
        @if ($branch->exists)
            @method('PUT')
        @endif
        <div class="card">
            <div class="card-body row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="name">Name</label>
                    <input class="form-control" id="name" name="name" value="{{ old('name', $branch->name) }}" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="code">Code</label>
                    <input class="form-control" id="code" name="code" value="{{ old('code', $branch->code) }}" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="status">Status</label>
                    @if ($branch->exists && $branch->is_head_office)
                        <input type="hidden" name="status" value="active">
                        <input class="form-control" value="Active" disabled>
                    @else
                        <select class="form-select" id="status" name="status">
                            <option value="active" @selected(old('status', $branch->status?->value ?? 'active') === 'active')>Active</option>
                            <option value="inactive" @selected(old('status', $branch->status?->value ?? 'active') === 'inactive')>Inactive</option>
                        </select>
                    @endif
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="phone">Phone</label>
                    <input class="form-control" id="phone" name="phone" value="{{ old('phone', $branch->phone) }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="mobile">Mobile</label>
                    <input class="form-control" id="mobile" name="mobile" value="{{ old('mobile', $branch->mobile) }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="email">Email</label>
                    <input class="form-control" id="email" name="email" type="email" value="{{ old('email', $branch->email) }}">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="gstin">GSTIN</label>
                    <input class="form-control" id="gstin" name="gstin" value="{{ old('gstin', $branch->gstin) }}">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="timezone">Timezone override</label>
                    <input class="form-control" id="timezone" name="timezone" value="{{ old('timezone', $branch->timezone) }}" placeholder="Leave blank to use the shop timezone">
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label" for="address_line1">Address line 1</label>
                    <input class="form-control" id="address_line1" name="address_line1" value="{{ old('address_line1', $branch->address_line1) }}" required>
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label" for="address_line2">Address line 2</label>
                    <input class="form-control" id="address_line2" name="address_line2" value="{{ old('address_line2', $branch->address_line2) }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="city">City</label>
                    <input class="form-control" id="city" name="city" value="{{ old('city', $branch->city) }}" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="state">State</label>
                    <input class="form-control" id="state" name="state" value="{{ old('state', $branch->state) }}" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="postal_code">Postal code</label>
                    <input class="form-control" id="postal_code" name="postal_code" value="{{ old('postal_code', $branch->postal_code) }}" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="country">Country</label>
                    <input class="form-control" id="country" name="country" value="{{ old('country', $branch->country ?: 'India') }}" required>
                </div>
                <div class="col-12">
                    @if ($branch->exists && $branch->is_head_office)
                        <input type="hidden" name="is_head_office" value="1">
                        <p class="text-secondary mb-0">This is the head office. Open another branch and mark it as head office to move the role.</p>
                    @else
                        <input type="hidden" name="is_head_office" value="0">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_head_office" name="is_head_office" value="1" @checked(filter_var(old('is_head_office', $branch->is_head_office), FILTER_VALIDATE_BOOLEAN))>
                            <label class="form-check-label" for="is_head_office">Head office</label>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <button class="btn btn-primary mt-4" type="submit">Save branch</button>
        <a class="btn btn-link" href="{{ route('branches.index') }}">Cancel</a>
    </form>
@endsection
