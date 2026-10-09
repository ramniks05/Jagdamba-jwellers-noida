@php
    $index = $field['index'];
    $value = old('settings.'.$index.'.value', $field['value']);
    $id = 'setting-'.$index;
@endphp
<div class="{{ $col ?? 'col-md-6' }}" data-setting="{{ $field['key'] }}">
    <input type="hidden" name="settings[{{ $index }}][key]" value="{{ $field['key'] }}">
    @if ($field['type'] === 'boolean')
        <input type="hidden" name="settings[{{ $index }}][value]" value="0" @disabled(! $canManage)>
        <div class="form-check form-switch mt-md-4">
            <input class="form-check-input" type="checkbox" role="switch" id="{{ $id }}" name="settings[{{ $index }}][value]" value="1" @checked(filter_var($value, FILTER_VALIDATE_BOOLEAN)) @disabled(! $canManage)>
            <label class="form-check-label" for="{{ $id }}">{{ $label ?? $field['label'] }}</label>
        </div>
    @else
        <label class="form-label" for="{{ $id }}">{{ $label ?? $field['label'] }}</label>
        @if (! empty($field['options']))
            <select class="form-select @error('settings.'.$index.'.value') is-invalid @enderror" id="{{ $id }}" name="settings[{{ $index }}][value]" @disabled(! $canManage)>
                @foreach ($field['options'] as $optionValue => $optionLabel)
                    <option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue)>{{ $optionLabel }}</option>
                @endforeach
            </select>
        @elseif (($field['input'] ?? '') === 'textarea')
            <textarea class="form-control @error('settings.'.$index.'.value') is-invalid @enderror" id="{{ $id }}" name="settings[{{ $index }}][value]" rows="{{ $rows ?? 3 }}" @disabled(! $canManage)>{{ $value }}</textarea>
        @else
            @if (! empty($suffix))
                <div class="input-group">
            @endif
            <input class="form-control @error('settings.'.$index.'.value') is-invalid @enderror" id="{{ $id }}" name="settings[{{ $index }}][value]" value="{{ $value }}" @if (! empty($inputmode)) inputmode="{{ $inputmode }}" @endif @if (! empty($list)) list="{{ $list }}" @endif @if (! empty($maxlength)) maxlength="{{ $maxlength }}" @endif @disabled(! $canManage)>
            @if (! empty($suffix))
                    <span class="input-group-text">{{ $suffix }}</span>
                </div>
            @endif
        @endif
    @endif
    @error('settings.'.$index.'.value')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
    @if (! empty($help ?? $field['help']))
        <div class="form-text">{{ $help ?? $field['help'] }}</div>
    @endif
</div>
