@extends('layouts.app')

@section('title', 'Settings')

@section('content')
    <h1 class="page-title h3 mb-2">Settings</h1>
    <p class="text-secondary">Sample amount {{ $currencyPreview }}. Sample weight {{ $weightPreview }}. Date preview {{ $datePreview }}.</p>
    <form method="POST" action="{{ route('settings.update') }}">
        @csrf
        @method('PUT')
        @foreach ($groups as $group)
            <div class="card mb-4">
                <div class="card-header bg-white">{{ $group['label'] }}</div>
                <div class="card-body">
                    @foreach ($group['fields'] as $field)
                        @php
                            $index = $field['index'];
                            $value = old('settings.'.$index.'.value', $field['value']);
                        @endphp
                        <input type="hidden" name="settings[{{ $index }}][key]" value="{{ $field['key'] }}">
                        <div class="mb-3">
                            <label class="form-label" for="setting-{{ $index }}">{{ $field['label'] }}</label>
                            @if ($field['type'] === 'boolean')
                                <input type="hidden" name="settings[{{ $index }}][value]" value="0" @disabled(! $canManage)>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="setting-{{ $index }}" name="settings[{{ $index }}][value]" value="1" @checked(filter_var($value, FILTER_VALIDATE_BOOLEAN)) @disabled(! $canManage)>
                                    <label class="form-check-label" for="setting-{{ $index }}">Enabled</label>
                                </div>
                            @elseif (! empty($field['options']))
                                <select class="form-select" id="setting-{{ $index }}" name="settings[{{ $index }}][value]" @disabled(! $canManage)>
                                    @foreach ($field['options'] as $optionValue => $optionLabel)
                                        <option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue)>{{ $optionLabel }}</option>
                                    @endforeach
                                </select>
                            @elseif (($field['input'] ?? '') === 'textarea')
                                <textarea class="form-control" id="setting-{{ $index }}" name="settings[{{ $index }}][value]" rows="3" @disabled(! $canManage)>{{ $value }}</textarea>
                            @else
                                <input class="form-control" id="setting-{{ $index }}" name="settings[{{ $index }}][value]" value="{{ $value }}" @disabled(! $canManage)>
                            @endif
                            @if ($field['help'])
                                <div class="form-text">{{ $field['help'] }}</div>
                            @endif
                            @error('settings.'.$index.'.value')
                                <div class="text-danger small">{{ $message }}</div>
                            @enderror
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
        @if ($canManage)
            <button class="btn btn-primary" type="submit">Save settings</button>
        @endif
    </form>
@endsection
