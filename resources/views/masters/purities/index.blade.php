@extends('layouts.app')

@section('title', $metal->name.' purity')

@section('content')
    @include('masters.partials.head', [
        'title' => $metal->name.' purity',
        'intro' => 'Fineness sets the pure metal in each gram. Rates, pieces and girvi are entered per purity.',
        'actions' => array_values(array_filter([
            ['url' => route('metals.index'), 'label' => 'All metals', 'icon' => 'arrow-left'],
            auth()->user()->can('create', App\Models\Purity::class)
                ? ['url' => route('metals.purities.create', $metal), 'label' => 'Add purity', 'icon' => 'plus-lg', 'primary' => true]
                : null,
        ])),
    ])
    @include('masters.partials.filters', ['action' => route('metals.purities.index', $metal)])
    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Code</th>
                        <th class="num">Fineness</th>
                        <th class="num">Pieces</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($purities as $purity)
                        <tr>
                            <td class="fw-semibold">{{ $purity->name }}</td>
                            <td class="text-nowrap">{{ $purity->code }}</td>
                            <td class="num">{{ \App\Services\Masters\Fineness::percentFromRatio((string) $purity->fineness) }}%</td>
                            <td class="num">{{ $purity->items_count }}</td>
                            <td>@include('masters.partials.status', ['active' => $purity->is_active])</td>
                            @include('masters.partials.row-actions', [
                                'record' => $purity,
                                'editUrl' => route('metals.purities.edit', [$metal, $purity]),
                                'deleteUrl' => route('metals.purities.destroy', [$metal, $purity]),
                            ])
                        </tr>
                    @empty
                        <tr><td colspan="6">{{ $search !== '' || $show !== 'all' ? 'No purity matches this search.' : 'No purities yet.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @include('masters.partials.pager', ['rows' => $purities])
@endsection
