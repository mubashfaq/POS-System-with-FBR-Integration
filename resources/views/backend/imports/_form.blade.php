@php
    $isEdit = isset($import);
    $model = $isEdit ? $import : null;

    $v = function ($field, $default = '') use ($model) {
        return old($field, $model->{$field} ?? $default);
    };
@endphp
<link rel="stylesheet" href="{{ asset('css/import.css') }}">
<form action="{{ $isEdit ? route('backend.admin.imports.update', $model->id) : route('backend.admin.imports.store') }}"
    method="POST">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="card-body">

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
                            <input type="number" step="0.01" name="ex_rate" class="form-control" placeholder="0.00"
                                value="{{ $v('ex_rate') }}">
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
                                <input type="number" step="0.01" name="expenses[{{ $i }}][cbf_cbm]"
                                    class="form-control cbf-input" placeholder="0.00"
                                    value="{{ $expense->cbf_cbm ?? '' }}">
                            </div>

                            {{-- Amount --}}
                            <div class="col-md-3">
                                <label>Amount</label>
                                <input type="number" step="0.01" name="expenses[{{ $i }}][amount]"
                                    class="form-control" placeholder="0.00" value="{{ $expense->amount ?? '' }}">
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

        <div class="card-footer import-footer text-right">
            <a href="{{ route('backend.admin.imports.index') }}" class="btn btn-import-cancel">
                <i class="fas fa-times mr-1"></i> Cancel
            </a>

            <button type="submit" class="btn btn-import-save">
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
</script>
