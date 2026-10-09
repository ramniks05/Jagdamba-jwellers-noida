@extends('layouts.app')

@section('title', 'Stones')

@section('content')
    @include('masters.partials.head', [
        'title' => 'Stones',
        'intro' => 'Active stone types are suggested when you type a stone name on a piece. Grades describe cut, colour and clarity.',
        'actions' => array_values(array_filter([
            auth()->user()->can('create', App\Models\StoneGrade::class)
                ? ['url' => route('stone-grades.create'), 'label' => 'Add grade', 'icon' => 'plus-lg']
                : null,
            auth()->user()->can('create', App\Models\StoneType::class)
                ? ['url' => route('stone-types.create'), 'label' => 'Add stone type', 'icon' => 'plus-lg', 'primary' => true]
                : null,
        ])),
    ])
    @include('masters.partials.filters', ['action' => route('stones.index'), 'placeholder' => 'Search stones or grades'])
    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between">
                    <span>Stone types</span>
                    <span class="text-secondary small">{{ $types->count() }}</span>
                </div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr><th>Name</th><th>Code</th><th>Status</th><th></th></tr>
                        </thead>
                        <tbody>
                            @forelse ($types as $type)
                                <tr>
                                    <td class="fw-semibold">{{ $type->name }}</td>
                                    <td class="text-nowrap">{{ $type->code }}</td>
                                    <td>@include('masters.partials.status', ['active' => $type->is_active])</td>
                                    @include('masters.partials.row-actions', [
                                        'record' => $type,
                                        'editUrl' => route('stone-types.edit', $type),
                                        'deleteUrl' => route('stone-types.destroy', $type),
                                    ])
                                </tr>
                            @empty
                                <tr><td colspan="4">{{ $search !== '' || $show !== 'all' ? 'No stone type matches.' : 'No stone types yet.' }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between">
                    <span>Grades</span>
                    <span class="text-secondary small">{{ $grades->count() }}</span>
                </div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr><th>Kind</th><th>Name</th><th>Code</th><th>Status</th><th></th></tr>
                        </thead>
                        <tbody>
                            @forelse ($grades as $grade)
                                <tr>
                                    <td class="text-nowrap">{{ $grade->kind->label() }}</td>
                                    <td class="fw-semibold">{{ $grade->name }}</td>
                                    <td class="text-nowrap">{{ $grade->code }}</td>
                                    <td>@include('masters.partials.status', ['active' => $grade->is_active])</td>
                                    @include('masters.partials.row-actions', [
                                        'record' => $grade,
                                        'editUrl' => route('stone-grades.edit', $grade),
                                        'deleteUrl' => route('stone-grades.destroy', $grade),
                                    ])
                                </tr>
                            @empty
                                <tr><td colspan="5">{{ $search !== '' || $show !== 'all' ? 'No grade matches.' : 'No grades yet.' }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
