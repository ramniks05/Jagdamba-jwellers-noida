@extends('layouts.app')

@section('title', 'Trays')

@section('content')
    @include('masters.partials.head', [
        'title' => 'Trays',
        'intro' => 'Display trays for loose pieces. Weight and piece count drop when you bill from a tray; main metal stock still follows each bill line.',
        'actions' => auth()->user()->can('create', App\Models\StockTray::class)
            ? [['url' => route('trays.create'), 'label' => 'Add tray', 'icon' => 'plus-lg', 'primary' => true]]
            : [],
    ])
    @include('masters.partials.filters', ['action' => route('trays.index')])
    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Code</th>
                        <th class="num">Weight left</th>
                        <th class="num">Pieces left</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($trays as $tray)
                        <tr>
                            <td class="fw-semibold">{{ $tray->name }}</td>
                            <td>{{ $tray->code }}</td>
                            <td class="num">{{ number_format((float) $tray->grams, 3, '.', '') }} g</td>
                            <td class="num">{{ $tray->pieces }}</td>
                            <td>@include('masters.partials.status', ['active' => $tray->is_active])</td>
                            <td class="text-end">
                                @can('reports.view')
                                    <a href="{{ route('reports.trays', ['tray' => $tray->uuid]) }}">Report</a>
                                @endcan
                                @can('update', $tray)
                                    <span class="text-secondary mx-1">·</span>
                                    <a href="{{ route('trays.edit', $tray) }}">Edit</a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6">{{ $search !== '' || $show !== 'all' ? 'No tray matches this search.' : 'No trays yet.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $trays->links() }}</div>
@endsection
