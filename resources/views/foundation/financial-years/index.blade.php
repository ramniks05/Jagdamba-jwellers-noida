@extends('layouts.app')

@section('title', 'Financial years')

@section('content')
    @include('masters.partials.head', [
        'title' => 'Financial years',
        'intro' => 'Bill numbers restart each year. Close a year once its books are final; a closed year cannot be changed.',
        'actions' => auth()->user()->can('create', App\Models\FinancialYear::class)
            ? [['url' => route('financial-years.create'), 'label' => 'Add next year', 'icon' => 'plus-lg', 'primary' => true]]
            : [],
    ])
    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Year</th>
                        <th>Period</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($years as $year)
                        <tr>
                            <td>
                                <span class="fw-semibold">{{ $year->name }}</span>
                                @if ($today->betweenIncluded($year->start_date, $year->end_date))
                                    <div class="small text-secondary">Today is in this year</div>
                                @endif
                            </td>
                            <td class="text-nowrap">{{ $year->start_date->format('d M Y') }} – {{ $year->end_date->format('d M Y') }}</td>
                            <td>
                                @if ($year->is_closed)
                                    <span class="order-status is-closed">Closed</span>
                                    <div class="small text-secondary">{{ $year->closed_at?->format('d M Y') }}{{ $year->closedBy ? ' by '.$year->closedBy->name : '' }}</div>
                                @elseif ($year->is_current)
                                    <span class="order-status is-ready">Current</span>
                                @else
                                    <span class="order-status is-active">Open</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                @can('update', $year)
                                    @unless ($year->is_closed)
                                        <a href="{{ route('financial-years.edit', $year) }}">Edit</a>
                                    @endunless
                                    @if (! $year->is_current && ! $year->is_closed)
                                        <form class="d-inline" method="POST" action="{{ route('financial-years.current', $year) }}" onsubmit="return confirm('Make {{ $year->name }} the current year? New bills will be numbered in this year.')">
                                            @csrf
                                            <button class="btn btn-link p-0 ms-2 align-baseline" type="submit">Make current</button>
                                        </form>
                                    @endif
                                @endcan
                                @can('close', $year)
                                    @if (! $year->is_current && ! $year->is_closed)
                                        <form class="d-inline" method="POST" action="{{ route('financial-years.close', $year) }}" onsubmit="return confirm('Close {{ $year->name }}? This cannot be undone and the year can no longer be edited.')">
                                            @csrf
                                            <button class="btn btn-link p-0 ms-2 align-baseline" type="submit">Close year</button>
                                        </form>
                                    @endif
                                @endcan
                                @can('delete', $year)
                                    @if (! $year->is_current && ! $year->is_closed)
                                        <form class="d-inline" method="POST" action="{{ route('financial-years.destroy', $year) }}" onsubmit="return confirm('Remove {{ $year->name }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-link text-danger p-0 ms-2 align-baseline" type="submit">Remove</button>
                                        </form>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4">No financial years yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @include('masters.partials.pager', ['rows' => $years])
@endsection
