@extends('layouts.app')

@section('title', 'Edit calculation method')

@section('content')
    <h1 class="page-title h3 mb-1">{{ $method->applies_to->label() }}: {{ $method->name }}</h1>
    <p class="text-secondary mb-3">The name is what the counter sees. The calculation stays {{ $method->code }}.</p>
    <form method="POST" action="{{ route('charge-methods.update', $method) }}" autocomplete="off">
        @csrf
        @method('PUT')
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <div class="weigh-section-title">Method</div>
                        <div class="row g-3">
                            @include('masters.partials.field', ['name' => 'name', 'label' => 'Name', 'value' => $method->name, 'col' => 'col-md-8', 'maxlength' => 80, 'autofocus' => true])
                            <div class="col-md-4">
                                <label class="form-label" for="method-code">Calculation</label>
                                <input class="form-control" id="method-code" value="{{ $method->code }}" disabled>
                            </div>
                        </div>
                        @include('masters.partials.visibility', ['record' => $method, 'noun' => 'method', 'hint' => 'Hidden methods stay on old pieces and can be turned back on.'])
                    </div>
                </div>
                @include('masters.partials.form-foot', ['label' => 'Save method', 'backUrl' => route('charge-methods.index')])
            </div>
        </div>
    </form>
@endsection
