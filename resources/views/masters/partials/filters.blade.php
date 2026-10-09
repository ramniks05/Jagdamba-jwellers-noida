<form class="d-flex flex-wrap align-items-center gap-2 mb-3" method="GET" action="{{ $action }}">
    @if ($show !== 'all')
        <input type="hidden" name="show" value="{{ $show }}">
    @endif
    <input class="form-control master-search" name="search" value="{{ $search }}" placeholder="{{ $placeholder ?? 'Search name or code' }}" aria-label="Search">
    <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i> Search</button>
    @if ($search !== '')
        <a class="btn btn-outline-secondary" href="{{ $action }}{{ $show !== 'all' ? '?show='.$show : '' }}">Clear</a>
    @endif
    <div class="d-flex flex-wrap gap-2 ms-md-auto">
        @foreach (['all' => 'All', 'active' => 'Active', 'hidden' => 'Hidden'] as $key => $label)
            <a class="btn btn-sm {{ $show === $key ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ $action }}?{{ http_build_query(array_filter(['search' => $search, 'show' => $key === 'all' ? null : $key])) }}">{{ $label }}</a>
        @endforeach
    </div>
</form>
