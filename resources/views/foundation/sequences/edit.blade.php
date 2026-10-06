@extends('layouts.app')

@section('title', 'Edit number series')

@section('content')
    <h1 class="page-title h3 mb-2">{{ $sequence->document_type->label() }}</h1>
    <p class="text-secondary">Next number preview: <strong>{{ $preview ?: 'Unavailable' }}</strong></p>
    @if ($previewError)
        <div class="alert alert-warning">{{ $previewError }}</div>
    @endif
    <form method="POST" action="{{ route('document-sequences.update', $sequence) }}">
        @csrf
        @method('PUT')
        <div class="card">
            <div class="card-body row">
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="prefix">Prefix</label>
                    <input class="form-control" id="prefix" name="prefix" value="{{ old('prefix', $sequence->prefix) }}" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="suffix">Suffix</label>
                    <input class="form-control" id="suffix" name="suffix" value="{{ old('suffix', $sequence->suffix) }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="separator">Separator</label>
                    <input class="form-control" id="separator" name="separator" value="{{ old('separator', $sequence->separator) }}" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="padding">Padding</label>
                    <input class="form-control" id="padding" name="padding" type="number" min="1" max="10" value="{{ old('padding', $sequence->padding) }}" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="next_number">Next number</label>
                    <input class="form-control" id="next_number" name="next_number" type="number" min="1" value="{{ old('next_number', $sequence->next_number) }}" required>
                    <div class="form-text">After a number is issued, this can move forward but not backward. A new period still restarts at 1.</div>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="reset_policy">Reset</label>
                    <select class="form-select" id="reset_policy" name="reset_policy">
                        @foreach (\App\Enums\SequenceResetPolicy::cases() as $policy)
                            <option value="{{ $policy->value }}" @selected(old('reset_policy', $sequence->reset_policy->value) === $policy->value)>{{ $policy->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <input type="hidden" name="is_active" value="0">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(filter_var(old('is_active', $sequence->is_active), FILTER_VALIDATE_BOOLEAN))>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
            </div>
        </div>
        <button class="btn btn-primary mt-4" type="submit">Save series</button>
        <a class="btn btn-link" href="{{ route('document-sequences.index') }}">Cancel</a>
    </form>
@endsection
