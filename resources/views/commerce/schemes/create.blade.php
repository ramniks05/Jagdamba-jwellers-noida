@extends('layouts.app')

@section('title', 'New scheme')

@section('content')
    <h1 class="page-title h3 mb-2">New scheme</h1>
    <p class="text-secondary">A fixed scheme collects the same amount every month. At the end the customer gets the amount they paid, plus the shop bonus. The total is shown before you save.</p>
    <form method="POST" action="{{ route('schemes.store') }}" id="scheme-form">
        @csrf
        <input type="hidden" name="is_active" value="1">
        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card mb-3">
                    <div class="card-header bg-white">Scheme</div>
                    <div class="card-body row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="code">Code</label>
                            <input class="form-control" id="code" name="code" value="{{ old('code') }}" placeholder="GOLD11" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="name">Name</label>
                            <input class="form-control" id="name" name="name" value="{{ old('name') }}" placeholder="11 month gold" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="mode">Installment</label>
                            <select class="form-select" id="mode" name="installment_mode">
                                <option value="fixed" @selected(old('installment_mode', 'fixed') === 'fixed')>Fixed monthly amount</option>
                                <option value="variable" @selected(old('installment_mode') === 'variable')>Customer chooses the amount</option>
                            </select>
                        </div>
                        <div class="col-md-3" id="monthly-wrap">
                            <label class="form-label" for="monthly">Monthly amount</label>
                            <input class="form-control" id="monthly" name="monthly_amount" value="{{ old('monthly_amount') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="months">Months</label>
                            <input class="form-control" id="months" name="duration_months" value="{{ old('duration_months', '11') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="bonus-type">Bonus</label>
                            <select class="form-select" id="bonus-type" name="bonus_type">
                                @foreach ($bonuses as $code => $label)
                                    <option value="{{ $code }}" @selected(old('bonus_type', 'extra_installment') === $code)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6" id="bonus-value-wrap">
                            <label class="form-label" id="bonus-value-label" for="bonus-value">Bonus value</label>
                            <input class="form-control" id="bonus-value" name="bonus_value" value="{{ old('bonus_value', '0') }}">
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5 bill-side">
                <div class="card mb-3">
                    <div class="card-header bg-white">What the customer gets</div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush mb-3" id="scheme-lines"></ul>
                        <p class="fw-semibold mb-0" id="scheme-note"></p>
                    </div>
                </div>
                <button class="btn btn-primary w-100" type="submit"><i class="bi bi-piggy-bank"></i> Save scheme</button>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        const money = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' });

        function round2(value) {
            return Math.round((value + Number.EPSILON) * 100) / 100;
        }

        function renderScheme() {
            const mode = document.getElementById('mode').value;
            const bonusType = document.getElementById('bonus-type').value;
            const months = Math.max(0, parseInt(document.getElementById('months').value || '0', 10));
            const monthly = Number(document.getElementById('monthly').value || 0);
            const bonusValue = Math.max(0, Number(document.getElementById('bonus-value').value || 0));
            document.getElementById('monthly-wrap').classList.toggle('d-none', mode !== 'fixed');
            document.getElementById('bonus-value-wrap').classList.toggle('d-none', bonusType === 'extra_installment');
            document.getElementById('bonus-value-label').textContent = bonusType === 'percent' ? 'Bonus percent' : 'Bonus amount';
            const note = document.getElementById('scheme-note');
            const list = document.getElementById('scheme-lines');

            if (bonusType === 'extra_installment' && mode !== 'fixed') {
                list.innerHTML = '';
                note.textContent = 'One extra installment needs a fixed monthly amount.';
                return;
            }
            if (mode === 'variable') {
                const bonus = bonusType === 'percent'
                    ? bonusValue + '% of whatever they pay'
                    : money.format(bonusValue) + ' added at the end';
                list.innerHTML = [
                    ['Months', String(months)],
                    ['Each month', 'The amount they choose'],
                    ['Bonus', bonus],
                ].map(line).join('');
                note.textContent = 'The final amount is known after every month is paid.';
                return;
            }
            if (months < 1 || monthly <= 0) {
                list.innerHTML = '';
                note.textContent = 'Enter the monthly amount and the number of months.';
                return;
            }

            const paid = round2(months * monthly);
            let bonus = 0;
            let bonusLabel = money.format(0);
            if (bonusType === 'extra_installment') {
                bonus = monthly;
                bonusLabel = 'One extra month';
            } else if (bonusType === 'percent') {
                bonus = round2(paid * bonusValue / 100);
                bonusLabel = bonusValue + '%';
            } else {
                bonus = bonusValue;
                bonusLabel = money.format(bonusValue);
            }
            const total = round2(paid + bonus);
            list.innerHTML = [
                ['Months', String(months)],
                ['Monthly', monthly],
                ['Customer pays', paid],
                ['Bonus', bonusType === 'extra_installment' || bonusType === 'percent' ? bonusLabel + ' · ' + money.format(bonus) : bonusLabel],
                ['Customer gets', total],
            ].map(line).join('');
            note.textContent = 'This amount is credited to the customer when the scheme matures.';
        }

        function line(row) {
            const value = typeof row[1] === 'number' ? money.format(row[1]) : row[1];
            const strong = row[0] === 'Customer gets' ? ' fw-semibold' : '';
            return '<li class="list-group-item d-flex justify-content-between px-0' + strong + '"><span>' + row[0] + '</span><span>' + value + '</span></li>';
        }

        document.getElementById('scheme-form').addEventListener('input', renderScheme);
        document.getElementById('scheme-form').addEventListener('change', renderScheme);
        renderScheme();
    </script>
@endpush
