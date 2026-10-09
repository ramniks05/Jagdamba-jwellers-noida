@extends('layouts.app')

@section('title', $grade->exists ? 'Edit stone grade' : 'Add stone grade')

@section('content')
    <h1 class="page-title h3 mb-1">{{ $grade->exists ? 'Edit stone grade' : 'Add stone grade' }}</h1>
    <p class="text-secondary mb-3">A grade belongs to one kind, such as cut Excellent or clarity VS1.</p>
    <form method="POST" action="{{ $grade->exists ? route('stone-grades.update', $grade) : route('stone-grades.store') }}" autocomplete="off">
        @csrf
        @if ($grade->exists)
            @method('PUT')
        @endif
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <div class="weigh-section-title">Grade</div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label" for="kind">Kind</label>
                                <select class="form-select @error('kind') is-invalid @enderror" id="kind" name="kind">
                                    @foreach ($kinds as $kind)
                                        <option value="{{ $kind->value }}" @selected(old('kind', $grade->kind?->value ?? $grade->kind) === $kind->value)>{{ $kind->label() }}</option>
                                    @endforeach
                                </select>
                                @error('kind')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            @include('masters.partials.field', ['name' => 'name', 'label' => 'Name', 'value' => $grade->name, 'col' => 'col-md-4', 'maxlength' => 80, 'placeholder' => 'Excellent', 'autofocus' => ! $grade->exists])
                            @include('masters.partials.field', ['name' => 'code', 'label' => 'Code', 'value' => $grade->code, 'col' => 'col-md-4', 'class' => 'text-uppercase', 'maxlength' => 20, 'placeholder' => 'EX', 'help' => '2 to 20 letters or numbers.'])
                        </div>
                        @include('masters.partials.visibility', ['record' => $grade, 'noun' => 'grade'])
                    </div>
                </div>
                @include('masters.partials.form-foot', ['label' => 'Save grade', 'backUrl' => route('stones.index')])
            </div>
        </div>
    </form>
@endsection
