@extends('layouts.app')

@section('title', 'Old gold')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="page-title h3 mb-0">Old gold</h1>
        @can('create', App\Models\OldGoldExchange::class)
            <a class="btn btn-primary" href="{{ route('old-gold.create') }}">New exchange</a>
        @endcan
    </div>
    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>Number</th><th>Customer</th><th>Metal</th><th>Value</th><th></th></tr></thead>
                <tbody>
                    @forelse ($exchanges as $exchange)
                        <tr>
                            <td>{{ $exchange->number }}</td>
                            <td>{{ $exchange->customer?->name }}</td>
                            <td>{{ $exchange->metalType?->name }} {{ $exchange->purity?->name }}</td>
                            <td>{{ $exchange->exchange_value }}</td>
                            <td class="text-end"><a href="{{ route('old-gold.show', $exchange) }}">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5">No old gold yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $exchanges->links() }}</div>
@endsection
