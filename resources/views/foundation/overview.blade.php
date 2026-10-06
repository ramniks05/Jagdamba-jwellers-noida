@extends('layouts.app')

@section('title', 'Overview')

@section('content')
    <h1 class="page-title h3 mb-1">{{ $company->displayName() }}</h1>
    <p class="text-secondary mb-4">{{ auth()->user()->roles->pluck('name')->join(', ') ?: 'No role assigned' }}</p>
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="stat-label">Shop</div>
                    <div class="fw-semibold">{{ $company->code }}</div>
                    <div>{{ $company->status->label() }}</div>
                    <div class="small text-secondary mt-2">{{ $company->formattedAddress() }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="stat-label">Registration</div>
                    <div>GSTIN: {{ $company->gstin ?: 'Not set' }}</div>
                    <div>PAN: {{ $company->pan ?: 'Not set' }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="stat-label">Current financial year</div>
                    <div class="fw-semibold">{{ $year->name ?? 'Not opened' }}</div>
                    @if ($year)
                        <div class="small text-secondary">{{ $year->start_date->format('d M Y') }} – {{ $year->end_date->format('d M Y') }}</div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="stat-label">Next sales invoice</div>
                    <div class="fw-semibold">{{ $invoicePreview ?: 'Unavailable' }}</div>
                    @if ($invoicePreviewError)
                        <div class="small text-danger">{{ $invoicePreviewError }}</div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="stat-label">Number preview</div>
                    <div>{{ $currencyPreview }}</div>
                    <div>{{ $weightPreview }}</div>
                </div>
            </div>
        </div>
        @if ($availablePieces !== null)
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="stat-label">Available pieces</div>
                        <div class="fw-semibold">{{ $availablePieces }}</div>
                        <a class="small" href="{{ route('items.index') }}">Open pieces</a>
                    </div>
                </div>
            </div>
        @endif
        @if ($todaySales !== null)
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="stat-label">Today's sales</div>
                        <div class="fw-semibold">{{ $todaySales }}</div>
                        <a class="small" href="{{ route('sales.index') }}">Open sales</a>
                    </div>
                </div>
            </div>
        @endif
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="stat-label">Date and time</div>
                    <div>{{ $datePreview }}</div>
                    <div>{{ $timePreview }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-white">Branches</div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Code</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($branches as $branch)
                        <tr>
                            <td>
                                {{ $branch->name }}
                                @if ($branch->is_head_office)
                                    <span class="badge text-bg-warning">Head office</span>
                                @endif
                            </td>
                            <td>{{ $branch->code }}</td>
                            <td>{{ $branch->status->label() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3">No branches yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
