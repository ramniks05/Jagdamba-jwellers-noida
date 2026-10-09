@extends('layouts.app')

@section('title', 'Shop profile')

@section('content')
    @include('masters.partials.head', [
        'title' => 'Shop profile',
        'intro' => 'Your shop name, GSTIN and address are printed at the top of every bill.',
        'actions' => [
            ['url' => route('settings.edit'), 'label' => 'Settings', 'icon' => 'sliders'],
            ['url' => route('branches.index'), 'label' => 'Branches', 'icon' => 'diagram-3'],
        ],
    ])
    <form method="POST" action="{{ route('company.update') }}" enctype="multipart/form-data" id="company-form">
        @csrf
        @method('PUT')
        <div class="row g-3 align-items-start">
            <div class="col-xl-8">
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="weigh-section-title"><i class="bi bi-shop"></i> Shop</div>
                        <div class="row g-3">
                            @include('masters.partials.field', ['name' => 'name', 'label' => 'Shop name', 'value' => $company->name, 'col' => 'col-md-6'])
                            @include('masters.partials.field', ['name' => 'legal_name', 'label' => 'Legal name', 'value' => $company->legal_name, 'col' => 'col-md-6', 'required' => false, 'help' => 'Only if the firm name on GST is different.'])
                            @include('masters.partials.field', ['name' => 'code', 'label' => 'Shop code', 'value' => $company->code, 'col' => 'col-md-4', 'class' => 'text-uppercase'])
                            @include('masters.partials.field', ['name' => 'gstin', 'label' => 'GSTIN', 'value' => $company->gstin, 'col' => 'col-md-4', 'class' => 'text-uppercase', 'maxlength' => 15, 'required' => false, 'placeholder' => '09ABCDE1234F1Z5'])
                            @include('masters.partials.field', ['name' => 'pan', 'label' => 'PAN', 'value' => $company->pan, 'col' => 'col-md-4', 'class' => 'text-uppercase', 'maxlength' => 10, 'required' => false, 'placeholder' => 'ABCDE1234F'])
                        </div>

                        <div class="weigh-section-title mt-4"><i class="bi bi-telephone"></i> Contact</div>
                        <div class="row g-3">
                            @include('masters.partials.field', ['name' => 'mobile', 'label' => 'Mobile', 'value' => $company->mobile, 'col' => 'col-md-4', 'inputmode' => 'tel', 'required' => false])
                            @include('masters.partials.field', ['name' => 'phone', 'label' => 'Phone', 'value' => $company->phone, 'col' => 'col-md-4', 'inputmode' => 'tel', 'required' => false])
                            @include('masters.partials.field', ['name' => 'email', 'label' => 'Email', 'value' => $company->email, 'col' => 'col-md-4', 'type' => 'email', 'required' => false])
                            @include('masters.partials.field', ['name' => 'website', 'label' => 'Website', 'value' => $company->website, 'col' => 'col-md-8', 'type' => 'url', 'required' => false, 'placeholder' => 'https://'])
                        </div>

                        <div class="weigh-section-title mt-4"><i class="bi bi-geo-alt"></i> Address</div>
                        <div class="row g-3">
                            @include('masters.partials.field', ['name' => 'address_line1', 'label' => 'Address line 1', 'value' => $company->address_line1, 'col' => 'col-md-6'])
                            @include('masters.partials.field', ['name' => 'address_line2', 'label' => 'Address line 2', 'value' => $company->address_line2, 'col' => 'col-md-6', 'required' => false])
                            @include('masters.partials.field', ['name' => 'city', 'label' => 'City', 'value' => $company->city, 'col' => 'col-6 col-md-3'])
                            @include('masters.partials.field', ['name' => 'state', 'label' => 'State', 'value' => $company->state, 'col' => 'col-6 col-md-3'])
                            @include('masters.partials.field', ['name' => 'postal_code', 'label' => 'PIN code', 'value' => $company->postal_code, 'col' => 'col-6 col-md-3', 'inputmode' => 'numeric', 'maxlength' => 10])
                            @include('masters.partials.field', ['name' => 'country', 'label' => 'Country', 'value' => $company->country, 'col' => 'col-6 col-md-3'])
                        </div>

                        <div class="weigh-section-title mt-4"><i class="bi bi-globe2"></i> Region and books</div>
                        <div class="row g-3">
                            <div class="col-md-5">
                                <label class="form-label" for="timezone">Timezone</label>
                                <input class="form-control @error('timezone') is-invalid @enderror" id="timezone" name="timezone" value="{{ old('timezone', $company->timezone) }}" list="timezone-list" required>
                                <datalist id="timezone-list">
                                    @foreach ($timezones as $zone)
                                        <option value="{{ $zone }}">
                                    @endforeach
                                </datalist>
                                @error('timezone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            @include('masters.partials.field', ['name' => 'currency_code', 'label' => 'Currency', 'value' => $company->currency_code, 'col' => 'col-6 col-md-3', 'class' => 'text-uppercase', 'maxlength' => 3])
                            <div class="col-6 col-md-4">
                                <label class="form-label" for="fy_start_month">Financial year starts</label>
                                <select class="form-select @error('fy_start_month') is-invalid @enderror" id="fy_start_month" name="fy_start_month" required>
                                    @foreach ($months as $number => $label)
                                        <option value="{{ $number }}" @selected((int) old('fy_start_month', $company->fy_start_month) === $number)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text">Used when the next year is suggested.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4 bill-side">
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="weigh-section-title"><i class="bi bi-receipt"></i> On the bill</div>
                        <div class="shop-bill-head">
                            <img src="{{ $company->brandLogoUrl() }}" alt="" class="shop-bill-logo" id="logo-preview">
                            <div>
                                <div class="fw-bold" id="head-name">{{ $company->name }}</div>
                                <div class="small text-secondary" id="head-address"></div>
                                <div class="small" id="head-contact"></div>
                                <div class="small fw-semibold" id="head-gstin"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-body">
                        <div class="weigh-section-title"><i class="bi bi-image"></i> Logo</div>
                        @if ($company->logo_path)
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" role="switch" id="remove_logo" name="remove_logo" value="1">
                                <label class="form-check-label" for="remove_logo">Remove this logo</label>
                            </div>
                        @else
                            <div class="form-text mb-2">The JD mark is used until you upload your own logo.</div>
                        @endif
                        <input class="form-control @error('logo') is-invalid @enderror" id="logo" name="logo" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" aria-label="Upload logo">
                        @error('logo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">JPEG, PNG or WebP, up to 2 MB. Printed when “Show the shop logo” is on in <a href="{{ route('settings.edit') }}">Settings</a>.</div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-body">
                        <div class="weigh-section-title"><i class="bi bi-pen"></i> Signature on the bill</div>
                        <img src="{{ $company->brandSignatureUrl() }}" alt="Authorised signature" class="shop-signature" id="signature-preview">
                        @if ($company->signature_path)
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" role="switch" id="remove_signature" name="remove_signature" value="1">
                                <label class="form-check-label" for="remove_signature">Remove this signature</label>
                            </div>
                        @else
                            <div class="form-text mb-2">This is a sample signature. Upload your own to replace it.</div>
                        @endif
                        <button class="btn btn-outline-secondary" id="signature-open" type="button"><i class="bi bi-upload"></i> Choose signature</button>
                        <input class="d-none" id="signature" name="signature" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                        @error('signature')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        <div class="form-text mt-2" id="signature-picked">Sign on plain white paper and take a photo. The white background is removed so only the ink prints.</div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body d-grid">
                        <button class="btn btn-primary btn-lg" type="submit"><i class="bi bi-check2"></i> Save profile</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        (() => {
            const form = document.getElementById('company-form');
            const val = (name) => (form.elements[name]?.value || '').trim();

            const refresh = () => {
                document.getElementById('head-name').textContent = val('name') || 'Shop name';
                document.getElementById('head-address').textContent = [val('address_line1'), val('address_line2'), [val('city'), val('state'), val('postal_code')].filter(Boolean).join(' ')].filter(Boolean).join(', ');
                document.getElementById('head-contact').textContent = [val('mobile'), val('phone'), val('email')].filter(Boolean).join(' · ');
                const gstin = val('gstin').toUpperCase();
                document.getElementById('head-gstin').textContent = gstin ? `GSTIN ${gstin}` : '';
            };
            form.addEventListener('input', refresh);
            refresh();

            const showPicked = (input, img) => {
                const file = input.files && input.files[0];
                if (file && file.type.startsWith('image/')) img.src = URL.createObjectURL(file);
                return file;
            };
            document.getElementById('logo').addEventListener('change', function () {
                showPicked(this, document.getElementById('logo-preview'));
            });
            document.getElementById('signature-open').addEventListener('click', () => document.getElementById('signature').click());
            document.getElementById('signature').addEventListener('change', function () {
                const file = showPicked(this, document.getElementById('signature-preview'));
                document.getElementById('signature-picked').textContent = file
                    ? `Selected ${file.name}. Click Save profile to put it on the bill.`
                    : 'Sign on plain white paper and take a photo. The white background is removed so only the ink prints.';
            });
        })();
    </script>
@endpush
