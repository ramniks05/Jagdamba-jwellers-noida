@extends('layouts.app')

@section('title', 'Making and wastage')

@section('content')
    <h1 class="page-title h3 mb-2">Making and wastage</h1>
    <p class="text-secondary">Rename a method or hide it. The calculation stays one of these methods so later pricing can use it. Rates are not stored here.</p>
    @foreach ($groups as $key => $label)
        <div class="card mb-4">
            <div class="card-header bg-white">{{ $label }}</div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Code</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($methods->get($key, collect()) as $method)
                            <tr>
                                <td>
                                    {{ $method->name }}
                                    @if ($method->is_system)
                                        <span class="badge text-bg-secondary">Locked method</span>
                                    @endif
                                </td>
                                <td>{{ $method->code }}</td>
                                <td>{{ $method->is_active ? 'Active' : 'Hidden' }}</td>
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
    @endforeach
@endsection
