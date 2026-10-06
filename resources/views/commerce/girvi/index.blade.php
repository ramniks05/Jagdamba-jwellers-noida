@extends('layouts.app')

@section('title', 'Girvi')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="page-title h3 mb-0">Girvi</h1>
        @can('create', App\Models\GirviPledge::class)
            <a class="btn btn-primary" href="{{ route('girvi.create') }}"><i class="bi bi-plus-lg"></i> New girvi</a>
        @endcan
    </div>
    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Number</th>
                        <th>Customer</th>
                        <th>Gold</th>
                        <th class="num">Loan</th>
                        <th class="num">Interest</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pledges as $pledge)
                        <tr>
                            <td>{{ $pledge->number }}</td>
                            <td>{{ $pledge->customer?->name }}</td>
                            <td>{{ $pledge->description }}</td>
                            <td class="num">{{ $money((string) $pledge->principal) }}</td>
                            <td class="num">{{ rtrim(rtrim(number_format((float) $pledge->interest_percent, 2, '.', ''), '0'), '.') }}% / month</td>
                            <td>{{ $pledge->status === 'open' ? 'Open' : 'Released' }}</td>
                            <td class="text-end"><a href="{{ route('girvi.show', $pledge) }}">Receipt</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7">No girvi yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $pledges->links() }}</div>
@endsection
