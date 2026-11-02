@php
    $mode = $mode ?? 'create';
    $isEdit = $mode === 'edit';
    $rows = [];
    $initialOptions = [];

    if ($isEdit && isset($product) && $product->has_variations) {
        // Use eager-loaded variations (already filtered by SoftDeletes, with stock_sum)
        foreach ($product->variations as $v) {
            $optionsMap = [];
            foreach ($v->values as $val) {
                $attrName = trim($val->attribute?->name ?? '');
                $attrVal = trim($val->value ?? '');
                if ($attrName !== '' && $attrVal !== '') {
                    $optionsMap[$attrName] = $attrVal;
                }
            }

            $rows[] = [
                'id' => $v->id,
                'sku' => $v->sku,
                'purchase_price' => $v->purchase_price,
                'price' => $v->price,
                'quantity' => $v->quantity,
                'options' => $optionsMap,
            ];
        }
    }
    $rows = old('variations') ? array_values(old('variations')) : $rows;

    // Collect distinct attribute options in the order they were introduced
    $optionsCollector = [];
    foreach ($rows as $r) {
        foreach (($r['options'] ?? []) as $name => $val) {
            $name = trim($name);
            $val = trim($val);
            if ($name === '' || $val === '') {
                continue;
            }
            $lowerKey = mb_strtolower($name);
            if (!isset($optionsCollector[$lowerKey])) {
                $optionsCollector[$lowerKey] = [
                    'name' => $name,
                    'values' => [],
                ];
            }
            if (!in_array($val, $optionsCollector[$lowerKey]['values'], true)) {
                $optionsCollector[$lowerKey]['values'][] = $val;
            }
        }
    }
    $initialOptions = array_values($optionsCollector);

    $enabled = $isEdit ? $product->has_variations ?? false : (bool) old('has_variations');
@endphp

