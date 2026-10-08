<div class="card mb-3">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span>Customer</span>
        <button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#customer-modal"><i class="bi bi-person-plus"></i> New customer</button>
    </div>
    <div class="card-body">
        <div id="customer-find">
            <label class="form-label" for="customer-search">Find customer</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input class="form-control" id="customer-search" placeholder="Type mobile number or name" autocomplete="off">
                <button class="btn btn-outline-secondary d-none" type="button" id="customer-walkin"><i class="bi bi-person"></i> Walk-in</button>
            </div>
            <div class="form-text">Press <strong>{{ $addLabel }}</strong> on the right customer, or Enter for the first one.</div>
            <div class="bill-results mt-2" id="customer-results"></div>
        </div>
        <div class="customer-chip d-none" id="customer-chosen"></div>
        <div class="text-danger small mt-2 d-none" id="customer-error">{{ $errorText ?? 'Choose a customer.' }}</div>
    </div>
</div>
