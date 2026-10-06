@extends('layouts.app')

@section('title', 'Branches')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="page-title h3 mb-0">Branches</h1>
        @can('create', App\Models\Branch::class)
            <a class="btn btn-primary" href="{{ route('branches.create') }}">Add branch</a>
        @endcan
    </div>
    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Code</th>
                        <th>City</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($branches as $branch)
                        <tr>
                            <td>
                                {{ $branch->name }}
                                @if ($branch->is_head_office)
                                    <span class="badge text-bg-warning">Head office</span>
                                @endif
                            </td>
                            <td>{{ $branch->code }}</td>
                            <td>{{ $branch->city }}</td>
                            <td>{{ $branch->status->label() }}</td>
                            <td class="text-end">
                                @can('update', $branch)
                                    <a href="{{ route('branches.edit', $branch) }}">Edit</a>
                                @endcan
                                @can('delete', $branch)
                                    @unless ($branch->is_head_office)
                                        <form class="d-inline" method="POST" action="{{ route('branches.destroy', $branch) }}" onsubmit="return confirm('Remove this branch?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-link text-danger p-0 ms-2" type="submit">Remove</button>
                                        </form>
                                    @endunless
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5">No branches yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $branches->links() }}</div>
@endsection
