@extends('layouts.app')

@section('title', 'Old gold stock')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="page-title h3 mb-0">Old gold</h1>
        @can('create', App\Models\OldGoldExchange::class)
            <a class="btn btn-primary" href="{{ route('old-gold.create') }}"><i class="bi bi-plus-lg"></i> New exchange</a>
        @endcan
    </div>
    @include('commerce.old-gold.tabs', ['active' => 'stock'])

    <div class="card mb-3">
        <div class="card-body">
            <h2 class="h6 mb-1">In hand</h2>
            <p class="small text-secondary">Old gold bought from customers that is still with you. It comes in with every exchange and goes out when you send it to a refiner, karigar or dealer, or make it into a stock piece.</p>
            @if ($balances->isEmpty())
                <p class="mb-0">No old gold in hand.</p>
            @else
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Metal</th>
                                <th class="num">Gross</th>
                                <th class="num">Fine</th>
                                <th class="num">Cost</th>
                                <th class="num">Cost / fine g</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($balances as $row)
                                <tr>
                                    <td class="text-nowrap"><strong>{{ $row['metal']->name }} {{ $row['purity']->name }}</strong></td>
                                    <td class="num">{{ $weight($row['gross']) }}</td>
                                    <td class="num"><strong>{{ $weight($row['fine']) }}</strong></td>
                                    <td class="num">{{ $money($row['value']) }}</td>
                                    <td class="num">{{ (float) $row['fine'] > 0 ? $money(number_format((float) $row['value'] / (float) $row['fine'], 2, '.', '')) : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    @can('create', App\Models\OldGoldExchange::class)
        @if ($balances->isNotEmpty())
            <details class="card mb-3 old-gold-send" @if ($errors->any()) open @endif>
                <summary class="card-body fw-semibold"><i class="bi bi-box-arrow-up-right"></i> Send out old gold</summary>
                <div class="card-body pt-0">
                    <form method="POST" action="{{ route('old-gold.stock.send') }}" autocomplete="off">
                        @csrf
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label" for="send-purity">Which old gold</label>
                                <select class="form-select" id="send-purity" name="purity_uuid" required>
                                    @foreach ($balances as $row)
                                        <option value="{{ $row['purity']->uuid }}" data-gross="{{ $row['gross'] }}" data-fine="{{ $row['fine'] }}" @selected(old('purity_uuid') === $row['purity']->uuid)>{{ $row['metal']->name }} {{ $row['purity']->name }} · {{ $weight($row['gross']) }} in hand</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="send-kind">What happened</label>
                                <select class="form-select" id="send-kind" name="kind" required>
                                    @foreach ($kinds as $key => $label)
                                        <option value="{{ $key }}" @selected(old('kind', 'refiner') === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="send-party">To</label>
                                <input class="form-control" id="send-party" name="party" value="{{ old('party') }}" maxlength="120" placeholder="Refiner, karigar or dealer name">
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label" for="send-gross">Gross g</label>
                                <div class="input-group">
                                    <input class="form-control" id="send-gross" name="gross_weight" value="{{ old('gross_weight') }}" inputmode="decimal" required>
                                    <button class="btn btn-outline-secondary" type="button" id="send-all">All</button>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label" for="send-fine">Fine g</label>
                                <input class="form-control" id="send-fine" name="fine_weight" value="{{ old('fine_weight') }}" inputmode="decimal" required>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label" for="send-amount">Amount received ₹</label>
                                <input class="form-control" id="send-amount" name="amount_received" value="{{ old('amount_received') }}" inputmode="decimal" placeholder="If sold">
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label" for="send-notes">Notes</label>
                                <input class="form-control" id="send-notes" name="notes" value="{{ old('notes') }}" maxlength="1000" placeholder="Challan no., fine gold back…">
                            </div>
                        </div>
                        <p class="small text-secondary mt-2 mb-2">Fine weight fills in from the gross. Change it if the refiner or dealer tested it differently.</p>
                        <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Send out</button>
                    </form>
                </div>
            </details>
        @endif
    @endcan

    <div class="card">
        <div class="card-body pb-0"><h2 class="h6">Movements</h2></div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>What</th>
                        <th>Metal</th>
                        <th class="num">In</th>
                        <th class="num">Out</th>
                        <th class="num">Fine</th>
                        <th class="num">Cost</th>
                        <th class="num">Received</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($movements as $movement)
                        @php($in = $movement->direction === 'in')
                        <tr>
                            <td class="text-nowrap">{{ $movement->moved_at?->timezone(config('app.timezone'))->format('d-m-Y') }}</td>
                            <td>
                                {{ $movement->kindLabel() }}
                                <div class="small text-secondary">
                                    @if ($movement->exchange)
                                        <a href="{{ route('old-gold.show', $movement->exchange) }}">{{ $movement->exchange->number }}</a>@if ($in) · {{ $movement->exchange->customer?->name }}@endif
                                    @endif
                                    @if ($movement->item)
                                        · <a href="{{ route('items.show', $movement->item) }}">{{ $movement->item->item_code }}</a>
                                    @elseif ($movement->party)
                                        {{ $movement->party }}
                                    @endif
                                    @if ($movement->notes)
                                        · {{ $movement->notes }}
                                    @endif
                                </div>
                            </td>
                            <td class="text-nowrap">{{ $movement->metalType?->name }} {{ $movement->purity?->name }}</td>
                            <td class="num">{{ $in ? $weight((string) $movement->gross_weight) : '' }}</td>
                            <td class="num">{{ $in ? '' : $weight((string) $movement->gross_weight) }}</td>
                            <td class="num">{{ $in ? '' : '− ' }}{{ $weight((string) $movement->fine_weight) }}</td>
                            <td class="num">{{ $money((string) $movement->value) }}</td>
                            <td class="num">{{ $movement->amount_received !== null ? $money((string) $movement->amount_received) : '' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8">No old gold yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $movements->links() }}</div>
@endsection

@push('scripts')
    <script>
        (() => {
            const purity = document.getElementById('send-purity');
            const gross = document.getElementById('send-gross');
            const fine = document.getElementById('send-fine');

            if (!purity) {
                return;
            }

            let fineTouched = fine.value !== '';
            const selected = () => purity.options[purity.selectedIndex];
            const fillFine = () => {
                if (fineTouched) {
                    return;
                }

                const option = selected();
                const haveGross = parseFloat(option.dataset.gross) || 0;
                const haveFine = parseFloat(option.dataset.fine) || 0;
                const sent = parseFloat(gross.value);
                fine.value = haveGross > 0 && sent > 0 ? Math.min(haveFine, sent * haveFine / haveGross).toFixed(3) : '';
            };

            fine.addEventListener('input', () => { fineTouched = fine.value !== ''; });
            gross.addEventListener('input', fillFine);
            purity.addEventListener('change', () => { fineTouched = false; fillFine(); });
            document.getElementById('send-all').addEventListener('click', () => {
                const option = selected();
                gross.value = option.dataset.gross;
                fine.value = option.dataset.fine;
                fineTouched = false;
            });
        })();
    </script>
@endpush
