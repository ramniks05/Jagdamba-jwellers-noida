@extends('layouts.app')

@section('title', $title)

@section('content')
    @include('masters.partials.head', [
        'title' => $title,
        'intro' => $intro,
        'actions' => auth()->user()->can('create', $modelClass)
            ? [['url' => route($routeName.'.create'), 'label' => 'Add '.strtolower($singular), 'icon' => 'plus-lg', 'primary' => true]]
            : [],
    ])
    @include('masters.partials.filters', ['action' => route($routeName.'.index')])
    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Code</th>
                        @foreach ($counts as $heading)
                            <th class="num">{{ $heading }}</th>
                        @endforeach
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $record)
                        <tr>
                            <td class="fw-semibold">{{ $record->name }}</td>
                            <td class="text-nowrap">{{ $record->code }}</td>
                            @foreach ($counts as $relation => $heading)
                                <td class="num">
                                    @if ($relation === 'purities')
                                        <a href="{{ route('metals.purities.index', $record) }}">{{ $record->purities_count }} {{ Str::plural('purity', $record->purities_count) }}</a>
                                    @else
                                        {{ $record->{$relation.'_count'} }}
                                    @endif
                                </td>
                            @endforeach
                            <td>@include('masters.partials.status', ['active' => $record->is_active])</td>
                            @include('masters.partials.row-actions', [
                                'record' => $record,
                                'editUrl' => route($routeName.'.edit', $record),
                                'deleteUrl' => route($routeName.'.destroy', $record),
                            ])
                        </tr>
                    @empty
                        <tr><td colspan="{{ 4 + count($counts) }}">{{ $search !== '' || $show !== 'all' ? 'Nothing matches this search.' : 'Nothing here yet.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @include('masters.partials.pager', ['rows' => $records])
@endsection
