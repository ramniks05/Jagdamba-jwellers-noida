@extends('layouts.app')

@section('title', 'Old gold exchange')

@section('content')
    <h1 class="page-title h3 mb-4">Old gold exchange</h1>
    <form method="POST" action="{{ route('old-gold.store') }}">
        @csrf
        <div class="card"><div class="card-body row g-3">
            <div class="col-md-4">
                <label class="form-label">Customer</label>
                <select class="form-select" name="customer_uuid" required>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->uuid }}">{{ $customer->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Metal</label>
                <select class="form-select" name="metal_uuid" required>
                    @foreach ($metals as $metal)
                        <option value="{{ $metal->uuid }}">{{ $metal->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Purity</label>
                <select class="form-select" name="purity_uuid" required>
                    @foreach ($metals as $metal)
                        @foreach ($metal->purities as $purity)
                            <option value="{{ $purity->uuid }}">{{ $metal->name }} {{ $purity->name }}</option>
                        @endforeach
                    @endforeach
                </select>
            </div>
            <div class="col-md-3"><label class="form-label">Gross g</label><input class="form-control" name="gross_weight" value="{{ old('gross_weight') }}" required></div>
            <div class="col-md-3"><label class="form-label">Stone g</label><input class="form-control" name="stone_weight" value="{{ old('stone_weight', '0') }}"></div>
            <div class="col-md-3"><label class="form-label">Melting loss %</label><input class="form-control" name="melting_loss_percent" value="{{ old('melting_loss_percent', '0') }}"></div>
            <div class="col-md-3"><label class="form-label">Rate per gram</label><input class="form-control" name="rate_per_gram" value="{{ old('rate_per_gram') }}" required></div>
            <div class="col-md-3"><label class="form-label">Deduction</label><input class="form-control" name="deduction_amount" value="{{ old('deduction_amount', '0') }}"></div>
            <div class="col-md-3"><label class="form-label">Refund</label><input class="form-control" name="refund" value="{{ old('refund', '0') }}"></div>
            <div class="col-md-6"><label class="form-label">Testing result</label><input class="form-control" name="testing_result" value="{{ old('testing_result') }}"></div>
            <div class="col-12"><button class="btn btn-primary" type="submit">Save exchange</button></div>
        </div></div>
    </form>
@endsection
