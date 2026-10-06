@extends('layouts.app')

@section('title', 'Categories')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="page-title h3 mb-1">Categories</h1>
            <p class="text-secondary mb-0">Choose a parent when you save to make a subcategory.</p>
        </div>
        @can('create', App\Models\Category::class)
            <a class="btn btn-primary" href="{{ route('categories.create') }}">Add category</a>
        @endcan
    </div>
    <form class="row g-2 mb-3" method="GET" action="{{ route('categories.index') }}">
        <div class="col-md-4">
            <input class="form-control" name="search" value="{{ $search }}" placeholder="Search name or code">
        </div>
        <div class="col-auto">
            <button class="btn btn-outline-secondary" type="submit">Search</button>
        </div>
    </form>
    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Parent</th>
                        <th>Code</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($categories as $category)
                        <tr>
                            <td>{{ $category->name }}</td>
                            <td>{{ $category->parent?->name ?: '—' }}</td>
                            <td>{{ $category->code }}</td>
                            <td>{{ $category->is_active ? 'Active' : 'Inactive' }}</td>
                            <td class="text-end">
                                @can('update', $category)
                                    <a href="{{ route('categories.edit', $category) }}">Edit</a>
                                @endcan
                                @can('delete', $category)
                                    <form class="d-inline" method="POST" action="{{ route('categories.destroy', $category) }}" onsubmit="return confirm('Remove this category?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-link text-danger p-0 ms-2" type="submit">Remove</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5">No categories yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $categories->links() }}</div>
@endsection
