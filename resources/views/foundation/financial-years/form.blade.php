@extends('layouts.app')

@section('title', $year->exists ? 'Edit financial year' : 'Add financial year')

@section('content')
    @php
        $start = $year->start_date instanceof \Carbon\CarbonInterface ? $year->start_date->toDateString() : $year->start_date;
        $end = $year->end_date instanceof \Carbon\CarbonInterface ? $year->end_date->toDateString() : $year->end_date;
    @endphp
    <h1 class="page-title h3 mb-4">{{ $year->exists ? 'Edit financial year' : 'Add financial year' }}</h1>
    <form method="POST" action="{{ $year->exists ? route('financial-years.update', $year) : route('financial-years.store') }}">
        @csrf
        @if ($year->exists)
            @method('PUT')
        @endif
        <div class="card">
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label" for="name">Name</label>
                    <input class="form-control" id="name" name="name" value="{{ old('name', $year->name) }}" required>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="start_date">Start date</label>
                        <input class="form-control" id="start_date" name="start_date" type="date" value="{{ old('start_date', $start) }}" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="end_date">End date</label>
                        <input class="form-control" id="end_date" name="end_date" type="date" value="{{ old('end_date', $end) }}" required>
                    </div>
                </div>
                @if ($year->exists && $year->is_current)
                    <input type="hidden" name="is_current" value="1">
                    <p class="text-secondary mb-0">This is the current financial year.</p>
                @else
                    <input type="hidden" name="is_current" value="0">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="is_current" name="is_current" value="1" @checked(old('is_current'))>
                        <label class="form-check-label" for="is_current">Make this the current year</label>
                    </div>
                @endif
                <p class="form-text mt-3">Changing the shop's start month does not rewrite years that already exist. It is used the next time a year is suggested.</p>
            </div>
        </div>
        <button class="btn btn-primary mt-4" type="submit">Save year</button>
        <a class="btn btn-link" href="{{ route('financial-years.index') }}">Cancel</a>
    </form>
@endsection
