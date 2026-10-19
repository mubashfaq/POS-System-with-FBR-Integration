@php
    $isEdit = isset($import);
    $model = $isEdit ? $import : null;

    $v = function ($field, $default = '') use ($model) {
        return old($field, $model->{$field} ?? $default);
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
                                <label>Import No <span class="text-danger">*</span></label>
                                <input type="text" name="import_no" class="form-control" placeholder="IMP-001"
                                    value="{{ $v('import_no') }}" required>
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
                                <input type="text" name="currency" class="form-control" placeholder="PKR (₨)"
                                    value="{{ $v('currency') }}">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Ex Rate</label>
                                <input type="number" step="0.01" name="ex_rate" class="form-control"
                                    placeholder="0.00" value="{{ $v('ex_rate') }}">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Inv Amount</label>
                                <input type="number" step="0.01" name="inv_amount" class="form-control"
                                    placeholder="0.00" value="{{ $v('inv_amount') }}">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Inv Amount / PKR</label>
                                <input type="number" step="0.01" name="inv_amount_pkr" class="form-control"
                                    placeholder="0.00" value="{{ $v('inv_amount_pkr') }}">
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
                        <h5 class="import-section-title">Category Expense Details</h5>
                        <p class="import-section-subtitle">
                            Select category, enter CBF/CBM & amount. Use <b>+</b> to add more rows.
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
                                    <label>Expense Category</label>
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

                                {{-- Amount --}}
                                <div class="col-md-3">
                                    <label>Amount</label>
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
                                <label>Total Expense 2</label>
                                <input type="number" step="0.01" name="total_expense_2" class="form-control"
                                    placeholder="0.00" value="{{ $v('total_expense_2') }}">
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
                                    <th style="min-width:150px;">Variation</th>
                                    <th style="min-width:130px;">Packing</th>
                                    <th style="min-width:100px;">Pcs / Ctn</th>
                                    <th style="min-width:100px;">Ctn Qty</th>
                                    <th>Total Pcs</th>
                                    <th style="min-width:110px;">Price / Pc</th>
                                    <th>Price / Dz</th>
                                    <th>Amount</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="importProductRows"></tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="10" class="text-right">Total Amount</th>
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
                {{ $isEdit ? 'Update Record' : 'Save Record' }}
            </button>
        </div>


    </div>
</form>

<script>
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
                        <label>Expense Category</label>
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
                        <label>Amount</label>
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

        const products = {!! json_encode(
            $products->map(
                    fn($p) => [
                        'id' => $p->id,
                        'name' => $p->name,
                        'sku' => $p->sku,
                        'has_variations' => (bool) $p->has_variations,
                        'variations' => $p->has_variations
                            ? $p->variations->map(
                                    fn($v) => [
                                        'id' => $v->id,
                                        'label' => $v->label,
                                    ],
                                )->values()
                            : [],
                    ],
                )->values(),
        ) !!};

        const units = @json($units->map(fn($u) => ['id' => $u->id, 'name' => $u->title])->values());

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
            const importNo = document.querySelector('[name="import_no"]');

            if (!importNo.value.trim()) {
                importNo.reportValidity();
                importNo.focus();
                return;
            }

            showStep(2);
        });

        prevBtn.addEventListener('click', () => showStep(1));

        document.querySelectorAll('.import-step').forEach(button => {
            button.addEventListener('click', function() {
                const targetStep = Number(this.dataset.step);

                if (targetStep === 2) {
                    const importNo = document.querySelector('[name="import_no"]');

                    if (!importNo.value.trim()) {
                        importNo.reportValidity();
                        importNo.focus();
                        return;
                    }
                }

                showStep(targetStep);
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

        function addProductRow(data = {}) {
            const index = rowIndex++;
            const row = document.createElement('tr');
            row.className = 'import-product-row';

            const productSelect = addOptions(products, data.product_id, 'Select product');
            productSelect.classList.add('product-select');
            productSelect.name = `import_products[${index}][product_id]`;

            // Variation select (populated based on chosen product)
            const variationSelect = document.createElement('select');
            variationSelect.className = 'form-control form-control-sm variation-select';
            variationSelect.name = `import_products[${index}][product_variation_id]`;
            variationSelect.innerHTML = '<option value="">—</option>';

            const unitSelect = addOptions(units, data.unit_id, 'Select packing');
            unitSelect.classList.add('unit-select');
            unitSelect.name = `import_products[${index}][unit_id]`;

            const codeInput = document.createElement('input');
            codeInput.type = 'text';
            codeInput.className = 'form-control form-control-sm product-code';
            codeInput.readOnly = true;

            const pcsInput = createInput(index, 'number', 'pcs_per_carton', data.pcs_per_carton ?? 1, '1');
            pcsInput.min = '1';
            pcsInput.classList.add('pcs-per-carton');

            const cartonInput = createInput(index, 'number', 'ctn_qty', data.ctn_qty ?? 0, '1');
            cartonInput.min = '0';
            cartonInput.classList.add('carton-qty');

            const totalInput = createInput(index, 'number', 'total_pcs', 0, '1');
            totalInput.readOnly = true;
            totalInput.classList.add('total-pcs');

            const pricePcInput = createInput(index, 'number', 'price_pc', data.price_pc ?? 0, '0.01');
            pricePcInput.min = '0';
            pricePcInput.classList.add('price-pc');

            const priceDzInput = createInput(index, 'number', 'price_dz', 0, '0.01');
            priceDzInput.readOnly = true;
            priceDzInput.classList.add('price-dz');

            const amountInput = createInput(index, 'number', 'amount', 0, '0.01');
            amountInput.readOnly = true;
            amountInput.classList.add('product-amount');

            const cells = [
                document.createTextNode(''), codeInput, productSelect, variationSelect, unitSelect,
                pcsInput, cartonInput,
                totalInput, pricePcInput, priceDzInput, amountInput
            ];

            cells.forEach((content, cellIndex) => {
                const td = document.createElement('td');

                if (cellIndex === 0) {
                    td.className = 'product-row-number';
                } else {
                    td.appendChild(content);
                }

                row.appendChild(td);
            });

            const actionCell = document.createElement('td');
            const removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.className = 'btn btn-sm btn-outline-danger remove-import-product';
            removeButton.innerHTML = '<i class="fas fa-trash"></i>';
            removeButton.title = 'Remove product';
            actionCell.appendChild(removeButton);
            row.appendChild(actionCell);

            rowsContainer.appendChild(row);

            const selectedProduct = products.find(
                p => String(p.id) === String(data.product_id ?? '')
            );

            codeInput.value = selectedProduct?.sku ?? '';

            // Populate variations if product has them
            // Populate variations if product has them
            if (selectedProduct && selectedProduct.has_variations) {
                variationSelect.innerHTML = '<option value="">—</option>';
                selectedProduct.variations.forEach(v => {
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
            const priceDozen = price * 12;
            const amount = totalPieces * price;

            row.querySelector('.total-pcs').value = totalPieces;
            row.querySelector('.price-dz').value = priceDozen.toFixed(2);
            row.querySelector('.product-amount').value = amount.toFixed(2);

            calculateTotal();
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

            if (event.target.classList.contains('product-select')) {
                const product = products.find(
                    p => String(p.id) === String(event.target.value)
                );

                row.querySelector('.product-code').value = product?.sku ?? '';

                // --- NEW: repopulate variation dropdown ---
                const variationSelect = row.querySelector('.variation-select');
                variationSelect.innerHTML = '<option value="">—</option>';

                if (product && product.has_variations && product.variations.length) {
                    product.variations.forEach(v => {
                        const opt = document.createElement('option');
                        opt.value = v.id;
                        opt.textContent = v.label;
                        variationSelect.appendChild(opt);
                    });
                    variationSelect.disabled = false;
                } else {
                    variationSelect.disabled = true;
                }
            }

            calculateRow(row);
        });

        rowsContainer.addEventListener('input', function(event) {
            const row = event.target.closest('.import-product-row');
            if (row) calculateRow(row);
        });

        rowsContainer.addEventListener('click', function(event) {
            const button = event.target.closest('.remove-import-product');
            if (!button) return;

            button.closest('tr').remove();
            updateRowNumbers();
            calculateTotal();
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
                    ['.product-select', 'product_id'],
                    ['.variation-select', 'product_variation_id'],
                    ['.unit-select', 'unit_id'],
                    ['.pcs-per-carton', 'pcs_per_carton'],
                    ['.carton-qty', 'ctn_qty'],
                    ['.total-pcs', 'total_pcs'],
                    ['.price-pc', 'price_pc'],
                    ['.price-dz', 'price_dz'],
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

        showStep(1);
    });
</script>
