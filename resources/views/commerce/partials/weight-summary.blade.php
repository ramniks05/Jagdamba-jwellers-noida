@if ($weights['rows'] !== [])
    <div class="row g-3 mb-3">
        <div class="col-md-3 col-6">
            <div class="card stat-card h-100">
                <div class="card-body">
                    <div class="stat-label">Net weight</div>
                    <div class="fw-semibold fs-5">{{ $weight($weights['net']) }}</div>
                    <div class="small text-secondary">Gross {{ $weight($weights['gross']) }}</div>
                </div>
            </div>
        </div>
        @foreach ($weights['rows'] as $row)
            <div class="col-md-3 col-6">
                <div class="card stat-card h-100">
                    <div class="card-body">
                        <div class="stat-label">{{ $row['label'] }}</div>
                        <div class="fw-semibold">{{ $weight($row['net']) }}</div>
                        <div class="small text-secondary">Gross {{ $weight($row['gross']) }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
