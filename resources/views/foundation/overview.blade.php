@extends('layouts.app')

@section('title', 'Overview')

@section('content')
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <h1 class="page-title h3 mb-1">{{ $company->displayName() }}</h1>
            <p class="text-secondary mb-0">{{ auth()->user()->roles->pluck('name')->join(', ') ?: 'No role assigned' }}</p>
        </div>
        <div class="quick-actions">
            @can('create', App\Models\Sale::class)
                <a href="{{ route('sales.create') }}"><i class="bi bi-plus-lg"></i> New bill</a>
            @endcan
            @can('customers.view')
                <a href="{{ route('customers.index') }}"><i class="bi bi-people"></i> Customers</a>
            @endcan
            @can('rates.view')
                <a href="{{ route('rates.index') }}"><i class="bi bi-graph-up-arrow"></i> Metal rates</a>
            @endcan
            @can('items.view')
                <a href="{{ route('items.index') }}"><i class="bi bi-box-seam"></i> Pieces</a>
            @endcan
        </div>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card stat-card h-100">
                <div class="card-body">
                    <div class="stat-label"><i class="bi bi-shop"></i> Shop</div>
                    <div class="stat-value">{{ $company->code }}</div>
                    <div>{{ $company->status->label() }}</div>
                    <div class="small text-secondary mt-2">{{ $company->formattedAddress() }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card h-100">
                <div class="card-body">
                    <div class="stat-label"><i class="bi bi-card-text"></i> Registration</div>
                    <div>GSTIN: {{ $company->gstin ?: 'Not set' }}</div>
                    <div>PAN: {{ $company->pan ?: 'Not set' }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card h-100">
                <div class="card-body">
                    <div class="stat-label"><i class="bi bi-calendar3"></i> Current financial year</div>
                    <div class="stat-value">{{ $year->name ?? 'Not opened' }}</div>
                    @if ($year)
                        <div class="small text-secondary">{{ $year->start_date->format('d M Y') }} – {{ $year->end_date->format('d M Y') }}</div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card h-100">
                <div class="card-body">
                    <div class="stat-label"><i class="bi bi-receipt"></i> Next sales invoice</div>
                    <div class="stat-value">{{ $invoicePreview ?: 'Unavailable' }}</div>
                    @if ($invoicePreviewError)
                        <div class="small text-danger">{{ $invoicePreviewError }}</div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card h-100">
                <div class="card-body">
                    <div class="stat-label"><i class="bi bi-123"></i> Number preview</div>
                    <div>{{ $currencyPreview }}</div>
                    <div>{{ $weightPreview }}</div>
                </div>
            </div>
        </div>
        @if ($availablePieces !== null)
            <div class="col-md-4">
                <div class="card stat-card h-100">
                    <div class="card-body">
                        <div class="stat-label"><i class="bi bi-box-seam"></i> Available pieces</div>
                        <div class="stat-value">{{ $availablePieces }}</div>
                        <a class="small" href="{{ route('items.index') }}">Open pieces</a>
                    </div>
                </div>
            </div>
        @endif
        @if ($todaySales !== null)
            <div class="col-md-4">
                <div class="card stat-card h-100">
                    <div class="card-body">
                        <div class="stat-label"><i class="bi bi-cash"></i> Today's sales</div>
                        <div class="stat-value">{{ $todaySales }}</div>
                        <a class="small" href="{{ route('sales.index') }}">Open sales</a>
                    </div>
                </div>
            </div>
        @endif
        <div class="col-md-4">
            <div class="card stat-card h-100">
                <div class="card-body">
                    <div class="stat-label"><i class="bi bi-clock"></i> Date and time</div>
                    <div>{{ $datePreview }}</div>
                    <div>{{ $timePreview }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-white"><i class="bi bi-buildings me-1"></i> Branches</div>
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
