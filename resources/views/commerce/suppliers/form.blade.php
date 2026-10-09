@extends('layouts.app')

@section('title', $supplier->exists ? 'Edit supplier' : 'Add supplier')

@section('content')
    @php
        $forPurchase = ($forPurchase ?? false) || old('for') === 'purchase';
        $backUrl = $forPurchase ? route('purchases.create') : ($supplier->exists ? route('suppliers.show', $supplier) : route('suppliers.index'));
    @endphp
    @include('masters.partials.head', [
        'title' => $supplier->exists ? 'Edit '.$supplier->name : 'Add supplier',
        'intro' => $forPurchase
            ? 'After saving you go straight back to the purchase with this supplier chosen.'
            : 'Only the name is needed now. GST, PAN and bank details can be added later.',
    ])
    <form method="POST" action="{{ $supplier->exists ? route('suppliers.update', $supplier) : route('suppliers.store') }}">
        @csrf
        @if ($supplier->exists)
            @method('PUT')
        @endif
        @if ($forPurchase)
            <input type="hidden" name="for" value="purchase">
        @endif
        <input type="hidden" name="country" value="{{ old('country', $supplier->country ?: 'India') }}">
        <div class="row">
            <div class="col-lg-9">
                <div class="card">
                    <div class="card-body">
                        <div class="weigh-section-title"><i class="bi bi-truck"></i> Supplier</div>
                        <div class="row g-3">
                            @include('masters.partials.field', ['name' => 'name', 'label' => 'Firm or karigar name', 'value' => $supplier->name, 'col' => 'col-md-6', 'autofocus' => ! $supplier->exists])
                            @include('masters.partials.field', ['name' => 'code', 'label' => 'Code', 'value' => $supplier->code, 'col' => 'col-6 col-md-3', 'class' => 'text-uppercase', 'maxlength' => 20, 'help' => 'Filled for you'])
                            <div class="col-6 col-md-3">
                                <label class="form-label" for="kyc_status">KYC</label>
                                <select class="form-select" id="kyc_status" name="kyc_status">
                                    @foreach ($kyc as $status)
                                        <option value="{{ $status->value }}" @selected(old('kyc_status', $supplier->kyc_status?->value) === $status->value)>{{ $status->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @include('masters.partials.field', ['name' => 'contact_name', 'label' => 'Contact person', 'value' => $supplier->contact_name, 'col' => 'col-md-4', 'required' => false])
                            @include('masters.partials.field', ['name' => 'mobile', 'label' => 'Mobile', 'value' => $supplier->mobile, 'col' => 'col-md-4', 'inputmode' => 'tel', 'maxlength' => 20, 'required' => false])
                            @include('masters.partials.field', ['name' => 'email', 'label' => 'Email', 'value' => $supplier->email, 'col' => 'col-md-4', 'type' => 'email', 'required' => false])
                        </div>

                        <div class="weigh-section-title mt-4"><i class="bi bi-geo-alt"></i> Address</div>
                        <div class="row g-3">
                            @include('masters.partials.field', ['name' => 'address_line1', 'label' => 'Address', 'value' => $supplier->address_line1, 'col' => 'col-md-6', 'required' => false])
                            @include('masters.partials.field', ['name' => 'city', 'label' => 'City', 'value' => $supplier->city, 'col' => 'col-6 col-md-3', 'required' => false])
                            @include('masters.partials.field', ['name' => 'state', 'label' => 'State', 'value' => $supplier->state, 'col' => 'col-6 col-md-3', 'required' => false])
                        </div>

                        <div class="weigh-section-title mt-4"><i class="bi bi-bank"></i> Tax and bank</div>
                        <div class="row g-3">
                            @include('masters.partials.field', ['name' => 'gstin', 'label' => 'GSTIN', 'value' => $supplier->gstin, 'col' => 'col-md-4', 'class' => 'text-uppercase', 'maxlength' => 15, 'required' => false])
                            @include('masters.partials.field', ['name' => 'pan', 'label' => 'PAN', 'value' => $supplier->pan, 'col' => 'col-md-4', 'class' => 'text-uppercase', 'maxlength' => 10, 'required' => false])
                            @include('masters.partials.field', ['name' => 'bank_name', 'label' => 'Bank', 'value' => $supplier->bank_name, 'col' => 'col-md-4', 'required' => false])
                            @include('masters.partials.field', ['name' => 'account_number', 'label' => 'Account number', 'value' => $supplier->account_number, 'col' => 'col-md-6', 'inputmode' => 'numeric', 'maxlength' => 40, 'required' => false])
                            @include('masters.partials.field', ['name' => 'ifsc', 'label' => 'IFSC', 'value' => $supplier->ifsc, 'col' => 'col-md-6', 'class' => 'text-uppercase', 'maxlength' => 11, 'required' => false])
                            <div class="col-12">
                                <label class="form-label" for="notes">Notes</label>
                                <textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes" rows="2" maxlength="1000">{{ old('notes', $supplier->notes) }}</textarea>
                                @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="mt-4">
                            <input type="hidden" name="is_active" value="0">
                            <div class="form-check form-switch">
                                <input class="form-check-input" id="is_active" name="is_active" type="checkbox" role="switch" value="1" @checked(filter_var(old('is_active', $supplier->is_active ?? true), FILTER_VALIDATE_BOOLEAN))>
                                <label class="form-check-label" for="is_active">Show in the purchase supplier list</label>
                            </div>
                        </div>
                    </div>
                </div>
                @include('masters.partials.form-foot', ['label' => $forPurchase ? 'Save and go to purchase' : 'Save supplier', 'backUrl' => $backUrl])
            </div>
        </div>
    </form>
@endsection
