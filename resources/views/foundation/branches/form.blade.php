@extends('layouts.app')

@section('title', $branch->exists ? 'Edit branch' : 'Add branch')

@section('content')
    @include('masters.partials.head', [
        'title' => $branch->exists ? 'Edit '.$branch->name : 'Add branch',
        'intro' => 'Address and GSTIN for this counter. Leave the timezone blank to use the shop timezone.',
    ])
    <form method="POST" action="{{ $branch->exists ? route('branches.update', $branch) : route('branches.store') }}">
        @csrf
        @if ($branch->exists)
            @method('PUT')
        @endif
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <div class="weigh-section-title"><i class="bi bi-diagram-3"></i> Branch</div>
                        <div class="row g-3">
                            @include('masters.partials.field', ['name' => 'name', 'label' => 'Name', 'value' => $branch->name, 'col' => 'col-md-6', 'autofocus' => ! $branch->exists])
                            @include('masters.partials.field', ['name' => 'code', 'label' => 'Code', 'value' => $branch->code, 'col' => 'col-6 col-md-3', 'class' => 'text-uppercase'])
                            <div class="col-6 col-md-3">
                                <label class="form-label" for="status">Status</label>
                                @if ($branch->exists && $branch->is_head_office)
                                    <input type="hidden" name="status" value="active">
                                    <input class="form-control" id="status" value="Active" disabled>
                                @else
                                    <select class="form-select" id="status" name="status">
                                        <option value="active" @selected(old('status', $branch->status?->value ?? 'active') === 'active')>Active</option>
                                        <option value="inactive" @selected(old('status', $branch->status?->value ?? 'active') === 'inactive')>Inactive</option>
                                    </select>
                                @endif
                            </div>
                            @include('masters.partials.field', ['name' => 'mobile', 'label' => 'Mobile', 'value' => $branch->mobile, 'col' => 'col-md-4', 'inputmode' => 'tel', 'required' => false])
                            @include('masters.partials.field', ['name' => 'phone', 'label' => 'Phone', 'value' => $branch->phone, 'col' => 'col-md-4', 'inputmode' => 'tel', 'required' => false])
                            @include('masters.partials.field', ['name' => 'email', 'label' => 'Email', 'value' => $branch->email, 'col' => 'col-md-4', 'type' => 'email', 'required' => false])
                            @include('masters.partials.field', ['name' => 'gstin', 'label' => 'GSTIN', 'value' => $branch->gstin, 'col' => 'col-md-6', 'class' => 'text-uppercase', 'maxlength' => 15, 'required' => false, 'help' => 'Only if this branch has its own GST registration.'])
                            @include('masters.partials.field', ['name' => 'timezone', 'label' => 'Timezone', 'value' => $branch->timezone, 'col' => 'col-md-6', 'required' => false, 'placeholder' => 'Same as the shop'])
                        </div>

                        <div class="weigh-section-title mt-4"><i class="bi bi-geo-alt"></i> Address</div>
                        <div class="row g-3">
                            @include('masters.partials.field', ['name' => 'address_line1', 'label' => 'Address line 1', 'value' => $branch->address_line1, 'col' => 'col-md-6'])
                            @include('masters.partials.field', ['name' => 'address_line2', 'label' => 'Address line 2', 'value' => $branch->address_line2, 'col' => 'col-md-6', 'required' => false])
                            @include('masters.partials.field', ['name' => 'city', 'label' => 'City', 'value' => $branch->city, 'col' => 'col-6 col-md-3'])
                            @include('masters.partials.field', ['name' => 'state', 'label' => 'State', 'value' => $branch->state, 'col' => 'col-6 col-md-3'])
                            @include('masters.partials.field', ['name' => 'postal_code', 'label' => 'PIN code', 'value' => $branch->postal_code, 'col' => 'col-6 col-md-3', 'inputmode' => 'numeric', 'maxlength' => 12])
                            @include('masters.partials.field', ['name' => 'country', 'label' => 'Country', 'value' => $branch->country ?: 'India', 'col' => 'col-6 col-md-3'])
                        </div>

                        <div class="mt-4">
                            @if ($branch->exists && $branch->is_head_office)
                                <input type="hidden" name="is_head_office" value="1">
                                <p class="text-secondary mb-0"><span class="order-status is-ready">Head office</span> To move the head office, open another branch and switch it on there.</p>
                            @else
                                <input type="hidden" name="is_head_office" value="0">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="is_head_office" name="is_head_office" value="1" @checked(filter_var(old('is_head_office', $branch->is_head_office), FILTER_VALIDATE_BOOLEAN))>
                                    <label class="form-check-label" for="is_head_office">Make this the head office</label>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
                @include('masters.partials.form-foot', ['label' => 'Save branch', 'backUrl' => route('branches.index')])
            </div>
        </div>
    </form>
@endsection