@if (!$isEdit || $enabled)

    <div class="variation-product-card" id="variation-card" data-mode="{{ $mode }}"
        data-rows="{{ json_encode($rows) }}"
        data-options="{{ json_encode($initialOptions) }}"
        data-product-sku="{{ $isEdit ? $product->sku ?? '' : '' }}">

        {{-- Header --}}
        <div class="variation-product-header">

            <div class="variation-product-heading">

                <div class="variation-product-icon">
                    <i class="fas fa-layer-group"></i>
                </div>

                <div>
                    <h3 class="variation-product-title">
                        Product Variations
                    </h3>

                    <p class="variation-product-description">
                        Add sizes, colors and other options. Each variation
                        can have its own price and stock.
                    </p>
                </div>

            </div>

            @if ($isEdit)
                <input type="hidden" name="has_variations" value="1">

                <div class="variation-status-badge">
                    <i class="fas fa-check-circle"></i>
                    Variable product
                </div>

                <input type="checkbox" id="has_variations" class="d-none" checked>
            @else
                <label class="variation-toggle">

                    <input type="checkbox" id="has_variations" name="has_variations" value="1"
                        {{ $enabled ? 'checked' : '' }}>

                    <span class="variation-toggle-slider"></span>

                    <span class="variation-toggle-label">
                        This product has variations
                    </span>

                </label>
            @endif

        </div>

        {{-- Body --}}
        <div class="variation-product-body" data-body>

            {{-- Options --}}
            <div data-options></div>


            {{-- Add option --}}
            <button type="button" class="variation-add-option" data-add-option>
                <i class="fas fa-plus"></i>
                Add another option
            </button>


            {{-- Empty state --}}
            <div data-empty class="variation-empty-state">
                <div class="variation-empty-icon">
                    <i class="fas fa-sliders-h"></i>
                </div>

                <div class="variation-empty-title">
                    Add your first option
                </div>

                <div class="variation-empty-text">
                    Add an option such as <strong>Size</strong> or
                    <strong>Color</strong>, then enter its values.
                </div>
            </div>


            {{-- Variation table --}}
            <div data-table-wrap class="d-none">

                {{-- Bulk controls --}}
                <div class="variation-bulk-box">

                    <div class="variation-bulk-heading">

                        <div class="variation-bulk-icon">
                            <i class="fas fa-layer-group"></i>
                        </div>

                        <div>
                            <div class="variation-bulk-title">
                                Bulk pricing
                            </div>

                            <div class="variation-bulk-description">
                                Apply prices to all variations
                            </div>
                        </div>

                    </div>


                    <div class="variation-bulk-fields">

                        <input type="number" step="0.01" min="0" class="variation-bulk-input"
                            placeholder="Selling price" data-bulk="price">

                        <button type="button" class="variation-apply-btn" data-apply-bulk>
                            <i class="fas fa-check"></i>
                            Apply
                        </button>

                    </div>

                </div>


                {{-- Table --}}
                <div class="variation-table-container">

                    <div class="table-responsive">

                        <table class="variation-table">

                            <thead>
                                <tr>
                                    <th>Variation</th>
                                    <th>SKU</th>
                                    <th>Selling Price</th>
                                    <th>{{ $isEdit ? 'Stock' : 'Opening Stock' }}</th>
                                </tr>
                            </thead>

                            <tbody data-rows></tbody>

                        </table>

                    </div>

                </div>


                @if ($isEdit)
                    <div class="variation-help-text">
                        <i class="fas fa-info-circle"></i>

                        <span>Stock changes through purchases and adjustments. Removing a value deactivates its
                            variations; history is kept.
                        </span>
                    </div>
                @endif

            </div>

        </div>

    </div>


    @once

        @push('script')
            <link rel="stylesheet" href="{{ asset('css/variation.css') }}">

            <script>
                (function() {
                    const root = document.getElementById('variation-card');
                    if (!root) return;

                    const isEdit = root.dataset.mode === 'edit';
                    const toggle = document.getElementById('has_variations');
                    const body = root.querySelector('[data-body]');
                    const optsEl = root.querySelector('[data-options]');
                    const rowsEl = root.querySelector('[data-rows]');
                    const emptyEl = root.querySelector('[data-empty]');
                    const wrapEl = root.querySelector('[data-table-wrap]');
                    const addBtn = root.querySelector('[data-add-option]');
                    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;',
                        "'": '&#39;'
                    } [c]));
                    const clean = s => s.replace(/[\[\]]/g, '').trim();

                    function makeComboKey(opts) {
                        let entries = [];
                        if (Array.isArray(opts)) {
                            entries = opts.map(p => [clean(p.name).toLowerCase(), clean(p.value)]);
                        } else if (opts && typeof opts === 'object') {
                            entries = Object.entries(opts).map(([k, v]) => [clean(k).toLowerCase(), clean(v)]);
                        }
                        entries.sort((a, b) => a[0].localeCompare(b[0]));
                        return entries.map(([k, v]) => `${k}:${v}`).join('|');
                    }

                    let options = JSON.parse(root.dataset.options || '[]');
                    const rawRows = JSON.parse(root.dataset.rows || '[]');

                    // store: canonical combination key -> row data, survives regeneration
                    // In edit mode, existing rows keep their `id` so the backend knows to update, not create
                    let store = {};

                    // Track which variation IDs came from the server (have stock, are existing)
                    let serverIds = new Set();

                    // If dataset.options wasn't passed or was empty, extract safely by attribute name (NOT by index)
                    if (!options.length) {
                        const optsMap = new Map();
                        rawRows.forEach(row => {
                            Object.entries(row.options || {}).forEach(([name, val]) => {
                                const cleanName = clean(name);
                                const cleanVal = clean(val);
                                if (!cleanName || !cleanVal) return;
                                const key = cleanName.toLowerCase();
                                if (!optsMap.has(key)) {
                                    optsMap.set(key, { name: cleanName, values: [] });
                                }
                                const opt = optsMap.get(key);
                                if (!opt.values.includes(cleanVal)) {
                                    opt.values.push(cleanVal);
                                }
                            });
                        });
                        options = Array.from(optsMap.values());
                    }

                    if (!options.length) {
                        options.push({
                            name: '',
                            values: []
                        });
                    }

                    // Populate store and serverIds using canonical keys
                    rawRows.forEach(row => {
                        const key = makeComboKey(row.options || {});
                        store[key] = row;
                        if (row.id) serverIds.add(row.id);
                    });

                    function getProductSku() {
                        if (isEdit) {
                            return root.dataset.productSku || '';
                        }
                        const skuInput = document.querySelector('input[name="sku"]');
                        return skuInput ? skuInput.value.trim() : '';
                    }

                    function findStoreRow(parts) {
                        const canonicalKey = makeComboKey(parts);
                        if (store[canonicalKey]) {
                            return { key: canonicalKey, row: store[canonicalKey] };
                        }
                        // Fallback: match by identical values if attribute names differ slightly
                        const partVals = parts.map(p => clean(p.value).toLowerCase()).sort();
                        for (const [sKey, sRow] of Object.entries(store)) {
                            if (!sRow.id || !serverIds.has(sRow.id)) continue;
                            const sVals = Object.values(sRow.options || {}).map(v => clean(v).toLowerCase()).sort();
                            if (sVals.length === partVals.length && sVals.every((v, idx) => v === partVals[idx])) {
                                return { key: sKey, row: sRow };
                            }
                        }
                        return null;
                    }

                    // ---------- Build the final row list for the table ----------
                    // In edit mode with smart merge:
                    //   - Existing rows with fewer options (older standalones) are preserved
                    //   - Full combinations match existing rows (with id) or create new rows (no id)
                    function buildRows() {
                        const on = toggle.checked;
                        if (!on) return [];

                        const used = options.filter(o => o.name && o.values.length);
                        if (!used.length) return [];

                        // Generate all combinations from the current options
                        const allCombos = used.reduce(
                            (acc, o) => acc.flatMap(a => o.values.map(v => [...a, {
                                name: o.name,
                                value: v
                            }])),
                            [
                                []
                            ]
                        );

                        // Build the result: each combo gets matched to store or creates a new entry
                        const result = [];
                        const seenKeys = new Set();

                        // 1. In edit mode, preserve existing server rows that have fewer options than the full matrix
                        // (e.g., older standalone variations like "White" when options now include Color + Size)
                        if (isEdit) {
                            for (const [key, row] of Object.entries(store)) {
                                if (!row.id || !serverIds.has(row.id)) continue;

                                const rowOptions = row.options || {};
                                const rowOptEntries = Object.entries(rowOptions);

                                // If the existing row already has all current options, allCombos below will match it
                                if (rowOptEntries.length >= used.length) continue;

                                // Check if all of this row's option values still exist in the current options
                                let stillValid = true;
                                for (const [optName, optVal] of rowOptEntries) {
                                    const matchingOpt = used.find(o => o.name.toLowerCase() === optName.toLowerCase());
                                    if (!matchingOpt || !matchingOpt.values.includes(optVal)) {
                                        stillValid = false;
                                        break;
                                    }
                                }

                                if (stillValid) {
                                    const canonicalKey = makeComboKey(rowOptions);
                                    if (!seenKeys.has(canonicalKey)) {
                                        seenKeys.add(canonicalKey);

                                        // Order parts according to current `used` order where possible
                                        const orderedParts = [];
                                        for (const u of used) {
                                            const val = rowOptions[u.name] || Object.entries(rowOptions).find(([k]) => k.toLowerCase() === u.name.toLowerCase())?.[1];
                                            if (val) {
                                                orderedParts.push({ name: u.name, value: val });
                                            }
                                        }
                                        for (const [n, v] of rowOptEntries) {
                                            if (!orderedParts.some(p => p.name.toLowerCase() === n.toLowerCase())) {
                                                orderedParts.push({ name: n, value: v });
                                            }
                                        }

                                        result.push({
                                            parts: orderedParts,
                                            key: canonicalKey,
                                            row: row,
                                        });
                                    }
                                }
                            }
                        }

                        // 2. Add all full combinations from current options matrix
                        for (const parts of allCombos) {
                            const canonicalKey = makeComboKey(parts);
                            if (seenKeys.has(canonicalKey)) continue;

                            const match = findStoreRow(parts);
                            const rowKey = match ? match.key : canonicalKey;
                            const row = match ? match.row : {
                                id: '',
                                sku: '',
                                price: '',
                                quantity: ''
                            };

                            if (!store[canonicalKey]) {
                                store[canonicalKey] = row;
                            }

                            seenKeys.add(canonicalKey);
                            seenKeys.add(rowKey);

                            result.push({
                                parts,
                                key: canonicalKey,
                                row: row,
                            });
                        }

                        return result;
                    }

                    // ---------- rendering ----------
                    function renderOptions() {
                        optsEl.innerHTML = options.map((o, i) => `
                            <div class="vr-option" data-opt="${i}">
                                <div class="d-flex mb-2">
                                    <input
                                        class="form-control form-control-sm mr-2"
                                        style="max-width:220px"
                                        placeholder="Option name (e.g. Size)"
                                        value="${esc(o.name)}"
                                        data-opt-name
                                        aria-label="Option name"
                                    >

                                    ${options.length > 1 || o.name || o.values.length ? ` 
                                        <button
                                            type="button"
                                            class="variation-remove-option"
                                            data-remove-opt
                                            title="Remove option"
                                            aria-label="Remove option"
                                        >
                                            <i class="fas fa-times"></i>
                                        </button>` : ''
                                    }
                                </div>

                                <div class="vr-chips form-control h-auto">
                                    ${o.values.map((v, j) => `
                                        <span class="vr-chip">
                                            ${esc(v)}
                                            <button
                                                type="button"
                                                data-remove-val="${j}"
                                                aria-label="Remove ${esc(v)}"
                                            >&times;</button>
                                        </span>
                                    `).join('')}

                                    <input
                                        class="vr-chip-input"
                                        placeholder="Type a value, press Enter"
                                        data-val-input
                                        aria-label="Option values"
                                    >
                                </div>
                            </div>
                        `).join('');

                        // No maximum limit
                        addBtn.classList.remove('d-none');
                    }

                    function renderRows() {
                        const on = toggle.checked;
                        const rows = buildRows();
                        wrapEl.classList.toggle('d-none', !rows.length);
                        emptyEl.classList.toggle('d-none', rows.length > 0 || !on);

                        rowsEl.innerHTML = rows.map((item, i) => {
                            const { parts, key, row: r } = item;
                            const n = `variations[${i}]`;
                            const input = (field, extra = '') =>
                                `<input class="form-control form-control-sm" name="${n}[${field}]" value="${esc(r[field])}"
                                    data-key="${esc(key)}" data-field="${field}" ${extra}>`;

                            const productSku = getProductSku();

                            const variationSku = r.sku || [
                                productSku,
                                ...parts.map(p => p.value)
                            ].filter(Boolean).join('-');

                            const isExisting = isEdit && r.id && serverIds.has(r.id);
                            const label = parts.map(p => p.value).join(' / ');

                            return `<tr>
                                <td class="align-middle font-weight-bold">
                                    ${esc(label)}
                                    ${isExisting ? '<span class="badge badge-info ml-1" style="font-size:0.7em">Existing</span>' : ''}
                                    ${r.id ? `<input type="hidden" name="${n}[id]" value="${esc(r.id)}">` : ''}
                                    ${parts.map(p => `<input type="hidden" name="${n}[options][${esc(p.name)}]" value="${esc(p.value)}">`).join('')}
                                </td>
                                <td><input type="text" class="form-control form-control-sm" name="${n}[sku]" value="${esc(variationSku)}" 
                                    readonly></td>
                                <td>${input('price', 'type="number" step="0.01" min="0" required')}</td>
                                <td>${isExisting ? `<span class="align-middle">${esc(r.quantity)}</span>`
                                    : input('quantity', 'type="number" step="0.01" min="0" placeholder="0"')}</td>
                            </tr>`;
                        }).join('');

                        document.querySelectorAll('.js-simple-fields').forEach(el => el.classList.toggle('d-none', on));
                    }

                    function render() {
                        renderOptions();
                        renderRows();
                    }

                    // ---------- events ----------
                    optsEl.addEventListener('input', e => {
                        if (!e.target.matches('[data-opt-name]')) return;
                        options[+e.target.closest('[data-opt]').dataset.opt].name = clean(e.target.value);
                        renderRows();
                    });

                    optsEl.addEventListener('keydown', e => {
                        if (!e.target.matches('[data-val-input]')) return;
                        const o = options[+e.target.closest('[data-opt]').dataset.opt];
                        if (e.key === 'Enter' || e.key === ',') {
                            e.preventDefault();
                            const v = clean(e.target.value.replace(/,/g, ''));
                            if (v && !o.values.includes(v)) o.values.push(v);
                            const i = options.indexOf(o);
                            render();
                            optsEl.querySelector(`[data-opt="${i}"] [data-val-input]`).focus();
                        } else if (e.key === 'Backspace' && !e.target.value && o.values.length) {
                            o.values.pop();
                            render();
                        }
                    });

                    optsEl.addEventListener('click', e => {
                        const opt = e.target.closest('[data-opt]');
                        if (!opt) return;

                        const i = Number(opt.dataset.opt);

                        // Remove value
                        const removeValueBtn = e.target.closest('[data-remove-val]');

                        if (removeValueBtn) {
                            const valueIndex = Number(removeValueBtn.dataset.removeVal);

                            options[i].values.splice(valueIndex, 1);

                            render();

                            return;
                        }

                        // Remove option
                        const removeOptionBtn = e.target.closest('[data-remove-opt]');

                        if (removeOptionBtn) {
                            options.splice(i, 1);

                            if (!options.length) {
                                options.push({
                                    name: '',
                                    values: []
                                });
                            }

                            render();

                            return;
                        }
                    });

                    addBtn.addEventListener('click', () => {
                        options.push({
                            name: '',
                            values: []
                        });
                        render();
                    });

                    rowsEl.addEventListener('input', e => {
                        const f = e.target.dataset.field;
                        if (f && store[e.target.dataset.key]) store[e.target.dataset.key][f] = e.target.value;
                    });

                    root.querySelector('[data-apply-bulk]').addEventListener('click', () => {
                        root.querySelectorAll('[data-bulk]').forEach(b => {
                            if (b.value === '') return;
                            Object.values(store).forEach(r => {
                                r[b.dataset.bulk] = b.value;
                            });
                        });
                        renderRows();
                    });

                    toggle.addEventListener('change', () => {
                        body.classList.toggle('d-none', !toggle.checked);
                        renderRows();
                    });

                    const mainSkuInput = document.querySelector('input[name="sku"]');
                    if (mainSkuInput && !isEdit) {
                        mainSkuInput.addEventListener('input', renderRows);
                    }


                    body.classList.toggle('d-none', !toggle.checked);
                    render();
                })
                ();
            </script>
        @endpush
    @endonce
@endif
