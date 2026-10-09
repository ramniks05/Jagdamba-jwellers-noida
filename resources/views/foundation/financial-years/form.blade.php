@extends('layouts.app')

@section('title', $year->exists ? 'Edit financial year' : 'Add financial year')

@section('content')
    @php
        $start = $year->start_date instanceof \Carbon\CarbonInterface ? $year->start_date->toDateString() : $year->start_date;
        $end = $year->end_date instanceof \Carbon\CarbonInterface ? $year->end_date->toDateString() : $year->end_date;
    @endphp
    @include('masters.partials.head', [
        'title' => $year->exists ? 'Edit '.$year->name : 'Add financial year',
        'intro' => 'The dates are filled from the shop’s year start month. The name follows the dates until you type your own.',
    ])
    <form method="POST" action="{{ $year->exists ? route('financial-years.update', $year) : route('financial-years.store') }}" id="year-form">
        @csrf
        @if ($year->exists)
            @method('PUT')
        @endif
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <div class="weigh-section-title"><i class="bi bi-calendar-range"></i> Year</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="start_date">Starts on</label>
                                <input class="form-control @error('start_date') is-invalid @enderror" id="start_date" name="start_date" type="date" value="{{ old('start_date', $start) }}" required>
                                @error('start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="end_date">Ends on</label>
                                <input class="form-control @error('end_date') is-invalid @enderror" id="end_date" name="end_date" type="date" value="{{ old('end_date', $end) }}" required>
                                @error('end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text">Changing the start fills in the end, one year later.</div>
                            </div>
                            @include('masters.partials.field', ['name' => 'name', 'label' => 'Name', 'value' => $year->name, 'col' => 'col-md-6', 'maxlength' => 30, 'help' => 'For example 2026-27.'])
                        </div>
                        <div class="mt-4">
                            @if ($year->exists && $year->is_current)
                                <input type="hidden" name="is_current" value="1">
                                <p class="text-secondary mb-0"><span class="order-status is-ready">Current</span> New bills are numbered in this year.</p>
                            @else
                                <input type="hidden" name="is_current" value="0">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="is_current" name="is_current" value="1" @checked(old('is_current'))>
                                    <label class="form-check-label" for="is_current">Make this the current year</label>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
                @include('masters.partials.form-foot', ['label' => 'Save year', 'backUrl' => route('financial-years.index')])
            </div>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        (() => {
            const start = document.getElementById('start_date');
            const end = document.getElementById('end_date');
            const name = document.getElementById('name');
            const nameFor = (from, to) => from.slice(0, 4) === to.slice(0, 4) ? from.slice(0, 4) : `${from.slice(0, 4)}-${to.slice(2, 4)}`;
            let followsDates = !name.value || name.value === nameFor(start.value || '', end.value || '');

            name.addEventListener('input', () => { followsDates = false; });
            start.addEventListener('change', () => {
                if (!start.value) return;
                const d = new Date(start.value + 'T00:00:00');
                d.setFullYear(d.getFullYear() + 1);
                d.setDate(d.getDate() - 1);
                const pad = (n) => String(n).padStart(2, '0');
                end.value = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
                if (followsDates) name.value = nameFor(start.value, end.value);
            });
            end.addEventListener('change', () => {
                if (followsDates && start.value && end.value) name.value = nameFor(start.value, end.value);
            });
        })();
    </script>
@endpush
