@if ($rows->total() > 0)
    <div class="report-pager">
        <span class="small text-secondary">Showing {{ $rows->firstItem() }}–{{ $rows->lastItem() }} of {{ $rows->total() }}</span>
        {{ $rows->onEachSide(1)->links() }}
    </div>
@endif
