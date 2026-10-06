@extends('layouts.app')

@section('title', $scheme->name)

@section('content')
    <h1 class="page-title h3 mb-1">{{ $scheme->name }}</h1>
    <div class="text-secondary mb-4">{{ $scheme->code }} · {{ $scheme->duration_months }} months · {{ $bonuses[$scheme->bonus_type] ?? $scheme->bonus_type }}</div>
    @can('create', App\Models\GoldScheme::class)
        <form class="card mb-4" method="POST" action="{{ route('schemes.enroll', $scheme) }}">
            @csrf
            <div class="card-body row g-2">
                <div class="col-md-6">
                    <select class="form-select" name="customer_uuid" required>
                        <option value="">Enroll a customer</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->uuid }}">{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2"><button class="btn btn-primary" type="submit">Enroll</button></div>
            </div>
        </form>
    @endcan
    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>Number</th><th>Customer</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @forelse ($scheme->enrollments as $enrollment)
                        <tr>
                            <td>{{ $enrollment->number }}</td>
                            <td>{{ $enrollment->customer?->name }}</td>
                            <td>{{ ucfirst($enrollment->status) }}</td>
                            <td class="text-end"><a href="{{ route('enrollments.show', $enrollment) }}">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="4">No members yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
