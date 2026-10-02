@php
    $mode = $mode ?? 'create';
    $isEdit = $mode === 'edit';
    $rows = [];

    if ($isEdit && isset($product) && $product->has_variations) {
        foreach (
            $product
                ->variations()
                ->withSum('purchaseItems as stock_sum', 'remaining_qty')
                ->with('values.attribute')
                ->where('status', 1)
                ->get()
            as $v
        ) {
            $rows[] = [
                'id' => $v->id,
                'sku' => $v->sku,
                'purchase_price' => $v->purchase_price,
                'price' => $v->price,
                'quantity' => $v->quantity,
                'options' => $v->values->pluck('value', 'attribute.name')->all(),
            ];
        }
    }
    $rows = old('variations') ? array_values(old('variations')) : $rows;

    $enabled = $isEdit ? $product->has_variations ?? false : (bool) old('has_variations');
@endphp

@if (!$isEdit || $enabled)

    <div class="variation-product-card" id="variation-card" data-mode="{{ $mode }}"
        data-rows="{{ json_encode($rows) }}" data-product-sku="{{ $isEdit ? $product->sku ?? '' : '' }}">

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

                        <span>Stock changes through purchases and adjustments.Removing a value deactivates its
                            variations;history is kept.
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
                    // const MAX_OPTS = 3;
                    const SEP = '\u0001';

                    let options = []; // [{ name, values: [] }]
                    let store = {}; // combination key -> row data, survives regeneration

                    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;',
                        "'": '&#39;'
                    } [c]));
                    const clean = s => s.replace(/[\[\]]/g, '').trim(); // brackets would break input names

                    // ---------- initial data (edit page or old() after a validation error) ----------
                    JSON.parse(root.dataset.rows || '[]').forEach(row => {
                        Object.entries(row.options || {}).forEach(([name, val], idx) => {
                            options[idx] = options[idx] || {
                                name,
                                values: []
                            };
                            if (!options[idx].values.includes(val)) options[idx].values.push(val);
                        });
                        store[Object.values(row.options || {}).join(SEP)] = row;
                    });
                    if (!options.length) options.push({
                        name: '',
                        values: []
                    });

                    function getProductSku() {
                        if (isEdit) {
                            return root.dataset.productSku || '';
                        }
                        // create mode: read from the main SKU field
                        const skuInput = document.querySelector('input[name="sku"]');
                        return skuInput ? skuInput.value.trim() : '';
                    }

                    // ---------- combinations ----------
                    function combos() {
                        const used = options.filter(o => o.name && o.values.length);
                        if (!used.length) return [];
                        return used.reduce(
                            (acc, o) => acc.flatMap(a => o.values.map(v => [...a, {
                                name: o.name,
                                value: v
                            }])),
                            [
                                []
                            ]
                        );
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
                        const cs = on ? combos() : [];
                        wrapEl.classList.toggle('d-none', !cs.length);
                        emptyEl.classList.toggle('d-none', cs.length > 0 || !on);

                        rowsEl.innerHTML = cs.map((parts, i) => {
                            const key = parts.map(p => p.value).join(SEP);
                            const r = store[key] = store[key] || {
                                id: '',
                                sku: '',
                                price: '',
                                quantity: ''
                            };
                            const n = `variations[${i}]`;
                            const input = (field, extra = '') =>
                                `<input class="form-control form-control-sm" name="${n}[${field}]" value="${esc(r[field])}"
                                    data-key="${esc(key)}" data-field="${field}" ${extra}>`;

                            const productSku = getProductSku();

                            const variationSku = [
                                productSku,
                                ...parts.map(p => p.value)
                            ].filter(Boolean).join('-');

                            return `<tr>
                                <td class="align-middle font-weight-bold">
                                    ${esc(parts.map(p => p.value).join(' / '))}
                                    ${r.id ? `<input type="hidden" name="${n}[id]" value="${esc(r.id)}">` : ''}
                                    ${parts.map(p => `<input type="hidden" name="${n}[options][${esc(p.name)}]" value="${esc(p.value)}">`).join('')}
                                </td>
                                <td><input type="text" class="form-control form-control-sm" name="${n}[sku]" value="${esc(variationSku)}" 
                                    readonly></td>
                                <td>${input('price', 'type="number" step="0.01" min="0" required')}</td>
                                <td>${isEdit && r.id ? `<span class="align-middle">${esc(r.quantity)}</span>`
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
                        if (f) store[e.target.dataset.key][f] = e.target.value;
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
