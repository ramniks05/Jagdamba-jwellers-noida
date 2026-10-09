@extends('layouts.app')

@section('title', 'Making and wastage')

@section('content')
    @include('masters.partials.head', [
        'title' => 'Making and wastage',
        'intro' => 'Rename a method or hide it from the piece and bill screens. The calculation behind each method stays fixed, and the rate is entered on each piece.',
    ])
    <div class="row g-3">
        @foreach ($groups as $key => $label)
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header bg-white">{{ $label }}</div>
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th class="num">Pieces</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($methods->get($key, collect()) as $method)
                                    <tr>
                                        <td>
                                            <span class="fw-semibold">{{ $method->name }}</span>
                                            <div class="small text-secondary">{{ $method->code }}@if ($method->is_system) · built in @endif</div>
                                        </td>
                                        <td class="num">{{ $pieces[$method->id] ?? 0 }}</td>
                                        <td>@include('masters.partials.status', ['active' => $method->is_active])</td>
                                        <td class="text-end">
                                            @can('update', $method)
                                                <a href="{{ route('charge-methods.edit', $method) }}">Edit</a>
                                            @endcan
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4">No methods yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
