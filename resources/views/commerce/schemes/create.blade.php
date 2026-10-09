@extends('layouts.app')

@section('title', 'New scheme')

@section('content')
    <h1 class="page-title h3 mb-1">New scheme</h1>
    <p class="text-secondary mb-3">The customer pays every month. At the end they get what they paid plus the shop bonus, credited for use on a bill.</p>
    <form method="POST" action="{{ route('schemes.store') }}" id="scheme-form" autocomplete="off">
        @csrf
        <input type="hidden" name="is_active" value="1">
        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card mb-3">
                    <div class="card-header bg-white">Scheme</div>
                    <div class="card-body">
                        <div class="weigh-section-title">Name</div>
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label" for="code">Code</label>
                                <input class="form-control text-uppercase @error('code') is-invalid @enderror" id="code" name="code" value="{{ old('code') }}" placeholder="GOLD11" maxlength="20" required>
                                @error('code')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-8">
                                <label class="form-label" for="name">Name</label>
                                <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" placeholder="11 month gold" maxlength="120" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="weigh-section">
                            <div class="weigh-section-title">Installments</div>
                            <div class="btn-group w-100 mb-2" role="group" aria-label="Installment">
                                <input class="btn-check" type="radio" name="installment_mode" id="mode-fixed" value="fixed" @checked(old('installment_mode', 'fixed') === 'fixed')>
                                <label class="btn btn-outline-primary" for="mode-fixed">Fixed monthly amount</label>
                                <input class="btn-check" type="radio" name="installment_mode" id="mode-variable" value="variable" @checked(old('installment_mode') === 'variable')>
                                <label class="btn btn-outline-primary" for="mode-variable">Customer chooses the amount</label>
                            </div>
                            <div class="row g-2">
                                <div class="col-6" id="monthly-wrap">
                                    <label class="form-label" for="monthly">Each month ₹</label>
                                    <input class="form-control @error('monthly_amount') is-invalid @enderror" id="monthly" name="monthly_amount" value="{{ old('monthly_amount') }}" inputmode="decimal" placeholder="5000">
                                    @error('monthly_amount')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-6">
                                    <label class="form-label" for="months">Months</label>
                                    <input class="form-control @error('duration_months') is-invalid @enderror" id="months" name="duration_months" value="{{ old('duration_months', '11') }}" inputmode="numeric" required>
                                    @error('duration_months')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="bill-products mt-2" id="month-chips">
                                @foreach ([6, 11, 12] as $count)
                                    <button type="button" data-months="{{ $count }}">{{ $count }} months</button>
                                @endforeach
                            </div>
                        </div>
                        <div class="weigh-section">
                            <div class="weigh-section-title">Bonus at the end</div>
                            <div class="row g-2">
                                <div class="col-md-7">
                                    <label class="form-label" for="bonus-type">Bonus</label>
                                    <select class="form-select" id="bonus-type" name="bonus_type">
                                        @foreach ($bonuses as $code => $label)
                                            <option value="{{ $code }}" @selected(old('bonus_type', 'extra_installment') === $code)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-5" id="bonus-value-wrap">
                                    <label class="form-label" id="bonus-value-label" for="bonus-value">Bonus value</label>
                                    <input class="form-control @error('bonus_value') is-invalid @enderror" id="bonus-value" name="bonus_value" value="{{ old('bonus_value', '0') }}" inputmode="decimal">
                                    @error('bonus_value')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5 bill-side">
                <div class="card mb-3">
                    <div class="card-header bg-white">What the customer gets</div>
                    <div class="card-body bill-sums">
                        <div class="weigh-quote is-empty" id="scheme-empty">Enter the monthly amount and the months</div>
                        <div class="d-none" id="scheme-sums">
                            <div class="bill-block mt-0">
                                <div class="bill-row bill-row-muted"><span>Months</span><span id="sum-months">0</span></div>
                                <div class="bill-row bill-row-muted"><span>Each month</span><span id="sum-monthly">0.00</span></div>
                                <div class="bill-row bill-row-sub"><span>Customer pays</span><span id="sum-paid">0.00</span></div>
                            </div>
                            <div class="bill-block">
                                <div class="bill-row"><span id="sum-bonus-label">Bonus</span><span id="sum-bonus">0.00</span></div>
                            </div>
                            <div class="bill-grand"><span>Customer gets</span><strong id="sum-total">0.00</strong></div>
                        </div>
                        <p class="text-secondary small mb-0 mt-2" id="scheme-note"></p>
                    </div>
                </div>
                <button class="btn btn-primary w-100" type="submit"><i class="bi bi-piggy-bank"></i> Save scheme</button>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        const money = new Intl.NumberFormat('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const $ = (id) => document.getElementById(id);
        const round2 = (value) => Math.round((value + Number.EPSILON) * 100) / 100;

        function stop(message) {
            $('scheme-empty').textContent = message;
            $('scheme-empty').classList.remove('d-none');
            $('scheme-sums').classList.add('d-none');
            $('scheme-note').textContent = '';
        }

        function renderScheme() {
            const fixed = $('mode-fixed').checked;
            const bonusType = $('bonus-type').value;
            const months = Math.max(0, parseInt($('months').value || '0', 10));
            const monthly = Number($('monthly').value || 0);
            const bonusValue = Math.max(0, Number($('bonus-value').value || 0));
            $('monthly-wrap').classList.toggle('d-none', !fixed);
            $('bonus-value-wrap').classList.toggle('d-none', bonusType === 'extra_installment');
            $('bonus-value-label').textContent = bonusType === 'percent' ? 'Bonus %' : 'Bonus ₹';
            document.querySelectorAll('#month-chips button').forEach((button) => button.classList.toggle('active', Number(button.dataset.months) === months));

            if (bonusType === 'extra_installment' && !fixed) return stop('One extra installment needs a fixed monthly amount');
            if (months < 1) return stop('Enter the number of months');

            if (!fixed) {
                $('scheme-empty').textContent = months + ' months · the customer chooses the amount each month';
                $('scheme-empty').classList.remove('d-none');
                $('scheme-sums').classList.add('d-none');
                $('scheme-note').textContent = bonusType === 'percent'
                    ? 'Bonus ' + bonusValue + '% of whatever they pay. The final amount is known after every month is paid.'
                    : 'Bonus ₹' + money.format(bonusValue) + ' added at the end. The final amount is known after every month is paid.';
                return;
            }
            if (monthly <= 0) return stop('Enter the monthly amount');

            const paid = round2(months * monthly);
            let bonus = bonusValue;
            let label = 'Bonus';
            if (bonusType === 'extra_installment') {
                bonus = monthly;
                label = 'Bonus · one extra month';
            } else if (bonusType === 'percent') {
                bonus = round2(paid * bonusValue / 100);
                label = 'Bonus · ' + bonusValue + '%';
            }
            $('scheme-empty').classList.add('d-none');
            $('scheme-sums').classList.remove('d-none');
            $('sum-months').textContent = String(months);
            $('sum-monthly').textContent = money.format(monthly);
            $('sum-paid').textContent = money.format(paid);
            $('sum-bonus-label').textContent = label;
            $('sum-bonus').textContent = money.format(bonus);
            $('sum-total').textContent = money.format(round2(paid + bonus));
            $('scheme-note').textContent = 'Credited to the customer when every month is paid. They can use it on a bill.';
        }

        $('month-chips').addEventListener('click', (event) => {
            const button = event.target.closest('button');
            if (!button) return;
            $('months').value = button.dataset.months;
            renderScheme();
        });
        $('scheme-form').addEventListener('input', renderScheme);
        $('scheme-form').addEventListener('change', renderScheme);
        renderScheme();
    </script>
@endpush
