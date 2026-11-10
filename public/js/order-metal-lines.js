/**
 * Multi-line metal rows on advance order create / edit.
 * Expects #order-lines, #order-line-template, #order-config JSON, and optional #estimated-making.
 */
(function () {
    function round2(value) {
        return Math.round((value + Number.EPSILON) * 100) / 100;
    }

    function selectedOption(select) {
        return select.options[select.selectedIndex];
    }

    function initOrderMetalLines(order, hooks) {
        const container = document.getElementById('order-lines');
        const template = document.getElementById('order-line-template');
        const addBtn = document.getElementById('add-order-line');
        if (!container || !template || !order) {
            return;
        }

        const money = new Intl.NumberFormat('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const rateEdited = new WeakMap();
        const maxLines = 8;

        function currentRate(metalId, purityId) {
            const rows = order.rates.filter(
                (rate) => String(rate.metal_type_id) === String(metalId) && String(rate.purity_id) === String(purityId),
            );
            return (
                rows.find((rate) => String(rate.branch_id) === String(order.branchId))
                || rows.find((rate) => rate.branch_id === null)
                || null
            );
        }

        function syncPurities(row) {
            const metal = selectedOption(row.querySelector('.order-line-metal'));
            const purity = row.querySelector('.order-line-purity');
            if (!metal || !purity) {
                return;
            }
            Array.from(purity.options).forEach((option) => {
                const show = option.dataset.metal === metal.dataset.id;
                option.hidden = !show;
                option.disabled = !show;
            });
            if (purity.selectedOptions[0]?.disabled) {
                const first = Array.from(purity.options).find((option) => !option.disabled);
                if (first) {
                    first.selected = true;
                }
            }
        }

        function reindexLines() {
            container.querySelectorAll('.order-line').forEach((row, index) => {
                row.dataset.index = String(index);
                row.querySelector('.order-line-label').textContent = 'Line ' + (index + 1);
                row.querySelectorAll('[name^="lines["]').forEach((field) => {
                    field.name = field.name.replace(/lines\[\d+\]/, 'lines[' + index + ']');
                });
                const remove = row.querySelector('.order-line-remove');
                if (remove) {
                    remove.classList.toggle('d-none', container.querySelectorAll('.order-line').length <= 1);
                }
            });
            if (addBtn) {
                addBtn.disabled = container.querySelectorAll('.order-line').length >= maxLines;
            }
        }

        function lineTotals() {
            let totalWeight = 0;
            let totalMetal = 0;
            let anyMissingRate = false;
            const rateLabels = [];

            container.querySelectorAll('.order-line').forEach((row) => {
                syncPurities(row);
                const metal = selectedOption(row.querySelector('.order-line-metal'));
                const purity = selectedOption(row.querySelector('.order-line-purity'));
                const rateField = row.querySelector('.order-line-rate');
                const edited = rateEdited.get(rateField) === true;
                const rateRow = metal && purity ? currentRate(metal.dataset.id, purity.dataset.id) : null;
                if (!edited && rateField) {
                    rateField.value = rateRow ? Number(rateRow.rate_per_gram).toFixed(2) : '';
                }
                const typed = Number(String(rateField?.value || '').replace(/,/g, ''));
                const rateValue = Number.isFinite(typed) && typed > 0 ? typed : 0;
                const weight = Number(row.querySelector('.order-line-weight')?.value || 0);
                const metalValue = round2(weight * rateValue);
                totalWeight += weight;
                totalMetal += metalValue;
                if (weight > 0 && rateValue <= 0) {
                    anyMissingRate = true;
                }
                const valueField = row.querySelector('.order-line-value');
                if (valueField) {
                    valueField.value = rateValue > 0 && weight > 0 ? money.format(metalValue) : '';
                }
                if (metal && purity && rateValue > 0) {
                    rateLabels.push(metal.text + ' ' + purity.text + ' @ ' + money.format(rateValue));
                }
                row.querySelector('.order-line-making').value = '0';
            });

            return {
                totalWeight,
                totalMetal: round2(totalMetal),
                anyMissingRate,
                rateLabels,
            };
        }

        function render() {
            const totals = lineTotals();
            const missing = document.getElementById('rate-missing');
            if (missing) {
                missing.classList.toggle('d-none', !totals.anyMissingRate);
                missing.textContent = totals.anyMissingRate
                    ? 'Enter the rate per gram on each line. Today’s rate fills in when it is set.'
                    : '';
            }
            if (typeof hooks?.onTotals === 'function') {
                hooks.onTotals(totals);
            }
        }

        function addLine() {
            if (container.querySelectorAll('.order-line').length >= maxLines) {
                return;
            }
            const html = template.innerHTML.replace(/__INDEX__/g, String(container.querySelectorAll('.order-line').length));
            const wrap = document.createElement('div');
            wrap.innerHTML = html.trim();
            const row = wrap.firstElementChild;
            container.appendChild(row);
            reindexLines();
            render();
        }

        container.addEventListener('click', (event) => {
            const remove = event.target.closest('.order-line-remove');
            if (!remove) {
                return;
            }
            const row = remove.closest('.order-line');
            if (row && container.querySelectorAll('.order-line').length > 1) {
                row.remove();
                reindexLines();
                render();
            }
        });

        container.addEventListener('change', (event) => {
            if (event.target.classList.contains('order-line-metal') || event.target.classList.contains('order-line-purity')) {
                const row = event.target.closest('.order-line');
                const rateField = row?.querySelector('.order-line-rate');
                if (rateField) {
                    rateEdited.set(rateField, false);
                }
            }
            if (event.target.classList.contains('order-line-rate')) {
                rateEdited.set(event.target, true);
            }
            render();
        });

        container.addEventListener('input', (event) => {
            if (event.target.classList.contains('order-line-rate')) {
                rateEdited.set(event.target, true);
            }
            render();
        });

        if (addBtn) {
            addBtn.addEventListener('click', addLine);
        }

        container.querySelectorAll('.order-line-rate').forEach((field) => {
            if (String(field.value || '').trim() !== '') {
                rateEdited.set(field, true);
            }
        });

        reindexLines();
        render();

        return { render, lineTotals };
    }

    window.initOrderMetalLines = initOrderMetalLines;
})();
