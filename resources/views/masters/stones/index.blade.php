@extends('layouts.app')

@section('title', 'Stones')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="page-title h3 mb-0">Stones</h1>
        <div class="d-flex gap-2">
            @can('create', App\Models\StoneType::class)
                <a class="btn btn-primary" href="{{ route('stone-types.create') }}">Add stone type</a>
            @endcan
            @can('create', App\Models\StoneGrade::class)
                <a class="btn btn-outline-secondary" href="{{ route('stone-grades.create') }}">Add grade</a>
            @endcan
        </div>
    </div>
    <form class="row g-2 mb-3" method="GET" action="{{ route('stones.index') }}">
        <div class="col-md-4">
            <input class="form-control" name="search" value="{{ $search }}" placeholder="Search stones">
        </div>
        <div class="col-auto">
            <button class="btn btn-outline-secondary" type="submit">Search</button>
        </div>
    </form>
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header bg-white">Stone types</div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr><th>Name</th><th>Code</th><th></th></tr>
                        </thead>
                        <tbody>
                            @forelse ($types as $type)
                                <tr>
                                    <td>{{ $type->name }}</td>
                                    <td>{{ $type->code }}</td>
                                    <td class="text-end">
                                        @can('update', $type)
                                            <a href="{{ route('stone-types.edit', $type) }}">Edit</a>
                                        @endcan
                                        @can('delete', $type)
                                            <form class="d-inline" method="POST" action="{{ route('stone-types.destroy', $type) }}" onsubmit="return confirm('Remove this stone type?')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-link text-danger p-0 ms-2" type="submit">Remove</button>
                                            </form>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3">No stone types yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header bg-white">Grades</div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr><th>Kind</th><th>Name</th><th>Code</th><th></th></tr>
                        </thead>
                        <tbody>
                            @forelse ($grades as $grade)
                                <tr>
                                    <td>{{ $grade->kind->label() }}</td>
                                    <td>{{ $grade->name }}</td>
                                    <td>{{ $grade->code }}</td>
                                    <td class="text-end">
                                        @can('update', $grade)
                                            <a href="{{ route('stone-grades.edit', $grade) }}">Edit</a>
                                        @endcan
                                        @can('delete', $grade)
                                            <form class="d-inline" method="POST" action="{{ route('stone-grades.destroy', $grade) }}" onsubmit="return confirm('Remove this grade?')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-link text-danger p-0 ms-2" type="submit">Remove</button>
                                            </form>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4">No grades yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
