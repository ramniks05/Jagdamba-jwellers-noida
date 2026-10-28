/**
 * Counter numeric fields: empty zero defaults on focus so typing does not become "05" or "0.500".
 * Blur leaves the field empty; server-side treats blank as zero where needed.
 */
(function () {
    const SELECTOR = [
        'input.counter-num',
        'input.metal-net:not([readonly])',
        'input.charge-value:not([readonly]):not([disabled])',
        'input.bill-weight:not([readonly])',
        'input.bill-pay',
        'input.stone-weight',
        'input.stone-value',
        'input.stone-rate',
        '#discount',
        '#customer-pays',
        '#retail-price',
        '#retail-tax-percent',
        '#retail-tax-amount',
        '#estimate',
        'input[data-counter-num]',
    ].join(', ');

    function matches(input) {
        return input instanceof HTMLInputElement
            && !input.hidden
            && input.type !== 'hidden'
            && input.type !== 'date'
            && input.type !== 'datetime-local'
            && input.matches(SELECTOR);
    }

    function isZeroLike(value) {
        const trimmed = String(value ?? '').trim().replace(/,/g, '');
        if (trimmed === '') {
            return true;
        }

        return /^0+(?:\.0*)?$/.test(trimmed);
    }

    document.addEventListener('focusin', (event) => {
        const input = event.target;
        if (!matches(input)) {
            return;
        }

        if (isZeroLike(input.value)) {
            input.value = '';
            input.dataset.counterWasZero = '1';
        } else if (typeof input.select === 'function') {
            requestAnimationFrame(() => input.select());
        }
    });

    document.addEventListener('focusout', (event) => {
        const input = event.target;
        if (!matches(input)) {
            return;
        }

        delete input.dataset.counterWasZero;
    });
})();
