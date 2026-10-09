@extends('layouts.app')

@section('title', 'Gold schemes')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="page-title h3 mb-0">Gold schemes</h1>
        @can('create', App\Models\GoldScheme::class)
            <a class="btn btn-primary" href="{{ route('schemes.create') }}"><i class="bi bi-plus-lg"></i> New scheme</a>
        @endcan
    </div>
    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Scheme</th>
                        <th class="num">Months</th>
                        <th class="num">Each month</th>
                        <th>Bonus</th>
                        <th class="num">Members</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($schemes as $scheme)
                        <tr>
                            <td class="text-nowrap">{{ $scheme->code }}</td>
                            <td>{{ $scheme->name }}<div class="small text-secondary">{{ $scheme->installment_mode === 'fixed' ? 'Fixed monthly amount' : 'Customer chooses the amount' }}</div></td>
                            <td class="num">{{ $scheme->duration_months }}</td>
                            <td class="num">{{ $scheme->monthly_amount !== null ? $money((string) $scheme->monthly_amount) : 'Any amount' }}</td>
                            <td>{{ $bonuses[$scheme->bonus_type] ?? $scheme->bonus_type }}</td>
                            <td class="num">{{ $scheme->enrollments_count }}<div class="small text-secondary">{{ $scheme->paying_count }} paying</div></td>
                            <td><span class="order-status {{ $scheme->is_active ? 'is-active' : 'is-closed' }}">{{ $scheme->is_active ? 'Open to join' : 'Closed' }}</span></td>
                            <td class="text-end text-nowrap">
                                @can('create', App\Models\GoldScheme::class)
                                    @if ($scheme->is_active)
                                        <a class="me-2" href="{{ route('schemes.show', $scheme) }}#add-member">Add member</a>
                                    @endif
                                @endcan
                                <a href="{{ route('schemes.show', $scheme) }}">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8">No schemes yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $schemes->links() }}</div>
@endsection
