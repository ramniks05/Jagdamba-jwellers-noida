<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h1 class="page-title h3 mb-1">{{ $title }}</h1>
        @if (! empty($intro))
            <p class="text-secondary mb-0">{{ $intro }}</p>
        @endif
    </div>
    @if (! empty($actions))
        <div class="d-flex flex-wrap gap-2">
            @foreach ($actions as $action)
                <a class="btn {{ ($action['primary'] ?? false) ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ $action['url'] }}">
                    @if (! empty($action['icon']))<i class="bi bi-{{ $action['icon'] }}"></i>@endif {{ $action['label'] }}
                </a>
            @endforeach
        </div>
    @endif
</div>
