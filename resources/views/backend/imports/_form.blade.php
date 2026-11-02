@php
    $isEdit = isset($import);
    $model = $isEdit ? $import : null;

    $v = function ($field, $default = '') use ($model) {
        $value = old($field, $model->{$field} ?? $default);

        if ($value instanceof \Carbon\CarbonInterface) {
            return $value->format('Y-m-d');
        }

        return $value;
    };

    $existingRows = old(
        'import_products',
        isset($import)
            ? $import->products
                ->map(
                    fn($item) => [
                        'product_id' => $item->product_id,
                        'product_variation_id' => $item->product_variation_id,
                        'unit_id' => $item->unit_id,
                        'pcs_per_carton' => $item->pcs_per_carton,
                        'ctn_qty' => $item->ctn_qty,
                        'price_pc' => $item->price_pc,
                        'sale_price' => $item->sale_price,
                        'sale_percent' => $item->sale_percent,
                        '_product_name' => $item->product?->name ?? '',
                    ],
                )
                ->values()
                ->toArray()
            : [],
    );
@endphp
<link rel="stylesheet" href="{{ asset('css/import.css') }}">
<form action="{{ $isEdit ? route('backend.admin.imports.update', $model->id) : route('backend.admin.imports.store') }}"
    method="POST">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="card-body">

        {{-- Step navigation --}}

        <div class="import-steps mb-4">
            <button type="button" class="import-step active" data-step="1">
                <span class="step-circle">1</span>
                <span class="step-label">
                    <strong>Import & Expenses</strong>
                </span>
            </button>

            <div class="step-connector"></div>

            <button type="button" class="import-step" data-step="2">
                <span class="step-circle">2</span>
                <span class="step-label">
                    <strong>Product Details</strong>
                </span>
            </button>
        </div>

        <div id="import-step-1">
            {{-- ============= Container & Supplier ============= --}}
            <div class="import-section">

                <div class="import-section-header">
                    <div class="import-section-icon">
                        <i class="fas fa-ship"></i>
                    </div>

                    <div>
                        <h5 class="import-section-title">Container & Supplier</h5>
                        <p class="import-section-subtitle">
                            Enter shipment, supplier and invoice information
                        </p>
                    </div>
                </div>

                <div class="import-section-body">

                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Import No</label>
                                <input type="text" class="form-control" readonly
                                    value="{{ $isEdit ? $model->import_no : $nextImportNo }}">
                                <small class="text-muted">
                                    {{ $isEdit ? 'This is the saved import number.' : 'This number is assigned automatically when you save.' }}
                                </small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Container Code</label>
                                <input type="text" name="container_code" class="form-control" placeholder="CONT-123"
                                    value="{{ $v('container_code') }}">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>P Date</label>
                                <input type="date" name="p_date" class="form-control"
                                    value="{{ $v('p_date', date('Y-m-d')) }}">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Clear Date</label>
                                <input type="date" name="clear_date" class="form-control"
                                    value="{{ $v('clear_date', date('Y-m-d')) }}">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Currency</label>
                                <select name="currency_id" id="currency_id" class="form-control select2">
                                    <option value="">-- No Currency (PKR only) --</option>
                                    @foreach ($currencies as $c)
                                        <option value="{{ $c->id }}" data-code="{{ $c->code }}"
                                            {{ old('currency_id', $v('currency_id')) == $c->id ? 'selected' : '' }}>
                                            {{ $c->code }} ({{ $c->symbol }}) — {{ $c->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Ex Rate</label>
                                <input type="number" step="0.000001" name="ex_rate" id="ex_rate" class="form-control"
                                    placeholder="0.00" value="{{ old('ex_rate', $v('ex_rate')) }}">
                                <small id="rate_error" class="text-danger" style="display:none;"></small>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Inv Amount</label>
                                <input type="number" step="0.01" name="inv_amount" id="inv_amount"
                                    class="form-control" placeholder="0.00"
                                    value="{{ old('inv_amount', $v('inv_amount')) }}">
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Inv Amount / PKR <span class="text-danger req-pkr"
                                        style="display:none;">*</span></label>
                                <input type="number" step="0.01" name="inv_amount_pkr" id="inv_amount_pkr"
                                    class="form-control" placeholder="0.00"
                                    value="{{ old('inv_amount_pkr', $v('inv_amount_pkr')) }}">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Godown</label>
                                <select name="godown_id" class="form-control">
                                    <option value="">Select Godown</option>
                                    @foreach ($godowns as $godown)
                                        <option value="{{ $godown->id }}"
                                            {{ $v('godown_id') == $godown->id ? 'selected' : '' }}>
                                            {{ $godown->name }}{{ $godown->location ? ' — ' . $godown->location : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Supplier</label>
                                <select name="supplier_id" class="form-control">
                                    <option value="">Select Supplier</option>
                                    @foreach ($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}"
                                            {{ $v('supplier_id') == $supplier->id ? 'selected' : '' }}>
                                            {{ $supplier->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Address</label>
                                <input type="text" name="address" class="form-control" placeholder="Address"
                                    value="{{ $v('address') }}">
                            </div>
                        </div>
                    </div>

                </div>
            </div>


            {{-- ============= Category Expense Details (DYNAMIC) ============= --}}
            <div class="import-section">

                <div class="import-section-header">
                    <div class="import-section-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>

                    <div>
                        <h5 class="import-section-title">Category Details</h5>
                        <p class="import-section-subtitle">
                            Select category, enter CBF/CBM & category expense. Use <b>+</b> to add more rows.
                        </p>
                    </div>
                </div>

                <div class="import-section-body">

                    <div id="expense-rows">

                        @php
                            $existingExpenses =
                                isset($import) && $import->expenses->count() ? $import->expenses : collect([null]); // fallback to one blank row
                        @endphp

                        @foreach ($existingExpenses as $i => $expense)
                            <div class="row expense-row mb-2 align-items-end">

                                {{-- Category --}}
                                <div class="col-md-3">
                                    <label>Category</label>
                                    <select name="expenses[{{ $i }}][category_id]"
                                        class="form-control expense-category">
                                        <option value="">Select Category</option>
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->id }}" data-name="{{ $category->name }}"
                                                {{ ($expense->category_id ?? '') == $category->id ? 'selected' : '' }}>
                                                {{ $category->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- CBF/CBM (dynamic label) --}}
                                <div class="col-md-3">
                                    <label class="cbf-label">
                                        {{ $expense && $expense->category ? $expense->category->name . ' CBF/CBM' : 'CBF/CBM' }}
                                    </label>
                                    <input type="number" step="0.01"
                                        name="expenses[{{ $i }}][cbf_cbm]" class="form-control cbf-input"
                                        placeholder="0.00" value="{{ $expense->cbf_cbm ?? '' }}">
                                </div>

                                {{-- Category Expense --}}
                                <div class="col-md-3">
                                    <label>Category Expense</label>
                                    <input type="number" step="0.01"
                                        name="expenses[{{ $i }}][amount]" class="form-control"
                                        placeholder="0.00" value="{{ $expense->amount ?? '' }}">
                                </div>

                                {{-- Add + Remove --}}
                                <div class="col-md-3 d-flex">
                                    <button type="button" class="btn btn-outline-primary add-expense mr-1"
                                        title="Add another row">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger remove-expense"
                                        title="Remove this row">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>

                            </div>
                        @endforeach

                    </div>
                </div>
            </div>


            {{-- ============= Fixed Expense Details ============= --}}
            <div class="import-section">

                <div class="import-section-header">
                    <div class="import-section-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>

                    <div>
                        <h5 class="import-section-title">Expense Details</h5>
                        <p class="import-section-subtitle">
                            Enter freight, fitting and other import expenses
                        </p>
                    </div>
                </div>

                <div class="import-section-body">

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Freight Expense</label>
                                <input type="number" step="0.01" name="freight_expense" class="form-control"
                                    placeholder="0.00" value="{{ $v('freight_expense') }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>F.T.A Expense</label>
                                <input type="number" step="0.01" name="fta_expense" class="form-control"
                                    placeholder="0.00" value="{{ $v('fta_expense') }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Fitting Expense</label>
                                <input type="number" step="0.01" name="fitting_expense" class="form-control"
                                    placeholder="0.00" value="{{ $v('fitting_expense') }}">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>C.O Expense</label>
                                <input type="number" step="0.01" name="co_expense" class="form-control"
                                    placeholder="0.00" value="{{ $v('co_expense') }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Other Expense</label>
                                <input type="number" step="0.01" name="other_expense" class="form-control"
                                    placeholder="0.00" value="{{ $v('other_expense') }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Total Expense</label>
                                <input type="number" step="0.01" id="total_expense_display" class="form-control"
                                    placeholder="0.00" value="{{ $v('total_expense') }}" readonly>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

        </div>

        <div id="import-step-2" style="display:none;">

            <div class="import-section">
                <div class="import-section-header">
                    <div class="import-section-icon">
                        <i class="fas fa-boxes"></i>
                    </div>
                    <div>
                        <h5 class="import-section-title">Product Details</h5>
                        <p class="import-section-subtitle">
                            Select products, packing units, carton quantities and prices.
                        </p>
                    </div>
                </div>

                <div class="import-section-body">

                    <div class="product-table-toolbar">
                        <div>
                            <h6 class="mb-1">Imported Products</h6>
                            <p class="mb-0">
                                Add each product with its packing and carton quantities.
                            </p>
                        </div>

                        <button type="button" class="btn btn-add-product" id="addImportProduct">
                            <i class="fas fa-plus mr-2"></i> Add Product
                        </button>
                    </div>


                    <div class="table-responsive">
                        <table class="table table-bordered table-sm" id="importProductsTable"
                            style="min-width: 1250px;">
                            <thead class="import-products-head">
                                <tr>
                                    <th>Sr No</th>
                                    <th>Code</th>
                                    <th style="min-width:200px;">Name</th>
                                    <th>Category</th>
                                    <th style="min-width:150px;">Variation</th>
                                    <th style="min-width:130px;">Packing</th>
                                    <th style="min-width:100px;">Pcs / Ctn</th>
                                    <th style="min-width:100px;">Ctn Qty</th>
                                    <th>Total Pcs</th>
                                    <th style="min-width:110px;">Price / Pc</th>
                                    <th style="min-width:110px;"
                                        title="Price / Pc + category expense share + total expense share">
                                        Cost / Pc <i class="fas fa-info-circle text-muted"
                                            style="font-size:11px;"></i>
                                    </th>
                                    <th style="min-width:110px;">Sale Price</th>
                                    <th style="min-width:90px;">%</th>
                                    <th>Amount</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="importProductRows"></tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="13" class="text-right">Total Amount</th>
                                    <th id="importProductsTotal">0.00</th>
                                    <th></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

        </div>



        <div class="card-footer import-footer">
            <a href="{{ route('backend.admin.imports.index') }}" class="btn btn-import-cancel">
                <i class="fas fa-times mr-1"></i> Cancel
            </a>

            <button type="button" class="btn btn-import-previous" id="previousImportStep" style="display:none;">
                <i class="fas fa-arrow-left mr-1"></i> Previous
            </button>

            <button type="button" class="btn btn-import-save" id="nextImportStep">
                Next: Product Details
                <i class="fas fa-arrow-right ml-1"></i>
            </button>

            <button type="submit" class="btn btn-import-save" id="saveImportRecord" style="display:none;">
                <i class="fas fa-save mr-1"></i>
                {{ $isEdit ? 'Update' : 'Complete' }}
            </button>
        </div>


    </div>
</form>

<script>
    $(document).ready(function() {
        const $currency = $('#currency_id');
        const $rate = $('#ex_rate');
        const $amt = $('#inv_amount');
        const $pkr = $('#inv_amount_pkr');
        const $error = $('#rate_error');

        // When user selects currency: request the rate via AJAX
        $currency.on('change', function() {
            const currencyId = $(this).val();
            $error.hide();
            $rate.val('');

            if (!currencyId) return;

            $.ajax({
                url: '{{ route('backend.admin.api.exchange-rate') }}',
                data: {
                    currency_id: currencyId
                },
                success: function(res) {
                    // Successfully retrieved rate, auto-fill
                    $rate.val(res.rate);
                    if ($amt.val() !== '') calculatePkr();
                },
                error: function(xhr) {
                    // Core: rate unavailable
                    let msg =
                        'Currency exchange rate not available currently. Please enter manually.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }
                    $error.text(msg).show();
                    $rate.prop('readonly', false); // Allow manual entry
                }
            });
        });

        // Amount linkage calculation (same as before)
        $rate.on('input', calculatePkr);
        $amt.on('input', calculatePkr);
        $pkr.on('input', function() {
            if ($amt.val() === '') {
                const pkrVal = parseFloat($(this).val()) || 0;
                const rateVal = parseFloat($rate.val()) || 0;
                if (rateVal > 0) $amt.val((pkrVal / rateVal).toFixed(2));
            }
        });

        function calculatePkr() {
            const a = parseFloat($amt.val()) || 0;
            const r = parseFloat($rate.val()) || 0;
            if (a > 0 && r > 0) $pkr.val((a * r).toFixed(2));
        }
    });
    document.addEventListener('DOMContentLoaded', function() {

        const expenseRows = document.getElementById('expense-rows');
        const categories = @json($categories);

        // Build category options HTML
        let categoryOptions = '<option value="">Select Category</option>';
        categories.forEach(function(cat) {
            categoryOptions += `<option value="${cat.id}" data-name="${cat.name}">${cat.name}</option>`;
        });

        // -------- Build a new row HTML --------
        function buildRow(index) {
            return `
                <div class="row expense-row mb-2 align-items-end">
                    <div class="col-md-3">
                        <label>Category</label>
                        <select name="expenses[${index}][category_id]" class="form-control expense-category">
                            ${categoryOptions}
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="cbf-label">CBF/CBM</label>
                        <input type="number" step="0.01"
                            name="expenses[${index}][cbf_cbm]"
                            class="form-control cbf-input" placeholder="0.00">
                    </div>

                    <div class="col-md-3">
                        <label>Category Expense</label>
                        <input type="number" step="0.01"
                            name="expenses[${index}][amount]"
                            class="form-control" placeholder="0.00">
                    </div>

                    <div class="col-md-3 d-flex">
                        <button type="button" class="btn btn-outline-primary add-expense mr-1" title="Add another row">
                            <i class="fas fa-plus"></i>
                        </button>
                        <button type="button" class="btn btn-outline-danger remove-expense" title="Remove this row">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `;
        }

        // -------- Add a new row --------
        function addRow() {
            const index = expenseRows.querySelectorAll('.expense-row').length;
            expenseRows.insertAdjacentHTML('beforeend', buildRow(index));
        }

        // -------- Event: Add / Remove / Category Change (delegated) --------
        expenseRows.addEventListener('click', function(e) {

            // Add
            const addBtn = e.target.closest('.add-expense');
            if (addBtn) {
                addRow();
                return;
            }

            // Remove
            const removeBtn = e.target.closest('.remove-expense');
            if (removeBtn) {
                const row = removeBtn.closest('.expense-row');

                if (expenseRows.querySelectorAll('.expense-row').length > 1) {
                    row.remove();
                    reindexExpenseRows();
                } else {
                    // Reset the last row instead of removing
                    row.querySelector('.expense-category').value = '';
                    row.querySelector('.cbf-input').value = '';
                    row.querySelector('input[name*="[amount]"]').value = '';
                    updateCbfLabel(row);
                }
            }
        });

        // Change category → update CBF/CBM label
        expenseRows.addEventListener('change', function(e) {
            if (e.target.classList.contains('expense-category')) {
                updateCbfLabel(e.target.closest('.expense-row'));
            }
        });

        // -------- Update CBF/CBM label based on selected category --------
        function updateCbfLabel(row) {
            const select = row.querySelector('.expense-category');
            const label = row.querySelector('.cbf-label');
            const selectedName = select.options[select.selectedIndex]?.dataset.name || '';

            label.textContent = selectedName ?
                selectedName + ' CBF/CBM' :
                'CBF/CBM';
        }

        // -------- Re-index names after removal --------
        function reindexExpenseRows() {
            const rows = expenseRows.querySelectorAll('.expense-row');

            rows.forEach(function(row, i) {
                const cat = row.querySelector('.expense-category');
                const cbf = row.querySelector('.cbf-input');
                const amt = row.querySelector('input[name*="[amount]"]');

                if (cat) cat.name = `expenses[${i}][category_id]`;
                if (cbf) cbf.name = `expenses[${i}][cbf_cbm]`;
                if (amt) amt.name = `expenses[${i}][amount]`;
            });
        }

    });


    document.addEventListener('DOMContentLoaded', function() {
        const step1 = document.getElementById('import-step-1');
        const step2 = document.getElementById('import-step-2');
        const nextBtn = document.getElementById('nextImportStep');
        const prevBtn = document.getElementById('previousImportStep');
        const saveBtn = document.getElementById('saveImportRecord');
        const addBtn = document.getElementById('addImportProduct');
        const rowsContainer = document.getElementById('importProductRows');

        const units = @json($units->map(fn($u) => ['id' => $u->id, 'name' => $u->title])->values());
        const categoriesById = @json($categories->mapWithKeys(fn($c) => [$c->id => $c->name]));

        const existingRows = @json($existingRows);

        let rowIndex = 0;

        function showStep(step) {
            const first = step === 1;

            step1.style.display = first ? '' : 'none';
            step2.style.display = first ? 'none' : '';
            nextBtn.style.display = first ? '' : 'none';
            prevBtn.style.display = first ? 'none' : '';
            saveBtn.style.display = first ? 'none' : '';

            document.querySelectorAll('.import-step').forEach(button => {
                button.classList.toggle('active', Number(button.dataset.step) === step);
            });

            document.querySelector('.import-steps').classList.toggle('step-two-active', step === 2);

            document.querySelectorAll('.import-step').forEach(button => {
                button.classList.toggle('completed', Number(button.dataset.step) < step);
            });

            if (!first) {
                calculateAllRows();
            }
        }

        nextBtn.addEventListener('click', function() {
            showStep(2);
        });

        prevBtn.addEventListener('click', () => showStep(1));

        document.querySelectorAll('.import-step').forEach(button => {
            button.addEventListener('click', function() {
                showStep(Number(this.dataset.step));
            });
        });

        function addOptions(items, selectedId, placeholder) {
            const select = document.createElement('select');
            select.className = 'form-control form-control-sm';

            const first = document.createElement('option');
            first.value = '';
            first.textContent = placeholder;
            select.appendChild(first);

            items.forEach(item => {
                const option = document.createElement('option');
                option.value = item.id;
                option.textContent = item.name;
                option.selected = String(item.id) === String(selectedId ?? '');
                select.appendChild(option);
            });

            return select;
        }

        const SEARCH_URL = @json(route('backend.admin.imports.search-products'));

        /**
         * Create a product search picker.
         * The hidden input is named import_products[i][product_id].
         */
        function createProductPicker(index, initial = {}) {
            const wrapper = document.createElement('div');
            wrapper.className = 'product-picker';

            const input = document.createElement('input');
            input.type = 'text';
            input.className = 'form-control form-control-sm product-search';
            input.placeholder = 'Search product…';
            input.autocomplete = 'off';

            const clearBtn = document.createElement('button');
            clearBtn.type = 'button';
            clearBtn.className = 'product-search-clear';
            clearBtn.innerHTML = '<i class="fas fa-times"></i>';
            clearBtn.title = 'Clear';

            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.className = 'product-id';
            hidden.name = `import_products[${index}][product_id]`;
            hidden.value = initial.product_id ?? '';

            const results = document.createElement('div');
            results.className = 'product-results';

            wrapper.append(input, clearBtn, hidden, results);

            // Render dropdown outside the table to prevent clipping.
            results.style.position = 'fixed';
            results.style.zIndex = '999999';

            // If edit-mode: prefill the search box with the current product name.
            if (initial._name) {
                input.value = initial._name;
                wrapper.classList.add('has-value');
            }

            // --- Lookup state ---
            let controller = null;
            let timer = null;

            function closeResults() {
                results.classList.remove('open');
                results.innerHTML = '';

                if (results.parentElement !== wrapper) {
                    wrapper.appendChild(results);
                }
            }

            const repositionResults = () => {
                if (results.classList.contains('open')) {
                    positionResults();
                }
            };

            window.addEventListener('scroll', repositionResults, true);
            window.addEventListener('resize', repositionResults);

            function positionResults() {
                const rect = input.getBoundingClientRect();

                results.style.position = 'fixed';
                results.style.left = `${rect.left}px`;
                results.style.top = `${rect.bottom + 5}px`;
                results.style.width = `${Math.max(rect.width, 220)}px`;

                // Keep the dropdown within the viewport.
                const spaceBelow = window.innerHeight - rect.bottom - 12;

                results.style.maxHeight = `${Math.max(
                    80,
                    Math.min(320, spaceBelow)
                )}px`;

                results.style.overflowY = 'auto';
                results.style.overflowX = 'hidden';
                results.style.backgroundColor = '#ffffff';
                results.style.opacity = '1';
                results.style.zIndex = '999999';
            }

            function applySelection(p) {
                hidden.value = p.id;
                input.value = p.name + (p.sku ? ' — ' + p.sku : '');
                wrapper.classList.add('has-value');
                closeResults();
                // Let the row re-populate variations + code column.
                wrapper.dispatchEvent(new CustomEvent('product:selected', {
                    bubbles: true,
                    detail: {
                        product: p
                    },
                }));
            }

            function renderResults(list) {
                results.innerHTML = '';

                if (!list.length) {
                    results.innerHTML = '<div class="empty">No products found</div>';
                } else {
                    list.forEach(p => {
                        const item = document.createElement('div');
                        item.className = 'item';
                        item.innerHTML =
                            `${escapeHtml(p.name)}<span class="sku">${escapeHtml(p.sku ?? '')}</span>`;

                        item.addEventListener('mousedown', e => {
                            e.preventDefault();
                            applySelection(p);
                        });

                        results.appendChild(item);
                    });
                }

                document.body.appendChild(results);
                results.classList.add('open');
                positionResults();
            }

            async function search(term) {
                if (controller) controller.abort();
                controller = new AbortController();

                try {
                    const res = await fetch(`${SEARCH_URL}?q=${encodeURIComponent(term)}`, {
                        headers: {
                            'Accept': 'application/json'
                        },
                        signal: controller.signal,
                    });
                    if (!res.ok) return;
                    const data = await res.json();
                    renderResults(data.results || []);
                } catch (err) {
                    if (err.name !== 'AbortError') console.error(err);
                }
            }

            // Debounced search
            input.addEventListener('input', () => {
                const term = input.value.trim();

                wrapper.classList.toggle('has-value', input.value.length > 0);

                // Clear the previous product selection when editing the search.
                hidden.value = '';

                if (controller) {
                    controller.abort();
                }

                clearTimeout(timer);

                // Empty term = show the default list; otherwise filter.
                timer = setTimeout(() => search(term), term.length === 0 ? 0 : 250);
            });

            input.addEventListener('focus', () => {
                input.select(); // optional: select text so typing replaces it
                search(input.value.trim());
            });

            // Reopen the list if the input is already focused and closed
            input.addEventListener('click', () => {
                if (!results.classList.contains('open')) {
                    search(input.value.trim());
                }
            });

            input.addEventListener('blur', () => {
                // Defer so mousedown on an item still registers
                setTimeout(closeResults, 120);
            });

            clearBtn.addEventListener('click', () => {
                input.value = '';
                hidden.value = '';
                wrapper.classList.remove('has-value');
                closeResults();
                wrapper.dispatchEvent(new CustomEvent('product:selected', {
                    bubbles: true,
                    detail: {
                        product: null
                    },
                }));
                input.focus(); // focus handler opens the list
            });

            // If edit-mode, resolve the initial product via API so variations render.
            if (initial.product_id) {
                fetch(`${SEARCH_URL}?q=${encodeURIComponent(initial._name ?? '')}`, {
                        headers: {
                            'Accept': 'application/json'
                        },
                    })
                    .then(r => r.json())
                    .then(data => {
                        const p = (data.results || []).find(x => String(x.id) === String(initial
                            .product_id));
                        if (p) {
                            wrapper.dispatchEvent(new CustomEvent('product:selected', {
                                bubbles: true,
                                detail: {
                                    product: p
                                },
                            }));
                        }
                    })
                    .catch(() => {});
            }

            return wrapper;
        }

        function escapeHtml(str) {
            return String(str ?? '').replace(/[&<>"']/g, s => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            } [s]));
        }

        function addProductRow(data = {}) {
            const index = rowIndex++;
            const row = document.createElement('tr');
            row.className = 'import-product-row';

            // NEW: search picker instead of <select>
            const picker = createProductPicker(index, {
                product_id: data.product_id ?? '',
                // For edit mode we need a display name before the API response arrives:
                _name: data._product_name ?? data.product_name ?? '',
            });

            const codeInput = document.createElement('input');
            codeInput.type = 'text';
            codeInput.className = 'form-control form-control-sm product-code';
            codeInput.readOnly = true;

            const variationSelect = document.createElement('select');
            variationSelect.className = 'form-control form-control-sm variation-select';
            variationSelect.name = `import_products[${index}][product_variation_id]`;
            variationSelect.innerHTML = '<option value="">—</option>';
            variationSelect.disabled = true;

            const unitSelect = addOptions(units, data.unit_id, 'Select packing');
            unitSelect.classList.add('unit-select');
            unitSelect.name = `import_products[${index}][unit_id]`;

            const pcsInput = createInput(index, 'number', 'pcs_per_carton', data.pcs_per_carton ?? 1, '1');
            const cartonInput = createInput(index, 'number', 'ctn_qty', data.ctn_qty ?? 0, '1');
            const totalInput = createInput(index, 'number', 'total_pcs', 0, '1');
            const pricePcInput = createInput(index, 'number', 'price_pc', data.price_pc ?? 0, '0.01');
            const amountInput = createInput(index, 'number', 'amount', 0, '0.01');

            const costPcInput = createInput(index, 'number', 'cost_pc', 0, '0.01');
            costPcInput.removeAttribute('name'); // display only, server recalculates
            costPcInput.readOnly = true;
            costPcInput.classList.add('cost-pc');

            const categoryDisplay = document.createElement('input');
            categoryDisplay.type = 'text';
            categoryDisplay.className = 'form-control form-control-sm product-category';
            categoryDisplay.readOnly = true;
            categoryDisplay.placeholder = '—';

            const salePriceInput = createInput(index, 'number', 'sale_price', data.sale_price ?? 0, '0.01');
            salePriceInput.min = '0';
            salePriceInput.classList.add('sale-price');

            const percentInput = createInput(index, 'number', 'sale_percent', data.sale_percent ?? 0, '0.01');
            percentInput.classList.add('sale-percent');

            pcsInput.min = '1';
            pcsInput.classList.add('pcs-per-carton');
            cartonInput.min = '0';
            cartonInput.classList.add('carton-qty');
            totalInput.readOnly = true;
            totalInput.classList.add('total-pcs');
            pricePcInput.min = '0';
            pricePcInput.classList.add('price-pc');
            amountInput.readOnly = true;
            amountInput.classList.add('product-amount');

            const cells = [
                document.createTextNode(''), codeInput, picker, categoryDisplay, variationSelect,
                unitSelect, pcsInput,
                cartonInput, totalInput, pricePcInput, costPcInput, salePriceInput, percentInput,
                amountInput,
            ];

            cells.forEach((content, i) => {
                const td = document.createElement('td');
                if (i === 0) td.className = 'product-row-number';
                else td.appendChild(content);
                row.appendChild(td);
            });

            // Action cell
            const actionCell = document.createElement('td');
            const removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.className = 'btn btn-sm btn-outline-danger remove-import-product';
            removeButton.innerHTML = '<i class="fas fa-trash"></i>';
            actionCell.appendChild(removeButton);
            row.appendChild(actionCell);

            rowsContainer.appendChild(row);

            // Fill variation list when the picker emits a selection
            row.addEventListener('product:selected', (e) => {
                const product = e.detail.product;
                const variationSelect = row.querySelector('.variation-select');
                const codeInput = row.querySelector('.product-code');

                variationSelect.innerHTML = '<option value="">—</option>';
                codeInput.value = product?.sku ?? '';
                row.dataset.categoryId = product?.category_id ?? '';

                const categoryDisplay = row.querySelector('.product-category');
                categoryDisplay.value = product?.category_id ? (categoriesById[product.category_id] ??
                    '') : '';

                const salePriceEl = row.querySelector('.sale-price');
                const priceEl = row.querySelector('.price-pc');
                if (salePriceEl && !Number(salePriceEl.value)) {
                    salePriceEl.value = Number(priceEl.value || 0).toFixed(2);
                }

                if (product && product.has_variations && product.variations.length) {
                    product.variations.forEach(v => {
                        const opt = document.createElement('option');
                        opt.value = v.id;
                        opt.textContent = v.label;
                        opt.selected = String(v.id) === String(data.product_variation_id ?? '');
                        variationSelect.appendChild(opt);
                    });
                    variationSelect.disabled = false;
                } else {
                    variationSelect.disabled = true;
                }

                calculateRow(row);
            });

            calculateRow(row);
            updateRowNumbers();
        }

        function createInput(index, type, field, value, step) {
            const input = document.createElement('input');
            input.type = type;
            input.step = step;
            input.value = value;
            input.className = 'form-control form-control-sm text-right';
            input.name = `import_products[${index}][${field}]`;
            return input;
        }

        function calculateRow(row) {
            const pcs = Number(row.querySelector('.pcs-per-carton').value) || 0;
            const cartons = Number(row.querySelector('.carton-qty').value) || 0;
            const price = Number(row.querySelector('.price-pc').value) || 0;

            const totalPieces = pcs * cartons;
            const amount = totalPieces * price;

            row.querySelector('.total-pcs').value = totalPieces;
            row.querySelector('.product-amount').value = amount.toFixed(2);

            const saleEl = row.querySelector('.sale-price');
            const pctEl = row.querySelector('.sale-percent');
            if (saleEl && pctEl && (pctEl.value === '' || pctEl.value === '0' || pctEl.value === '0.00')) {
                if (Number(saleEl.value)) syncPercentFromSale(row);
            }

            calculateTotal();
            recalcCosts();
        }

        // Sync sale price → percentage based on price/pc
        function syncPercentFromSale(row) {
            const price = Number(row.querySelector('.price-pc').value) || 0;
            const sale = Number(row.querySelector('.sale-price').value) || 0;
            const pct = price > 0 ? ((sale - price) / price) * 100 : 0;
            row.querySelector('.sale-percent').value = pct.toFixed(2);
        }

        // Sync percentage → sale price based on price/pc
        function syncSaleFromPercent(row) {
            const price = Number(row.querySelector('.price-pc').value) || 0;
            const pct = Number(row.querySelector('.sale-percent').value) || 0;
            const sale = price * (1 + pct / 100);
            row.querySelector('.sale-price').value = sale.toFixed(2);
        }

        function calculateAllRows() {
            rowsContainer.querySelectorAll('.import-product-row').forEach(calculateRow);
        }

        function calculateTotal() {
            let total = 0;

            rowsContainer.querySelectorAll('.product-amount').forEach(input => {
                total += Number(input.value) || 0;
            });

            document.getElementById('importProductsTotal').textContent =
                total.toFixed(2);
        }

        function updateRowNumbers() {
            rowsContainer.querySelectorAll('.import-product-row').forEach((row, i) => {
                row.querySelector('.product-row-number').textContent = i + 1;
            });
        }

        addBtn.addEventListener('click', () => addProductRow());

        rowsContainer.addEventListener('change', function(event) {
            const row = event.target.closest('.import-product-row');
            if (!row) return;
            if (event.target.matches('input, select')) calculateRow(row);
        });

        rowsContainer.addEventListener('input', function(event) {
            const row = event.target.closest('.import-product-row');
            if (!row) return;

            // Two-way sync between sale price and percentage
            if (event.target.classList.contains('sale-price')) {
                syncPercentFromSale(row);
                calculateRow(row);
                return;
            }
            if (event.target.classList.contains('sale-percent')) {
                syncSaleFromPercent(row);
                calculateRow(row);
                return;
            }
            if (event.target.classList.contains('price-pc')) {
                // If price/pc changes, keep the sale price fixed and re-derive %
                // (Alternative: keep % fixed and re-derive sale price — uncomment if preferred)
                syncPercentFromSale(row);
            }

            calculateRow(row);
        });

        rowsContainer.addEventListener('click', function(event) {
            const button = event.target.closest('.remove-import-product');
            if (!button) return;

            button.closest('tr').remove();
            updateRowNumbers();
            calculateTotal();
            recalcCosts();
        });

        if (existingRows.length) {
            existingRows.forEach(row => addProductRow(row));
        } else {
            addProductRow();
        }

        // Reindex input names after deleting rows.
        function reindexProductInputs() {
            rowsContainer.querySelectorAll('.import-product-row').forEach((row, i) => {
                const fields = [
                    ['.product-id', 'product_id'],
                    ['.variation-select', 'product_variation_id'],
                    ['.unit-select', 'unit_id'],
                    ['.pcs-per-carton', 'pcs_per_carton'],
                    ['.carton-qty', 'ctn_qty'],
                    ['.total-pcs', 'total_pcs'],
                    ['.price-pc', 'price_pc'],
                    ['.sale-price', 'sale_price'],
                    ['.sale-percent', 'sale_percent'],
                    ['.product-amount', 'amount']
                ];

                fields.forEach(([selector, field]) => {
                    row.querySelector(selector).name =
                        `import_products[${i}][${field}]`;
                });
            });
        }

        rowsContainer.addEventListener('blur', function(event) {
            const input = event.target;
            if (input.tagName !== 'INPUT' || input.type !== 'number') return;
            if (input.readOnly) return;

            const raw = input.value;
            if (raw === '' || raw === null) return;

            // Only normalize integer-ish fields (not price fields with decimals)
            const integerFields = ['pcs-per-carton', 'carton-qty', 'total-pcs'];
            const isIntegerField = integerFields.some(cls => input.classList.contains(cls));

            if (isIntegerField) {
                const n = parseInt(raw, 10);
                input.value = isNaN(n) ? '' : String(n); // "01" -> "1"
            }
        }, true); // capture phase so it works for dynamically added inputs too

        rowsContainer.addEventListener('click', function(event) {
            if (event.target.closest('.remove-import-product')) {
                setTimeout(reindexProductInputs, 0);
            }
        });


        function recalcCosts() {
            // category pools from step 1: amount
            const pools = {};
            document.querySelectorAll('#expense-rows .expense-row').forEach(r => {
                const cat = r.querySelector('.expense-category').value;
                if (!cat) return;
                const amt = Number(r.querySelector('input[name*="[amount]"]').value) || 0;
                pools[cat] = (pools[cat] || 0) + amt;
            });

            const globalExpense = Number(document.querySelector('#total_expense_display').value) || 0;

            const rows = [...rowsContainer.querySelectorAll('.import-product-row')];
            let totalPcs = 0;
            const pcsByCat = {};
            rows.forEach(r => {
                const pcs = Number(r.querySelector('.total-pcs').value) || 0;
                totalPcs += pcs;
                const c = r.dataset.categoryId;
                if (c) pcsByCat[c] = (pcsByCat[c] || 0) + pcs;
            });

            rows.forEach(r => {
                const price = Number(r.querySelector('.price-pc').value) || 0;
                const c = r.dataset.categoryId;
                const catPerPc = (c && pcsByCat[c] > 0) ? (pools[c] || 0) / pcsByCat[c] : 0;
                const globalPerPc = totalPcs > 0 ? globalExpense / totalPcs : 0;
                const cost = price + catPerPc + globalPerPc;

                const costInput = r.querySelector('.cost-pc');
                costInput.value = cost.toFixed(2);

                // Hover text on the cost input and its cell
                const tip = `Price: ${price.toFixed(2)}\n` +
                    `Category expense: ${catPerPc.toFixed(2)}\n` +
                    `Total expense: ${globalPerPc.toFixed(2)}\n` +
                    `Cost: ${cost.toFixed(2)}`;
                costInput.title = tip;
                costInput.closest('td').title = tip;
            });
        }

        document.getElementById('expense-rows').addEventListener('input', recalcCosts);
        document.getElementById('expense-rows').addEventListener('change', recalcCosts);

        // ===== Auto-calculate Total Expense =====
        const expenseFields = ['freight_expense', 'fta_expense', 'fitting_expense', 'co_expense',
            'other_expense'
        ];

        function calculateTotalExpense() {
            let total = 0;
            expenseFields.forEach(function(field) {
                const input = document.querySelector(`[name="${field}"]`);
                if (input) total += parseFloat(input.value) || 0;
            });

            const totalField = document.querySelector('#total_expense_display');
            if (totalField) totalField.value = total.toFixed(2);
            recalcCosts();
        }

        // Attach listeners to all expense inputs
        expenseFields.forEach(function(field) {
            const input = document.querySelector(`[name="${field}"]`);
            if (input) {
                input.addEventListener('input', calculateTotalExpense);
                input.addEventListener('change', calculateTotalExpense);
            }
        });

        // Run once on page load (in case of edit mode with existing values)
        calculateTotalExpense();

        showStep(1);
    });
</script>
