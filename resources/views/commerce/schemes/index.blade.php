@extends('layouts.app')

@section('title', 'Gold schemes')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="page-title h3 mb-0">Gold schemes</h1>
        @can('create', App\Models\GoldScheme::class)
            <a class="btn btn-primary" href="{{ route('schemes.create') }}">New scheme</a>
        @endcan
    </div>
    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>Code</th><th>Name</th><th>Months</th><th>Monthly</th><th>Bonus</th><th></th></tr></thead>
                <tbody>
                    @forelse ($schemes as $scheme)
                        <tr>
                            <td>{{ $scheme->code }}</td>
                            <td>{{ $scheme->name }}</td>
                            <td>{{ $scheme->duration_months }}</td>
                            <td>{{ $scheme->monthly_amount ?: 'Variable' }}</td>
                            <td>{{ $scheme->bonus_type }}</td>
                            <td class="text-end"><a href="{{ route('schemes.show', $scheme) }}">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6">No schemes yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $schemes->links() }}</div>
@endsection
