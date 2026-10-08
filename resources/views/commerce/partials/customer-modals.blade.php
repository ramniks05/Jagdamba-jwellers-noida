<div class="modal fade" id="customer-modal" tabindex="-1" aria-labelledby="customer-modal-title" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="customer-modal-title">New customer</h2>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                @isset($note)
                    <p class="text-secondary small">{{ $note }}</p>
                @endisset
                <div class="mb-3">
                    <label class="form-label" for="new-customer-name">Name</label>
                    <input class="form-control" id="new-customer-name" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="new-customer-mobile">Mobile</label>
                    <input class="form-control" id="new-customer-mobile" inputmode="numeric">
                </div>
                <div class="text-danger small d-none" id="customer-modal-error"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" id="save-customer" type="button">Save and use on this {{ $document }}</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="customer-details-modal" tabindex="-1" aria-labelledby="customer-details-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title h5" id="customer-details-title">Customer</h2>
                    <div class="small text-secondary" id="customer-details-sub"></div>
                </div>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="customer-details-body"></div>
            <div class="modal-footer">
                <a class="btn btn-outline-secondary d-none" id="customer-details-edit" href="#" target="_blank" rel="noopener"><i class="bi bi-pencil"></i> Edit</a>
                <a class="btn btn-outline-secondary d-none" id="customer-details-open" href="#" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> Full page</a>
                <button class="btn btn-primary" type="button" data-bs-dismiss="modal">Back to {{ $document }}</button>
            </div>
        </div>
    </div>
</div>
