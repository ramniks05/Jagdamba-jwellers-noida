@extends('layouts.app')

@section('title', 'Edit number series')

@section('content')
    @include('masters.partials.head', [
        'title' => $sequence->document_type->label().' numbers',
        'intro' => 'Changes apply from the next number. Numbers already printed stay as they are.',
    ])
    @if ($previewError)
        <div class="alert alert-warning">{{ $previewError }}</div>
    @endif
    <form method="POST" action="{{ route('document-sequences.update', $sequence) }}" id="sequence-form">
        @csrf
        @method('PUT')
        <div class="row g-3 align-items-start">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <div class="weigh-section-title"><i class="bi bi-hash"></i> Number pattern</div>
                        <div class="row g-3">
                            @include('masters.partials.field', ['name' => 'prefix', 'label' => 'Prefix', 'value' => $sequence->prefix, 'col' => 'col-6 col-md-4', 'class' => 'text-uppercase', 'maxlength' => 20, 'help' => 'Letters and digits, e.g. INV'])
                            @include('masters.partials.field', ['name' => 'separator', 'label' => 'Separator', 'value' => $sequence->separator, 'col' => 'col-6 col-md-4', 'maxlength' => 3, 'help' => '- / _ or .'])
                            @include('masters.partials.field', ['name' => 'suffix', 'label' => 'Suffix', 'value' => $sequence->suffix, 'col' => 'col-6 col-md-4', 'class' => 'text-uppercase', 'maxlength' => 20, 'required' => false, 'help' => 'Optional, e.g. a branch code'])
                            @include('masters.partials.field', ['name' => 'padding', 'label' => 'Digits', 'value' => $sequence->padding, 'col' => 'col-6 col-md-4', 'type' => 'number', 'inputmode' => 'numeric', 'help' => '4 gives 0001'])
                            <div class="col-md-8">
                                <label class="form-label" for="reset_policy">Number starts again at 1</label>
                                <select class="form-select" id="reset_policy" name="reset_policy">
                                    @foreach (\App\Enums\SequenceResetPolicy::cases() as $policy)
                                        <option value="{{ $policy->value }}" @selected(old('reset_policy', $sequence->reset_policy->value) === $policy->value)>{{ $policy->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="weigh-section-title mt-4"><i class="bi bi-skip-forward"></i> Next number</div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label" for="next_number">Next running number</label>
                                <input class="form-control @error('next_number') is-invalid @enderror" id="next_number" name="next_number" type="number" min="1" inputmode="numeric" value="{{ old('next_number', $sequence->next_number) }}" required>
                                @error('next_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-8 form-text mt-md-5">
                                Last issued: <strong>{{ $sequence->last_issued_number ?: 'none yet' }}</strong>. Once numbers are issued this can only move forward, so no number is ever repeated.
                            </div>
                        </div>
                        <div class="mt-4">
                            <input type="hidden" name="is_active" value="0">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" @checked(filter_var(old('is_active', $sequence->is_active), FILTER_VALIDATE_BOOLEAN))>
                                <label class="form-check-label" for="is_active">Series is on</label>
                            </div>
                            <div class="form-text">When it is off, this document cannot be saved until a series is switched on.</div>
                        </div>
                    </div>
                </div>
                @include('masters.partials.form-foot', ['label' => 'Save series', 'backUrl' => route('document-sequences.index')])
            </div>
            <div class="col-lg-4 bill-side">
                <div class="card">
                    <div class="card-body">
                        <div class="weigh-section-title"><i class="bi bi-eye"></i> Next number will look like</div>
                        <div class="sequence-sample" id="sequence-sample" aria-live="polite">{{ $preview ?: '—' }}</div>
                        <div class="form-text mt-2" id="sequence-note">Updates as you type.</div>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        (() => {
            const tokens = @json($periodTokens);
            const form = document.getElementById('sequence-form');
            const v = (name) => (form.elements[name]?.value || '').trim();
            const refresh = () => {
                const policy = v('reset_policy');
                const separator = v('separator') || '-';
                const padding = Math.min(10, Math.max(1, parseInt(v('padding'), 10) || 1));
                const number = String(Math.max(1, parseInt(v('next_number'), 10) || 1)).padStart(padding, '0');
                const parts = [v('prefix').toUpperCase()];
                const note = document.getElementById('sequence-note');
                note.textContent = 'Updates as you type.';
                if (policy !== 'never') {
                    if (tokens[policy]) {
                        parts.push(tokens[policy]);
                    } else {
                        parts.push('????');
                        note.textContent = 'Add a financial year that covers today first.';
                    }
                }
                parts.push(number);
                let text = parts.filter((part) => part !== '').join(separator);
                if (v('suffix')) text += separator + v('suffix').toUpperCase();
                document.getElementById('sequence-sample').textContent = text;
            };
            form.addEventListener('input', refresh);
            form.addEventListener('change', refresh);
        })();
    </script>
@endpush
