@extends('layouts.app')

@section('title', 'Overview')

@section('content')
    <div class="home-head">
        <div>
            <h1 class="page-title h3 mb-1">{{ $company->displayName() }}</h1>
            <p class="text-secondary mb-0">{{ auth()->user()->roles->pluck('name')->join(', ') ?: 'No role assigned' }} · {{ $year->name ?? 'Financial year not opened' }}</p>
        </div>
        <div class="home-head-actions">
            @if ($currentBranch)
                <button class="branch-chip" type="button" data-bs-toggle="modal" data-bs-target="#branch-modal">
                    <i class="bi bi-geo-alt"></i>
                    <span>{{ $currentBranch->name }}</span>
                </button>
            @endif
            @can('create', App\Models\Sale::class)
                <a class="btn btn-primary" href="{{ route('sales.create') }}"><i class="bi bi-plus-lg"></i> New bill</a>
            @endcan
        </div>
    </div>

    <section class="home-panel mb-4">
        <div class="home-panel-head">
            <h2>Today's metal rates</h2>
            @can('viewAny', App\Models\MetalRate::class)
                <a href="{{ route('rates.index') }}">Change rates</a>
            @endcan
        </div>
        @if ($rateGroups->isEmpty())
            <p class="text-secondary mb-0">No metal rate is saved yet. Save today's rate before billing, purchase, old gold, or girvi.</p>
        @else
            <div class="rate-row">
                @foreach ($rateGroups as $metal => $rates)
                    @php($headline = $rates->first())
                    <button class="rate-card" type="button" data-bs-toggle="modal" data-bs-target="#rate-modal-{{ $loop->index }}">
                        <span class="rate-metal">{{ $metal }}</span>
                        <span class="rate-figure">{{ $money((string) $headline->rate_per_gram) }}</span>
                        <span class="rate-meta">{{ $headline->purity?->name }} / g · {{ $headline->effective_at?->timezone($timezone)->isToday() ? 'Today' : $headline->effective_at?->timezone($timezone)->format('d M') }}</span>
                    </button>
                @endforeach
            </div>
        @endif
    </section>

    <div class="row g-3 mb-4">
        @if ($todaySales !== null)
            <div class="col-6 col-lg-3">
                <a class="home-stat" href="{{ route('sales.index') }}">
                    <span><i class="bi bi-receipt"></i> Today's sales</span>
                    <strong>{{ $todaySales }}</strong>
                    <em>{{ $todayBills }} {{ $todayBills === 1 ? 'bill' : 'bills' }}</em>
                </a>
            </div>
        @endif
        @if ($cashOut !== null)
            <div class="col-6 col-lg-3">
                <div class="home-stat">
                    <span><i class="bi bi-cash-stack"></i> Cash out today</span>
                    <strong>{{ $cashOut }}</strong>
                    <em class="cash-lines">
                        @foreach ($cashOutParts as $part)
                            <span>{{ $part }}</span>
                        @endforeach
                    </em>
                </div>
            </div>
        @endif
        @if ($collectTotal !== null)
            <div class="col-6 col-lg-3">
                <a class="home-stat {{ $collectCount > 0 ? 'attention' : '' }}" href="{{ auth()->user()->can('reports.view') ? route('reports.outstanding') : route('customers.index') }}">
                    <span><i class="bi bi-wallet2"></i> To collect</span>
                    <strong>{{ $collectTotal }}</strong>
                    <em>{{ $collectCount }} {{ $collectCount === 1 ? 'customer' : 'customers' }}</em>
                </a>
            </div>
        @endif
        @if ($repairCount !== null)
            <div class="col-6 col-lg-3">
                <a class="home-stat {{ $repairCount > 0 ? 'attention' : '' }}" href="{{ route('repairs.index') }}">
                    <span><i class="bi bi-tools"></i> Repairs in shop</span>
                    <strong>{{ $repairCount }}</strong>
                    <em>{{ $repairCount === 1 ? 'Job waiting' : 'Jobs waiting' }}</em>
                </a>
            </div>
        @endif
        @if ($availablePieces !== null)
            <div class="col-6 col-lg-3">
                <a class="home-stat" href="{{ route('items.index') }}">
                    <span><i class="bi bi-box-seam"></i> Pieces in stock</span>
                    <strong>{{ $availablePieces }}</strong>
                    <em>Ready to sell</em>
                </a>
            </div>
        @endif
        @if ($girviPrincipal !== null)
            <div class="col-6 col-lg-3">
                <a class="home-stat" href="{{ route('girvi.index') }}">
                    <span><i class="bi bi-safe"></i> Open girvi</span>
                    <strong>{{ $girviPrincipal }}</strong>
                    <em>{{ $girviCount }} {{ $girviCount === 1 ? 'pledge' : 'pledges' }}</em>
                </a>
            </div>
        @endif
        @if ($schemeMembers !== null)
            <div class="col-6 col-lg-3">
                <a class="home-stat" href="{{ route('schemes.index') }}">
                    <span><i class="bi bi-piggy-bank"></i> Schemes</span>
                    <strong>{{ $schemeMembers }}</strong>
                    <em>{{ $dueCount }} {{ $dueCount === 1 ? 'installment due' : 'installments due' }}</em>
                </a>
            </div>
        @endif
    </div>

    <div class="row g-3 mb-4">
        @if ($collectTotal !== null)
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-wallet2 me-1"></i> Money still to collect</span>
                        @can('reports.view')
                            <a href="{{ route('reports.outstanding') }}">All dues</a>
                        @endcan
                    </div>
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th class="num">Due</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($collectRows as $row)
                                    <tr>
                                        <td>
                                            <a href="{{ route('customers.show', $row['customer']) }}">{{ $row['customer']->name }}</a>
                                            <div class="small text-secondary">{{ $row['customer']->mobile }}</div>
                                        </td>
                                        <td class="num">{{ $row['balance'] }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="2">No customer owes money.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
        @if ($repairCount !== null)
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-tools me-1"></i> Repairs still in the shop</span>
                        <a href="{{ route('repairs.index') }}">All repairs</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Piece</th>
                                    <th>Expected</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($openRepairs as $repair)
                                    <tr>
                                        <td><a href="{{ route('repairs.show', $repair) }}">{{ $repair->customer?->name }}</a></td>
                                        <td>{{ $repair->description }}</td>
                                        <td class="{{ $repair->expected_on && $repair->expected_on->toDateString() < $today ? 'is-late' : '' }}">
                                            {{ $repair->expected_on?->format('d M Y') ?: 'Not set' }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3">No repair is waiting.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
        @if ($girviPrincipal !== null)
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-safe me-1"></i> Girvi still in the shop</span>
                        @can('create', App\Models\GirviPledge::class)
                            <a href="{{ route('girvi.create') }}">New girvi</a>
                        @endcan
                    </div>
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>Number</th>
                                    <th>Customer</th>
                                    <th class="num">Loan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($openGirvi as $pledge)
                                    <tr>
                                        <td><a href="{{ route('girvi.show', $pledge) }}">{{ $pledge->number }}</a></td>
                                        <td>
                                            <div>{{ $pledge->customer?->name }}</div>
                                            <div class="small text-secondary">{{ $pledge->description }}</div>
                                        </td>
                                        <td class="num">{{ $money((string) $pledge->principal) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3">No gold is in girvi.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
        @if ($schemeMembers !== null)
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-piggy-bank me-1"></i> Scheme installments due</span>
                        <a href="{{ route('schemes.index') }}">All schemes</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Scheme</th>
                                    <th>Due</th>
                                    <th class="num">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($dueInstallments as $installment)
                                    <tr>
                                        <td><a href="{{ route('enrollments.show', $installment->enrollment) }}">{{ $installment->enrollment?->customer?->name }}</a></td>
                                        <td>{{ $installment->enrollment?->scheme?->name }}</td>
                                        <td>{{ $installment->due_on->format('d M Y') }}</td>
                                        <td class="num">{{ $money((string) $installment->amount) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4">No installment is due.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <div class="home-note">
        <span>Next bill {{ $invoicePreview ?: 'not ready' }}</span>
        @if ($invoicePreviewError)
            <span>{{ $invoicePreviewError }}</span>
        @endif
        <span>{{ $company->gstin ? 'GSTIN '.$company->gstin : 'GSTIN not set' }}</span>
    </div>

    <div class="modal fade" id="branch-modal" tabindex="-1" aria-labelledby="branch-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="branch-modal-title">Branches</h2>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @forelse ($branches as $branch)
                        <div class="branch-line">
                            <div>
                                <strong>{{ $branch->name }}</strong>
                                @if ($branch->is_head_office)
                                    <span class="badge text-bg-warning">Head office</span>
                                @endif
                                <div class="small text-secondary">{{ $branch->code }} · {{ $branch->status->label() }}</div>
                            </div>
                            <div class="small">{{ collect([$branch->address_line1, $branch->city, $branch->phone])->filter()->implode(' · ') }}</div>
                        </div>
                    @empty
                        <p class="mb-0">No branch is set up.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    @foreach ($rateGroups as $metal => $rates)
        <div class="modal fade" id="rate-modal-{{ $loop->index }}" tabindex="-1" aria-labelledby="rate-title-{{ $loop->index }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title h5" id="rate-title-{{ $loop->index }}">{{ $metal }} rates</h2>
                        <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-0">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>Purity</th>
                                    <th class="num">Rate / g</th>
                                    <th>Saved</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rates as $rate)
                                    <tr>
                                        <td>{{ $rate->purity?->name }}</td>
                                        <td class="num">{{ $money((string) $rate->rate_per_gram) }}</td>
                                        <td>{{ $rate->effective_at?->timezone($timezone)->format('d M Y, h:i A') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
@endsection
