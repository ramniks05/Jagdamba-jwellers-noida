@extends('layouts.app')

@section('title', $title)

@section('content')
    <h1 class="page-title h3 mb-1">{{ $title }}</h1>
    <p class="text-secondary mb-3">{{ $record->exists ? 'Changes show on new pieces and bills straight away.' : 'Give it a name and a short code you will recognise in lists.' }}</p>
    <form method="POST" action="{{ $record->exists ? route($routeName.'.update', $record) : route($routeName.'.store') }}" autocomplete="off">
        @csrf
        @if ($record->exists)
            @method('PUT')
        @endif
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <div class="weigh-section-title">{{ $singular }}</div>
                        <div class="row g-3">
                            @include('masters.partials.field', ['name' => 'name', 'label' => 'Name', 'value' => $record->name, 'col' => 'col-md-8', 'maxlength' => 80, 'autofocus' => ! $record->exists])
                            @include('masters.partials.field', ['name' => 'code', 'label' => 'Code', 'value' => $record->code, 'col' => 'col-md-4', 'class' => 'text-uppercase', 'maxlength' => 20, 'help' => '2 to 20 letters or numbers.'])
                        </div>
                        @include('masters.partials.visibility', ['record' => $record, 'noun' => strtolower($singular)])
                    </div>
                </div>
                @include('masters.partials.form-foot', ['label' => 'Save '.strtolower($singular), 'backUrl' => $backUrl])
            </div>
        </div>
    </form>
@endsection
