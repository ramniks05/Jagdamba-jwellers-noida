@if (! request()->boolean('all') && $rows->total() > 0)
    <div class="report-pager no-print">
        <div class="d-flex align-items-center gap-2 small text-secondary">
            <span>Showing {{ $rows->firstItem() }}–{{ $rows->lastItem() }} of {{ $rows->total() }} {{ $noun }}</span>
            <form method="GET" action="{{ url()->current() }}" class="d-flex align-items-center gap-2">
                @foreach (request()->except(['per_page', 'page']) as $key => $value)
                    @if (is_string($value))
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endif
                @endforeach
                <label class="text-nowrap" for="per-page">Rows</label>
                <select class="form-select form-select-sm" id="per-page" name="per_page" onchange="this.form.submit()">
                    @foreach ([25, 50, 100] as $size)
                        <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }}</option>
                    @endforeach
                </select>
            </form>
        </div>
        {{ $rows->onEachSide(1)->links() }}
    </div>
@endif
