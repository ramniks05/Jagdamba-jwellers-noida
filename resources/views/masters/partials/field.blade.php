<div class="{{ $col ?? 'col-md-6' }}">
    <label class="form-label" for="{{ $name }}">{{ $label }}</label>
    <input class="form-control @error($name) is-invalid @enderror {{ $class ?? '' }}" id="{{ $name }}" name="{{ $name }}" type="{{ $type ?? 'text' }}" @if (($type ?? 'text') !== 'password') value="{{ old($name, $value ?? null) }}" @endif @if (! empty($placeholder)) placeholder="{{ $placeholder }}" @endif @if (! empty($maxlength)) maxlength="{{ $maxlength }}" @endif @if (! empty($inputmode)) inputmode="{{ $inputmode }}" @endif @if (! empty($autocomplete)) autocomplete="{{ $autocomplete }}" @endif @if ($required ?? true) required @endif @if (! empty($autofocus)) autofocus @endif @disabled($disabled ?? false)>
    @error($name)
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
    @if (! empty($help))
        <div class="form-text">{{ $help }}</div>
    @endif
</div>
