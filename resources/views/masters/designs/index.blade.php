@extends('layouts.app')

@section('title', 'Designs')

@section('content')
    @include('masters.partials.head', [
        'title' => 'Designs',
        'intro' => 'Your design numbers, so a piece can be matched to its pattern.',
        'actions' => auth()->user()->can('create', App\Models\Design::class)
            ? [['url' => route('designs.create'), 'label' => 'Add design', 'icon' => 'plus-lg', 'primary' => true]]
            : [],
    ])
    @include('masters.partials.filters', ['action' => route('designs.index'), 'placeholder' => 'Search name or design number'])
    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Number</th>
                        <th>Name</th>
                        <th>Collection</th>
                        <th>Category</th>
                        <th class="num">Pieces</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($designs as $design)
                        <tr>
                            <td class="text-nowrap fw-semibold">{{ $design->design_number }}</td>
                            <td>
                                {{ $design->name }}
                                @if ($design->description)
                                    <div class="small text-secondary">{{ Str::limit($design->description, 70) }}</div>
                                @endif
                            </td>
                            <td>{{ $design->collection?->name ?: '—' }}</td>
                            <td>{{ $design->category?->name ?: '—' }}</td>
                            <td class="num">{{ $design->items_count }}</td>
                            <td>@include('masters.partials.status', ['active' => $design->is_active])</td>
                            @include('masters.partials.row-actions', [
                                'record' => $design,
                                'editUrl' => route('designs.edit', $design),
                                'deleteUrl' => route('designs.destroy', $design),
                            ])
                        </tr>
                    @empty
                        <tr><td colspan="7">{{ $search !== '' || $show !== 'all' ? 'No design matches this search.' : 'No designs yet.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @include('masters.partials.pager', ['rows' => $designs])
@endsection
