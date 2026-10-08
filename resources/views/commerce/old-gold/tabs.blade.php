<div class="d-flex flex-wrap gap-2 mb-3">
    <a class="btn btn-sm {{ $active === 'exchanges' ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('old-gold.index') }}"><i class="bi bi-arrow-left-right"></i> Exchanges</a>
    <a class="btn btn-sm {{ $active === 'stock' ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('old-gold.stock') }}"><i class="bi bi-box-seam"></i> Old gold stock</a>
</div>
