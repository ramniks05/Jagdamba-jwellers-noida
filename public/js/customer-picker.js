function customerPicker(options) {
    const settings = Object.assign({
        customers: [],
        createUrl: null,
        showUrl: null,
        csrf: '',
        addLabel: 'Add to bill',
        chipLabel: 'Bill to',
        onChange: () => {},
    }, options);
    const $ = (id) => document.getElementById(id);
    let current = null;

    function dueMark(row) {
        const text = row.due || row.advance;
        if (!text) return document.createTextNode('');
        const mark = document.createElement('span');
        mark.className = row.due ? 'customer-due' : 'customer-due is-advance';
        mark.textContent = ' (' + text + ')';
        return mark;
    }

    function premiumStar(row) {
        if (!row.is_premium) return document.createTextNode('');
        const star = document.createElement('i');
        star.className = 'bi bi-star-fill customer-premium-star';
        star.title = 'Premium customer';
        star.setAttribute('aria-label', 'Premium customer');
        return star;
    }

    function matches(query, values) {
        const needle = query.trim().toLowerCase();
        if (needle === '') return false;
        return values.some((value) => String(value || '').toLowerCase().includes(needle));
    }

    function found() {
        const query = $('customer-search').value;
        return settings.customers.filter((row) => matches(query, [row.name, row.mobile, row.code])).slice(0, 8);
    }

    function resultRow(row) {
        const button = document.createElement('button');
        button.type = 'button';
        const info = document.createElement('span');
        info.className = 'result-info';
        const name = document.createElement('strong');
        name.append(premiumStar(row), document.createTextNode(row.name), dueMark(row));
        const detail = document.createElement('small');
        detail.textContent = [row.mobile, row.code].filter(Boolean).join(' · ');
        info.append(name, detail);
        const add = document.createElement('span');
        add.className = 'result-add';
        add.innerHTML = '<i class="bi bi-plus-lg"></i> ';
        add.append(settings.addLabel);
        button.append(info, add);
        button.addEventListener('click', () => choose(row));
        return button;
    }

    function render() {
        const query = $('customer-search').value.trim();
        const box = $('customer-results');
        if (!box) return;
        box.innerHTML = '';
        if (query === '') return;
        const rows = found();
        if (rows.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'result-empty';
            const text = document.createElement('span');
            text.textContent = 'No customer found for "' + query + '"';
            const create = document.createElement('button');
            create.type = 'button';
            create.className = 'btn btn-primary btn-sm';
            create.innerHTML = '<i class="bi bi-person-plus"></i> Add as new customer';
            create.addEventListener('click', () => {
                const isMobile = /^[0-9+\s-]+$/.test(query);
                $('new-customer-mobile').value = isMobile ? query : '';
                $('new-customer-name').value = isMobile ? '' : query;
                bootstrap.Modal.getOrCreateInstance($('customer-modal')).show();
            });
            empty.append(text, create);
            box.appendChild(empty);
            return;
        }
        rows.forEach((row) => box.appendChild(resultRow(row)));
    }

    function choose(row) {
        current = row;
        $('customer-uuid').value = row.uuid;
        const chosen = $('customer-chosen');
        chosen.classList.remove('d-none');
        chosen.replaceChildren();
        const icon = document.createElement('i');
        icon.className = 'bi bi-person-check-fill customer-chip-icon';
        const info = document.createElement('div');
        info.className = 'customer-chip-info';
        const label = document.createElement('small');
        label.textContent = settings.chipLabel;
        const name = document.createElement('strong');
        name.append(premiumStar(row), document.createTextNode(row.name), dueMark(row));
        const meta = document.createElement('span');
        meta.textContent = [row.mobile, row.code].filter(Boolean).join(' · ');
        info.append(label, name, meta);
        const actions = document.createElement('div');
        actions.className = 'customer-chip-actions';
        if (settings.showUrl && !row.walkin) {
            const details = document.createElement('button');
            details.type = 'button';
            details.className = 'btn btn-outline-primary btn-sm';
            details.innerHTML = '<i class="bi bi-person-vcard"></i> Details';
            details.addEventListener('click', () => showDetails(row));
            actions.appendChild(details);
        }
        const change = document.createElement('button');
        change.type = 'button';
        change.className = 'btn btn-outline-secondary btn-sm';
        change.innerHTML = '<i class="bi bi-arrow-repeat"></i> Change';
        change.addEventListener('click', clear);
        actions.appendChild(change);
        chosen.append(icon, info, actions);
        $('customer-find').classList.add('d-none');
        $('customer-error').classList.add('d-none');
        $('customer-results').innerHTML = '';
        $('customer-search').value = '';
        settings.onChange(row);
    }

    function clear() {
        current = null;
        $('customer-uuid').value = '';
        $('customer-chosen').classList.add('d-none');
        $('customer-find').classList.remove('d-none');
        $('customer-search').focus();
        settings.onChange(null);
    }

    function detailItem(label, value) {
        const item = document.createElement('div');
        const name = document.createElement('div');
        name.className = 'stat-label';
        name.textContent = label;
        const text = document.createElement('div');
        if (Array.isArray(value)) {
            value.forEach((line) => {
                const row = document.createElement('div');
                row.textContent = line;
                text.appendChild(row);
            });
            if (value.length === 0) text.textContent = '—';
        } else {
            text.textContent = value || '—';
        }
        item.append(name, text);
        return item;
    }

    async function showDetails(row) {
        const body = $('customer-details-body');
        $('customer-details-title').textContent = row.name;
        $('customer-details-sub').textContent = [row.mobile, row.code].filter(Boolean).join(' · ');
        $('customer-details-edit').classList.add('d-none');
        $('customer-details-open').classList.add('d-none');
        body.innerHTML = '<div class="text-secondary small">Loading…</div>';
        bootstrap.Modal.getOrCreateInstance($('customer-details-modal')).show();
        let data;
        try {
            const response = await fetch(settings.showUrl.replace('__customer__', row.uuid), { headers: { 'Accept': 'application/json' } });
            if (!response.ok) throw new Error();
            data = await response.json();
        } catch (error) {
            body.innerHTML = '<div class="text-danger small">Customer details could not be loaded.</div>';
            return;
        }
        body.replaceChildren();
        const stats = document.createElement('div');
        stats.className = 'customer-stats';
        [
            [data.balance_sign < 0 ? 'Advance with shop' : 'Outstanding', data.balance, data.balance_sign > 0 ? 'is-due' : ''],
            ['Bills', String(data.bills_count), ''],
            ['Total bought', data.bills_total, ''],
        ].forEach(([label, value, tone]) => {
            const box = detailItem(label, value);
            box.className = 'customer-stat ' + tone;
            stats.appendChild(box);
        });
        const recentTitle = document.createElement('div');
        recentTitle.className = 'weigh-section-title mt-3 mb-1';
        recentTitle.textContent = 'Recent bills';
        const recent = document.createElement('div');
        recent.className = 'customer-recent';
        if ((data.recent || []).length === 0) {
            recent.innerHTML = '<div class="text-secondary small">No bills yet.</div>';
        }
        (data.recent || []).forEach((sale) => {
            const link = document.createElement('a');
            link.href = sale.url;
            link.target = '_blank';
            link.rel = 'noopener';
            const left = document.createElement('span');
            const number = document.createElement('strong');
            number.textContent = sale.number;
            const when = document.createElement('small');
            when.textContent = sale.when;
            left.append(number, when);
            const total = document.createElement('span');
            total.textContent = sale.total;
            link.append(left, total);
            recent.appendChild(link);
        });
        body.append(stats, recentTitle, recent);
        if (data.details_url) {
            body.appendChild(detailsEditor(row, data));
        }
        const open = $('customer-details-open');
        open.href = data.url;
        open.target = '_blank';
        open.rel = 'noopener';
        open.classList.remove('d-none');
        open.onclick = () => {
            if (typeof settings.onLeave === 'function') settings.onLeave();
        };
        // Edit happens in this modal — no separate edit page needed.
        $('customer-details-edit').classList.add('d-none');
        $('customer-details-edit').onclick = null;
    }

    function fieldBox(col, label, control) {
        const box = document.createElement('div');
        box.className = col;
        const lab = document.createElement('label');
        lab.className = 'form-label';
        lab.textContent = label;
        box.append(lab, control);
        return box;
    }

    function textInput(value, opts = {}) {
        const input = document.createElement('input');
        input.className = 'form-control';
        input.value = value || '';
        input.autocomplete = 'off';
        if (opts.type) input.type = opts.type;
        if (opts.placeholder) input.placeholder = opts.placeholder;
        if (opts.maxLength) input.maxLength = opts.maxLength;
        return input;
    }

    function selectInput(options, value) {
        const select = document.createElement('select');
        select.className = 'form-select';
        (options || []).forEach((option) => {
            const row = document.createElement('option');
            row.value = option.value;
            row.textContent = option.label;
            if (String(option.value) === String(value || '')) row.selected = true;
            select.appendChild(row);
        });
        return select;
    }

    function detailsEditor(row, data) {
        const wrap = document.createElement('div');
        wrap.className = 'customer-details-edit mt-3';
        const title = document.createElement('div');
        title.className = 'weigh-section-title mb-2';
        title.textContent = 'Edit customer';
        const help = document.createElement('p');
        help.className = 'text-secondary small';
        help.textContent = 'Save here so the bill on this page is not cleared.';
        const fields = data.fields || {};
        const options = data.options || {};
        const form = document.createElement('div');
        form.className = 'row g-2';
        const inputs = {};
        const name = textInput(fields.name, { maxLength: 160 });
        name.required = true;
        inputs.name = name;
        inputs.mobile = textInput(fields.mobile, { maxLength: 20 });
        inputs.email = textInput(fields.email, { type: 'email', maxLength: 160 });
        inputs.customer_type = selectInput(options.types, fields.customer_type);
        inputs.kyc_status = selectInput(options.kyc, fields.kyc_status);
        const societies = [{ value: '', label: '— None —' }].concat(options.societies || []);
        inputs.society_id = selectInput(societies, fields.society_id || '');
        inputs.address_line1 = textInput(fields.address_line1, { maxLength: 200 });
        inputs.address_line2 = textInput(fields.address_line2, { maxLength: 200 });
        inputs.city = textInput(fields.city, { maxLength: 100 });
        inputs.state = textInput(fields.state, { maxLength: 100 });
        inputs.postal_code = textInput(fields.postal_code, { maxLength: 12 });
        inputs.pan = textInput(fields.pan, { maxLength: 10 });
        inputs.gstin = textInput(fields.gstin, { maxLength: 15 });
        inputs.dob = textInput(fields.dob, { type: 'date' });
        inputs.anniversary = textInput(fields.anniversary, { type: 'date' });
        inputs.id_proof_type = textInput(fields.id_proof_type, { placeholder: 'Aadhaar', maxLength: 40 });
        inputs.id_proof_number = textInput(fields.id_proof_number, { maxLength: 40 });
        const notes = document.createElement('textarea');
        notes.className = 'form-control';
        notes.rows = 2;
        notes.maxLength = 1000;
        notes.value = fields.notes || '';
        inputs.notes = notes;
        inputs.is_premium = document.createElement('input');
        inputs.is_premium.type = 'checkbox';
        inputs.is_premium.className = 'form-check-input';
        inputs.is_premium.checked = !!fields.is_premium;
        inputs.is_active = document.createElement('input');
        inputs.is_active.type = 'checkbox';
        inputs.is_active.className = 'form-check-input';
        inputs.is_active.checked = fields.is_active !== false;
        if (data.walkin) inputs.is_active.disabled = true;

        form.append(
            fieldBox('col-md-6', 'Name', inputs.name),
            fieldBox('col-md-3', 'Mobile', inputs.mobile),
            fieldBox('col-md-3', 'Email', inputs.email),
            fieldBox('col-md-4', 'Type', inputs.customer_type),
            fieldBox('col-md-4', 'KYC', inputs.kyc_status),
            fieldBox('col-md-4', 'Society', inputs.society_id),
            fieldBox('col-md-6', 'Address line 1', inputs.address_line1),
            fieldBox('col-md-6', 'Address line 2', inputs.address_line2),
            fieldBox('col-md-4', 'City', inputs.city),
            fieldBox('col-md-4', 'State', inputs.state),
            fieldBox('col-md-4', 'PIN', inputs.postal_code),
            fieldBox('col-md-3', 'PAN', inputs.pan),
            fieldBox('col-md-3', 'GSTIN', inputs.gstin),
            fieldBox('col-md-3', 'Date of birth', inputs.dob),
            fieldBox('col-md-3', 'Anniversary', inputs.anniversary),
            fieldBox('col-md-6', 'ID proof', inputs.id_proof_type),
            fieldBox('col-md-6', 'ID number', inputs.id_proof_number),
            fieldBox('col-12', 'Notes', inputs.notes),
        );
        const checks = document.createElement('div');
        checks.className = 'col-12 d-flex flex-wrap gap-3 mt-1';
        const premiumWrap = document.createElement('div');
        premiumWrap.className = 'form-check';
        const premiumLabel = document.createElement('label');
        premiumLabel.className = 'form-check-label';
        premiumLabel.textContent = 'Premium customer';
        premiumWrap.append(inputs.is_premium, premiumLabel);
        const activeWrap = document.createElement('div');
        activeWrap.className = 'form-check';
        const activeLabel = document.createElement('label');
        activeLabel.className = 'form-check-label';
        activeLabel.textContent = 'Active';
        activeWrap.append(inputs.is_active, activeLabel);
        checks.append(premiumWrap, activeWrap);
        form.appendChild(checks);

        const error = document.createElement('div');
        error.className = 'text-danger small d-none mt-2';
        const status = document.createElement('div');
        status.className = 'text-success small d-none mt-2';
        const save = document.createElement('button');
        save.type = 'button';
        save.className = 'btn btn-primary btn-sm mt-2';
        save.innerHTML = '<i class="bi bi-check2"></i> Save customer';
        save.addEventListener('click', async () => {
            error.classList.add('d-none');
            status.classList.add('d-none');
            save.disabled = true;
            try {
                const response = await fetch(data.details_url, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': settings.csrf,
                    },
                    body: JSON.stringify({
                        name: inputs.name.value,
                        mobile: inputs.mobile.value,
                        email: inputs.email.value,
                        customer_type: inputs.customer_type.value,
                        kyc_status: inputs.kyc_status.value,
                        society_id: inputs.society_id.value || null,
                        address_line1: inputs.address_line1.value,
                        address_line2: inputs.address_line2.value,
                        city: inputs.city.value,
                        state: inputs.state.value,
                        postal_code: inputs.postal_code.value,
                        country: fields.country || 'India',
                        pan: inputs.pan.value,
                        gstin: inputs.gstin.value,
                        dob: inputs.dob.value,
                        anniversary: inputs.anniversary.value,
                        id_proof_type: inputs.id_proof_type.value,
                        id_proof_number: inputs.id_proof_number.value,
                        notes: inputs.notes.value,
                        is_premium: inputs.is_premium.checked,
                        is_active: inputs.is_active.checked,
                    }),
                });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) {
                    error.textContent = Object.values(payload.errors || {}).flat().join(' ') || 'Customer could not be saved.';
                    error.classList.remove('d-none');
                    return;
                }
                const saved = payload.customer || {};
                const next = {
                    ...row,
                    name: saved.name || inputs.name.value,
                    mobile: saved.mobile || inputs.mobile.value,
                    code: saved.code || row.code,
                    is_premium: !!saved.is_premium,
                };
                const listRow = settings.customers.find((item) => item.uuid === row.uuid);
                if (listRow) {
                    listRow.name = next.name;
                    listRow.mobile = next.mobile;
                    listRow.is_premium = next.is_premium;
                }
                choose(next);
                $('customer-details-title').textContent = next.name;
                $('customer-details-sub').textContent = [next.mobile, next.code].filter(Boolean).join(' · ');
                status.textContent = 'Customer saved. Your bill on this page is unchanged.';
                status.classList.remove('d-none');
                if (typeof settings.onDetailsSaved === 'function') settings.onDetailsSaved(next, payload);
                if (typeof settings.onAddressSaved === 'function') settings.onAddressSaved(next, payload);
            } catch (err) {
                error.textContent = 'Customer could not be saved.';
                error.classList.remove('d-none');
            } finally {
                save.disabled = false;
            }
        });
        wrap.append(title, help, form, save, error, status);
        return wrap;
    }

    async function saveNew() {
        const error = $('customer-modal-error');
        error.classList.add('d-none');
        const response = await fetch(settings.createUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': settings.csrf,
            },
            body: JSON.stringify({
                name: $('new-customer-name').value,
                mobile: $('new-customer-mobile').value,
                customer_type: 'retail',
                kyc_status: 'pending',
                is_active: true,
            }),
        });
        const payload = await response.json();
        if (!response.ok) {
            error.textContent = Object.values(payload.errors || {}).flat().join(' ') || 'The customer could not be saved.';
            error.classList.remove('d-none');
            return;
        }
        settings.customers.push(payload);
        choose(payload);
        bootstrap.Modal.getOrCreateInstance($('customer-modal')).hide();
        $('new-customer-name').value = '';
        $('new-customer-mobile').value = '';
    }

    const search = $('customer-search');
    if (!search) {
        console.error('customer-picker: #customer-search is missing on this page.');
        return {
            choose: () => {},
            clear: () => {},
            current: () => null,
            ensure: () => false,
        };
    }
    search.addEventListener('input', render);
    search.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') return;
        event.preventDefault();
        const first = found()[0];
        if (first) choose(first);
    });
    const saveCustomer = $('save-customer');
    if (saveCustomer) saveCustomer.addEventListener('click', saveNew);
    const walkin = settings.customers.find((row) => row.walkin);
    if (walkin) {
        const button = $('customer-walkin');
        if (button) {
            button.classList.remove('d-none');
            button.addEventListener('click', () => choose(walkin));
        }
    }
    const preset = settings.customers.find((row) => row.uuid === $('customer-uuid').value);
    if (preset) {
        try {
            choose(preset);
        } catch (error) {
            console.error('customer-picker: preset customer failed', error);
        }
    }

    document.querySelectorAll('#customer-find [data-bs-toggle="tooltip"]').forEach((node) => {
        if (typeof bootstrap !== 'undefined') {
            bootstrap.Tooltip.getOrCreateInstance(node);
        }
    });

    return {
        choose,
        clear,
        current: () => current,
        ensure() {
            if ($('customer-uuid').value) return true;
            $('customer-error').classList.remove('d-none');
            if (!$('customer-find').classList.contains('d-none')) $('customer-search').focus();
            return false;
        },
    };
}
