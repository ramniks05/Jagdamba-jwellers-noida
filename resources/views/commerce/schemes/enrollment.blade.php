@extends('layouts.app')

@section('title', $enrollment->number)

@section('content')
    <h1 class="page-title h3 mb-1">{{ $enrollment->number }}</h1>
    <div class="text-secondary mb-4">{{ $enrollment->customer?->name }} · {{ $enrollment->scheme?->name }} · {{ ucfirst($enrollment->status) }}</div>
    @if ($enrollment->status === 'active')
        @can('create', App\Models\GoldScheme::class)
            <form class="card mb-4" method="POST" action="{{ route('enrollments.installments.store', $enrollment) }}">
                @csrf
                <div class="card-header bg-white">Collect installment</div>
                <div class="card-body row g-2">
                    <div class="col-md-3"><input class="form-control" name="amount" value="{{ old('amount', $enrollment->scheme?->monthly_amount) }}" required></div>
                    <div class="col-md-3">
                        <select class="form-select" name="method">
                            @foreach ($methods as $method)
                                <option value="{{ $method->value }}">{{ $method->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2"><button class="btn btn-primary" type="submit">Save</button></div>
                </div>
            </form>
        @endcan
        @can('update', $enrollment)
            <form class="mb-4" method="POST" action="{{ route('enrollments.mature', $enrollment) }}">
                @csrf
                <button class="btn btn-outline-primary" type="submit">Mature scheme</button>
            </form>
        @endcan
    @else
        <div class="alert alert-success">Matured for {{ $money((string) $enrollment->maturity_amount) }}</div>
    @endif
    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>Due</th><th>Amount</th><th>Paid</th></tr></thead>
                <tbody>
                    @foreach ($enrollment->installments as $installment)
                        <tr>
                            <td>{{ $installment->due_on->format('d M Y') }}</td>
                            <td>{{ $money((string) $installment->amount) }}</td>
                            <td>{{ $installment->paid_at?->timezone(config('app.timezone'))->format('d M Y H:i') ?: 'Due' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
