@extends('layouts.app')

@section('title', 'New scheme')

@section('content')
    <h1 class="page-title h3 mb-4">New scheme</h1>
    <form method="POST" action="{{ route('schemes.store') }}">
        @csrf
        <div class="card"><div class="card-body row g-3">
            <div class="col-md-3"><label class="form-label">Code</label><input class="form-control" name="code" value="{{ old('code') }}" required></div>
            <div class="col-md-5"><label class="form-label">Name</label><input class="form-control" name="name" value="{{ old('name') }}" required></div>
            <div class="col-md-4">
                <label class="form-label">Installment</label>
                <select class="form-select" name="installment_mode">
                    <option value="fixed">Fixed monthly amount</option>
                    <option value="variable">Variable amount</option>
                </select>
            </div>
            <div class="col-md-3"><label class="form-label">Monthly amount</label><input class="form-control" name="monthly_amount" value="{{ old('monthly_amount') }}"></div>
            <div class="col-md-3"><label class="form-label">Months</label><input class="form-control" name="duration_months" value="{{ old('duration_months', '11') }}" required></div>
            <div class="col-md-3">
                <label class="form-label">Bonus</label>
                <select class="form-select" name="bonus_type">
                    @foreach ($bonuses as $code => $label)
                        <option value="{{ $code }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3"><label class="form-label">Bonus value</label><input class="form-control" name="bonus_value" value="{{ old('bonus_value', '0') }}"></div>
            <input type="hidden" name="is_active" value="1">
            <div class="col-12"><button class="btn btn-primary" type="submit">Save scheme</button></div>
        </div></div>
    </form>
@endsection
