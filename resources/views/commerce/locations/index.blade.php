@extends('layouts.app')

@section('title', 'Stock locations')

@section('content')
    @include('masters.partials.head', [
        'title' => 'Stock locations',
        'intro' => 'Where pieces are kept: counter, tray, locker or safe. Put one inside another, like Tray 1 inside Counter A.',
        'actions' => auth()->user()->can('create', App\Models\StockLocation::class)
            ? [['url' => route('locations.create'), 'label' => 'Add location', 'icon' => 'plus-lg', 'primary' => true]]
            : [],
    ])
    @include('masters.partials.filters', ['action' => route('locations.index')])
    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Location</th>
                        <th>Code</th>
                        <th>Kind</th>
                        <th>Branch</th>
                        <th class="num">In stock</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($locations as $location)
                        <tr>
                            <td>
                                <span class="fw-semibold">{{ $location->name }}</span>
                                @if ($location->parent)
                                    <div class="small text-secondary">Inside {{ $location->parent->name }}</div>
                                @endif
                            </td>
                            <td class="text-nowrap">{{ $location->code }}</td>
                            <td>{{ $location->kind->label() }}</td>
                            <td>{{ $location->branch?->name ?? '—' }}</td>
                            <td class="num">
                                @if ($location->stock_count > 0 && auth()->user()->can('reports.view'))
                                    <a href="{{ route('reports.stock', ['location' => $location->uuid]) }}">{{ $location->stock_count }}</a>
                                @else
                                    {{ $location->stock_count ?: '—' }}
                                @endif
                            </td>
                            <td>@include('masters.partials.status', ['active' => $location->is_active])</td>
                            <td class="text-end">
                                @can('update', $location)
                                    <a href="{{ route('locations.edit', $location) }}">Edit</a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7">{{ $search !== '' || $show !== 'all' ? 'No location matches this search.' : 'No locations yet.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @include('masters.partials.pager', ['rows' => $locations])
@endsection
