@extends('layouts.app')

@section('title', $repair->number)

@section('content')
    <div class="d-flex justify-content-between align-items-start mb-4 no-print">
        <div>
            <h1 class="page-title h3 mb-1">{{ $repair->number }}</h1>
            <div class="text-secondary">{{ ucfirst($repair->status) }}</div>
        </div>
        <button class="btn btn-outline-secondary" type="button" onclick="window.print()">Print</button>
    </div>
    <div class="card mb-4">
        <div class="card-body">
            <div class="fw-semibold">{{ $company->displayName() }}</div>
            <div>{{ $repair->customer?->name }} · {{ $repair->customer?->mobile }}</div>
            <div class="mt-2">{{ $repair->description }}</div>
            <div>{{ $repair->problem }}</div>
            <div class="text-secondary">Technician {{ $repair->technician ?: '—' }} · Expected {{ $repair->expected_on?->format('d M Y') ?: '—' }}</div>
            <div>Estimate {{ $money((string) $repair->estimated_cost) }}</div>
            @if ($repair->final_charge !== null)
                <div class="fw-semibold">Charge {{ $money((string) $repair->final_charge) }}</div>
            @endif
        </div>
    </div>
    @can('update', $repair)
        @if (in_array($repair->status, ['received', 'inspection', 'repairing'], true))
            <form class="d-flex gap-2 mb-4 no-print" method="POST" action="{{ route('repairs.status', $repair) }}">
                @csrf
                @if ($repair->status === 'received')
                    <button class="btn btn-primary" name="status" value="inspection" type="submit">Start inspection</button>
                @elseif ($repair->status === 'inspection')
                    <button class="btn btn-primary" name="status" value="repairing" type="submit">Start repair</button>
                @else
                    <button class="btn btn-primary" name="status" value="ready" type="submit">Mark ready</button>
                @endif
                <button class="btn btn-outline-secondary" name="status" value="cancelled" type="submit">Cancel</button>
            </form>
        @endif
        @if ($repair->status === 'ready')
            <form class="card mb-4 no-print" method="POST" action="{{ route('repairs.deliver', $repair) }}">
                @csrf
                <div class="card-header bg-white">Deliver</div>
                <div class="card-body row g-2">
                    <div class="col-md-3"><input class="form-control" name="final_charge" placeholder="Final charge" value="{{ old('final_charge', $repair->estimated_cost) }}"></div>
                    <div class="col-md-3"><input class="form-control" name="payment" placeholder="Payment" value="{{ old('payment') }}"></div>
                    <div class="col-md-3">
                        <select class="form-select" name="method">
                            @foreach ($methods as $method)
                                <option value="{{ $method->value }}">{{ $method->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2"><button class="btn btn-primary" type="submit">Deliver</button></div>
                </div>
            </form>
        @endif
    @endcan
@endsection
