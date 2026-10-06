@extends('layouts.app')

@section('title', 'Financial years')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="page-title h3 mb-0">Financial years</h1>
        @can('create', App\Models\FinancialYear::class)
            <a class="btn btn-primary" href="{{ route('financial-years.create') }}">Add year</a>
        @endcan
    </div>
    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>From</th>
                        <th>To</th>
                        <th>State</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($years as $year)
                        <tr>
                            <td>{{ $year->name }}</td>
                            <td>{{ $year->start_date->format('d M Y') }}</td>
                            <td>{{ $year->end_date->format('d M Y') }}</td>
                            <td>
                                @if ($year->is_current)
                                    <span class="badge text-bg-success">Current</span>
                                @endif
                                @if ($year->is_closed)
                                    <span class="badge text-bg-secondary">Closed</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @can('update', $year)
                                    @unless ($year->is_closed)
                                        <a href="{{ route('financial-years.edit', $year) }}">Edit</a>
                                    @endunless
                                    @if (! $year->is_current && ! $year->is_closed)
                                        <form class="d-inline" method="POST" action="{{ route('financial-years.current', $year) }}">
                                            @csrf
                                            <button class="btn btn-link p-0 ms-2" type="submit">Make current</button>
                                        </form>
                                    @endif
                                @endcan
                                @can('close', $year)
                                    @if (! $year->is_current && ! $year->is_closed)
                                        <form class="d-inline" method="POST" action="{{ route('financial-years.close', $year) }}">
                                            @csrf
                                            <button class="btn btn-link p-0 ms-2" type="submit">Close</button>
                                        </form>
                                    @endif
                                @endcan
                                @can('delete', $year)
                                    @if (! $year->is_current && ! $year->is_closed)
                                        <form class="d-inline" method="POST" action="{{ route('financial-years.destroy', $year) }}" onsubmit="return confirm('Remove this financial year?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-link text-danger p-0 ms-2" type="submit">Remove</button>
                                        </form>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5">No financial years yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $years->links() }}</div>
@endsection
