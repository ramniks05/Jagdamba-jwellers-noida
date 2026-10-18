@extends('layouts.app')

@section('title', $purity->exists ? 'Edit purity' : 'Add purity')

@section('content')
    <h1 class="page-title h3 mb-1">{{ $purity->exists ? 'Edit purity' : 'Add purity' }} for {{ $metal->name }}</h1>
    <p class="text-secondary mb-3">Leave the fineness blank when this purity has no fixed fineness. A purity with a fineness is used for the rate suggestion.</p>
    <form method="POST" action="{{ $purity->exists ? route('metals.purities.update', [$metal, $purity]) : route('metals.purities.store', $metal) }}" autocomplete="off">
        @csrf
        @if ($purity->exists)
            @method('PUT')
        @endif
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <div class="weigh-section-title">Purity</div>
                        <div class="row g-3">
                            @include('masters.partials.field', ['name' => 'name', 'label' => 'Name', 'value' => $purity->name, 'col' => 'col-md-4', 'maxlength' => 80, 'placeholder' => '22K', 'autofocus' => ! $purity->exists])
                            @include('masters.partials.field', ['name' => 'code', 'label' => 'Code', 'value' => $purity->code, 'col' => 'col-md-4', 'class' => 'text-uppercase', 'maxlength' => 20, 'placeholder' => '22K', 'help' => '2 to 20 letters or numbers.'])
                            @include('masters.partials.field', ['name' => 'fineness_percent', 'label' => 'Fineness %', 'value' => $finenessPercent, 'col' => 'col-md-4', 'inputmode' => 'decimal', 'placeholder' => '91.6', 'required' => false, 'help' => 'Optional. 925 is 92.5. Leave blank to add a purity with no fineness.'])
                        </div>
                        @include('masters.partials.visibility', ['record' => $purity, 'noun' => 'purity', 'hint' => 'Hidden purities stay on old pieces, rates and bills and are left out of new ones.'])
                    </div>
                </div>
                @include('masters.partials.form-foot', ['label' => 'Save purity', 'backUrl' => route('metals.purities.index', $metal)])
            </div>
        </div>
    </form>
@endsection
