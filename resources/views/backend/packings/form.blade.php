@extends('backend.master')

@php
    $isEdit = isset($packing) && $packing;
    $isUsed = $isEdit ? (bool) ($isUsed ?? false) : false;

    $pageTitle = $isEdit ? 'Edit Packing' : 'Create Packing';
    $submitLabel = $isEdit ? 'Update Packing' : 'Create Packing';

    $formAction = $isEdit
        ? route('backend.admin.packings.update', $packing->id)
        : route('backend.admin.packings.store');

    $initialLevels = old('levels');

    if (!$initialLevels) {
        $initialLevels = $isEdit
            ? $packing->levels
                ->map(
                    fn($level) => [
                        'unit_id' => $level->unit_id,
                        'contains_qty' => (float) $level->contains_qty,
                    ],
                )
                ->values()
                ->all()
            : [
                ['unit_id' => $units->values()->get(0)?->id ?? '', 'contains_qty' => 10],
                [
                    'unit_id' => $units->values()->get(1)?->id ?? ($units->values()->get(0)?->id ?? ''),
                    'contains_qty' => '',
                ],
            ];
    }
@endphp

@section('title', $pageTitle)

@push('style')
    <style>
        .packing-page {
            --packing-orange: #fd6802;
            --packing-orange-soft: rgba(253, 104, 2, .09);
            --packing-border: #e9edf2;
            --packing-muted: #77808d;
        }

        .packing-page .packing-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 22px;
        }

        .packing-page .packing-heading {
            margin: 0;
            color: #252b36;
            font-size: 20px;
            font-weight: 700;
        }

        .packing-page .packing-subheading {
            display: block;
            margin-top: 5px;
            color: var(--packing-muted);
            font-size: 13px;
        }

        .packing-page .packing-panel {
            overflow: hidden;
            border: 1px solid var(--packing-border);
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 3px 14px rgba(25, 35, 50, .035);
        }

        .packing-page .panel-heading {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 18px 22px;
            border-bottom: 1px solid var(--packing-border);
        }

        .packing-page .panel-icon {
            display: inline-flex;
            width: 38px;
            height: 38px;
            flex: 0 0 38px;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: var(--packing-orange-soft);
            color: var(--packing-orange);
            font-size: 16px;
        }

        .packing-page .panel-title {
            margin: 0;
            color: #252b36;
            font-size: 15px;
            font-weight: 700;
        }

        .packing-page .panel-description {
            display: block;
            margin-top: 3px;
            color: var(--packing-muted);
            font-size: 12px;
        }

        .packing-page .panel-body {
            padding: 22px;
        }

        .packing-page .form-label {
            margin-bottom: 8px;
            color: #414957;
            font-size: 13px;
            font-weight: 600;
        }

        .packing-page .form-control {
            min-height: 43px;
            border: 1px solid #dfe4ea;
            border-radius: 7px;
            color: #303846;
            box-shadow: none;
        }

        .packing-page .form-control:focus {
            border-color: var(--packing-orange);
            box-shadow: 0 0 0 3px rgba(253, 104, 2, .10);
        }

        .packing-page .form-control::placeholder {
            color: #a3aab4;
        }

        .packing-page .field-hint {
            display: block;
            margin-top: 6px;
            color: var(--packing-muted);
            font-size: 11px;
        }

        .packing-page .status-card {
            display: flex;
            min-height: 50px;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 13px 15px;
            border: 1px solid var(--packing-border);
            border-radius: 9px;
            background: #fafbfc;
        }

        .packing-page .status-title {
            display: block;
            color: #303846;
            font-size: 13px;
            font-weight: 600;
        }

        .packing-page .status-description {
            display: block;
            margin-top: 3px;
            color: var(--packing-muted);
            font-size: 11px;
        }

        .packing-page .custom-control.custom-switch {
            padding-left: 2.5rem;
            min-height: 1.5rem;
        }

        .packing-page .custom-control-label {
            cursor: pointer;
        }

        .packing-page .custom-control-label::before {
            width: 2rem;
            height: 1.1rem;
            left: -2.5rem;
            top: .15rem;
            border-color: #cbd2da;
            background-color: #dfe4ea;
            box-shadow: none;
        }

        .packing-page .custom-control-label::after {
            width: calc(1.1rem - 4px);
            height: calc(1.1rem - 4px);
            top: calc(.15rem + 2px);
            left: calc(-2.5rem + 2px);
            background-color: #fff;
        }

        .packing-page .custom-control-input:checked~.custom-control-label::before {
            border-color: var(--packing-orange);
            background-color: var(--packing-orange);
        }

        .packing-page .custom-control-input:focus~.custom-control-label::before {
            box-shadow: 0 0 0 3px rgba(253, 104, 2, .12);
        }

        .packing-page .hierarchy-note {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 18px;
            padding: 12px 14px;
            border: 1px solid #f1e2d7;
            border-radius: 8px;
            background: #fffaf6;
            color: #76513b;
            font-size: 12px;
            line-height: 1.6;
        }

        .packing-page .hierarchy-note i {
            margin-top: 2px;
            color: var(--packing-orange);
        }

        .packing-page .level-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .packing-page .level-card {
            display: grid;
            grid-template-columns: 54px minmax(170px, 1fr) minmax(190px, 1fr) 42px;
            align-items: center;
            gap: 14px;
            padding: 15px;
            border: 1px solid var(--packing-border);
            border-radius: 10px;
            background: #fff;
            transition: border-color .15s ease, box-shadow .15s ease;
        }

        .packing-page .level-card:hover {
            border-color: #f5c4a5;
            box-shadow: 0 3px 12px rgba(35, 45, 60, .04);
        }

        .packing-page .level-number {
            display: flex;
            width: 42px;
            height: 42px;
            align-items: center;
            justify-content: center;
            border-radius: 9px;
            background: var(--packing-orange-soft);
            color: var(--packing-orange);
            font-size: 14px;
            font-weight: 800;
        }

        .packing-page .level-field label {
            display: block;
            margin-bottom: 6px;
            color: #697281;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .35px;
        }

        .packing-page .level-field .form-control {
            min-height: 40px;
            font-size: 13px;
        }

        .packing-page .quantity-control {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .packing-page .quantity-control .form-control {
            min-width: 70px;
        }

        .packing-page .quantity-suffix {
            color: var(--packing-muted);
            font-size: 11px;
            white-space: nowrap;
        }

        .packing-page .base-unit-label {
            display: inline-flex;
            min-height: 40px;
            align-items: center;
            gap: 7px;
            color: #16845b;
            font-size: 12px;
            font-weight: 700;
        }

        .packing-page .remove-level {
            display: inline-flex;
            width: 36px;
            height: 36px;
            align-items: center;
            justify-content: center;
            border: 1px solid #f2d6d6;
            border-radius: 7px;
            background: #fff;
            color: #dc4545;
        }

        .packing-page .remove-level:hover {
            background: #fff2f2;
        }

        .packing-page .add-level-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 13px;
            border: 1px solid rgba(253, 104, 2, .35);
            border-radius: 7px;
            background: var(--packing-orange-soft);
            color: var(--packing-orange);
            font-size: 12px;
            font-weight: 700;
        }

        .packing-page .add-level-btn:hover {
            border-color: var(--packing-orange);
            background: rgba(253, 104, 2, .14);
        }

        .packing-page .packing-preview {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-top: 18px;
            padding: 15px 16px;
            border: 1px solid #f3d5c1;
            border-radius: 9px;
            background: #fffaf6;
        }

        .packing-page .preview-icon {
            display: flex;
            width: 34px;
            height: 34px;
            flex: 0 0 34px;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            background: var(--packing-orange-soft);
            color: var(--packing-orange);
        }

        .packing-page .preview-label {
            display: block;
            margin-bottom: 4px;
            color: #8b684f;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        .packing-page .preview-value {
            color: #343b46;
            font-size: 14px;
            font-weight: 700;
            line-height: 1.6;
            overflow-wrap: anywhere;
        }

        .packing-page .form-footer {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
            padding-bottom: 20px;
        }

        .packing-page .btn-packing-primary {
            min-height: 42px;
            padding: 10px 20px;
            border: 1px solid var(--packing-orange);
            border-radius: 7px;
            background: var(--packing-orange);
            color: #fff;
            font-size: 13px;
            font-weight: 700;
        }

        .packing-page .btn-packing-primary:hover {
            border-color: #e75c00;
            background: #e75c00;
            color: #fff;
        }

        .packing-page .btn-packing-secondary {
            min-height: 42px;
            padding: 10px 18px;
            border: 1px solid #dfe4ea;
            border-radius: 7px;
            background: #fff;
            color: #596273;
            font-size: 13px;
            font-weight: 600;
        }

        .packing-page .btn-packing-secondary:hover {
            background: #f7f8fa;
        }

        .packing-page .locked-alert {
            margin-bottom: 20px;
            border: 1px solid #f4d69b;
            border-radius: 9px;
            background: #fff9eb;
            color: #75531c;
            font-size: 12px;
            line-height: 1.6;
        }

        @media (max-width: 767.98px) {
            .packing-page .packing-header {
                align-items: flex-start;
            }

            .packing-page .panel-heading,
            .packing-page .panel-body {
                padding: 16px;
            }

            .packing-page .level-card {
                grid-template-columns: 42px minmax(0, 1fr) 36px;
                gap: 12px;
            }

            .packing-page .level-card .unit-field {
                grid-column: 2;
            }

            .packing-page .level-card .quantity-field {
                grid-column: 2;
            }

            .packing-page .level-card .level-action {
                grid-column: 3;
                grid-row: 1;
            }

            .packing-page .level-number {
                grid-column: 1;
                grid-row: 1 / span 2;
            }

            .packing-page .form-footer {
                flex-direction: column-reverse;
                align-items: stretch;
            }

            .packing-page .form-footer .btn {
                width: 100%;
            }
        }
    </style>
@endpush

@section('content')
    <div class="packing-page">
        <div class="mt-n5 mb-3 d-flex justify-content-end">
            <a href="{{ route('backend.admin.packings.index') }}" class="btn btn-packing-secondary">
                <i class="fas fa-arrow-left mr-1"></i> Back to Packings
            </a>
        </div>

        @if ($isEdit && $isUsed)
            <div class="alert locked-alert d-flex align-items-start" role="alert">
                <i class="fas fa-lock mr-2 mt-1"></i>
                <div>
                    <strong>Hierarchy locked</strong><br>
                    This packing is already used in products, imports, or orders. Its level structure cannot be modified.
                    You can still update the packing name and active status.
                </div>
            </div>
        @endif

        <form action="{{ $formAction }}" method="POST" id="packingForm">
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            <div class="packing-panel mb-4">
                <div class="panel-heading">
                    <span class="panel-icon"><i class="fas fa-box-open"></i></span>
                    <div>
                        <h5 class="panel-title">Packing Information</h5>
                        <span class="panel-description">Basic details and availability of this packing.</span>
                    </div>
                </div>

                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="name" class="form-label">Packing Name <span class="text-danger">*</span></label>
                            <input type="text" id="name" name="name" maxlength="150" required
                                value="{{ old('name', $isEdit ? $packing->name : '') }}"
                                class="form-control @error('name') is-invalid @enderror"
                                placeholder="e.g. Carton 24, Bottle Box">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label d-block">Status</label>
                            <div class="status-card">
                                <div>
                                    <span class="status-title">Active Packing</span>
                                </div>
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" name="is_active" value="1"
                                        id="is_active"
                                        {{ old('is_active', $isEdit ? $packing->is_active : 1) ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="is_active"></label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="packing-panel">
                <div class="panel-heading">
                    <span class="panel-icon"><i class="fas fa-layer-group"></i></span>
                    <div class="flex-grow-1">
                        <h5 class="panel-title">Packing Hierarchy</h5>
                        <span class="panel-description">Define how larger units convert into smaller units.</span>
                    </div>
                    @if (!$isUsed)
                        <button type="button" class="add-level-btn" id="addLevelBtn">
                            <i class="fas fa-plus"></i> Add Level
                        </button>
                    @endif
                </div>

                <div class="panel-body">
                    <div class="hierarchy-note">
                        <i class="fas fa-info-circle"></i>
                        <div>
                            <strong>How it works:</strong>
                            Start with the largest packing and move down to the smallest unit.
                            The last level is always the base unit and contains 1.
                            You can configure up to 3 levels.
                        </div>
                    </div>

                    @if ($errors->has('levels') || $errors->has('levels.*'))
                        <div class="alert alert-danger py-2 small">
                            Please review the packing hierarchy fields below.
                            @foreach ($errors->get('levels.*') as $messages)
                                @foreach ($messages as $message)
                                    <div>{{ $message }}</div>
                                @endforeach
                            @endforeach
                        </div>
                    @endif

                    <div id="levelsList" class="level-list"></div>

                    <div class="packing-preview">
                        <span class="preview-icon"><i class="fas fa-calculator"></i></span>
                        <div>
                            <span class="preview-label">Live Conversion Preview</span>
                            <div id="summaryPreview" class="preview-value">-</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-footer">
                <a href="{{ route('backend.admin.packings.index') }}" class="btn btn-packing-secondary">Cancel</a>
                <button type="submit" class="btn btn-packing-primary">
                    <i class="fas {{ $isEdit ? 'fa-save' : 'fa-plus' }} mr-1"></i>
                    {{ $submitLabel }}
                </button>
            </div>
        </form>
    </div>
@endsection

@push('script')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const availableUnits = @json($units);
            const isUsed = @json($isUsed);
            let levels = @json($initialLevels);

            const levelsList = document.getElementById('levelsList');
            const addLevelBtn = document.getElementById('addLevelBtn');
            const summaryPreview = document.getElementById('summaryPreview');
            const form = document.getElementById('packingForm');

            if (!Array.isArray(levels) || levels.length === 0) {
                levels = [{
                    unit_id: availableUnits[0]?.id || '',
                    contains_qty: 10
                }, {
                    unit_id: availableUnits[1]?.id || availableUnits[0]?.id || '',
                    contains_qty: ''
                }];
            }

            // Keep unit labels safe when inserting them into the generated markup.
            const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, char => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            })[char]);

            function getUnitTitle(id) {
                const unit = availableUnits.find(item => String(item.id) === String(id));
                return unit ? unit.title : 'Unit';
            }

            function formatQuantity(value) {
                const number = Number(value);
                if (!Number.isFinite(number)) return '0';
                return Number.isInteger(number) ? String(number) : String(Number(number.toFixed(4)));
            }

            function updatePreview() {
                if (!levels.length) {
                    summaryPreview.textContent = '-';
                    return;
                }

                const quantities = new Array(levels.length).fill(1);

                for (let i = levels.length - 2; i >= 0; i--) {
                    const contains = Number.parseFloat(levels[i].contains_qty);
                    quantities[i] = (Number.isFinite(contains) && contains > 0 ? contains : 1) * quantities[i + 1];
                }

                const topQuantity = quantities[0];

                summaryPreview.textContent = levels.map((level, index) => {
                    const ratio = quantities[index] > 0 ? topQuantity / quantities[index] : 0;
                    return `${formatQuantity(ratio)} ${getUnitTitle(level.unit_id)}`;
                }).join(' = ');
            }

            function renderLevels() {
                levelsList.innerHTML = '';

                levels.forEach((level, index) => {
                    const isLast = index === levels.length - 1;
                    const options = availableUnits.map(unit => `
                <option value="${escapeHtml(unit.id)}" ${String(unit.id) === String(level.unit_id) ? 'selected' : ''}>
                    ${escapeHtml(unit.title)} (${escapeHtml(unit.short_name)})
                </option>
            `).join('');

                    const card = document.createElement('div');
                    card.className = 'level-card';

                    card.innerHTML = `
                <div class="level-number">${index + 1}</div>

                <div class="level-field unit-field">
                    <label>Unit ${index === 0 ? '(Largest)' : isLast ? '(Base Unit)' : ''}</label>
                    <select
                        name="levels[${index}][unit_id]"
                        class="form-control unit-select"
                        data-index="${index}"
                        ${isUsed ? 'disabled' : ''}
                        required>
                        <option value="">Select unit</option>
                        ${options}
                    </select>
                    ${isUsed ? `<input type="hidden" name="levels[${index}][unit_id]" value="${escapeHtml(level.unit_id)}">` : ''}
                </div>

                <div class="level-field quantity-field">
                    <label>${isLast ? 'Base Quantity' : 'Contains Quantity'}</label>
                    ${isLast ? `
                                                            <div class="base-unit-label">
                                                                <i class="fas fa-check-circle"></i>
                                                                1 ${escapeHtml(getUnitTitle(level.unit_id))} (Base Unit)
                                                            </div>
                                                            <input type="hidden" name="levels[${index}][contains_qty]" value="1">
                                                        ` : `
                                                            <div class="quantity-control">
                                                                <input
                                                                    type="number"
                                                                    step="0.0001"
                                                                    min="0.0001"
                                                                    name="levels[${index}][contains_qty]"
                                                                    class="form-control qty-input"
                                                                    data-index="${index}"
                                                                    value="${escapeHtml(level.contains_qty ?? '')}"
                                                                    placeholder="e.g. 12"
                                                                    ${isUsed ? 'readonly' : 'required'}>
                                                                <span class="quantity-suffix">of next unit</span>
                                                            </div>
                                                        `}
                </div>

                <div class="level-action text-center">
                    ${!isUsed && isLast && levels.length > 1 ? `
                                                            <button type="button" class="remove-level" title="Remove base level" aria-label="Remove base level">
                                                                <i class="fas fa-trash-alt"></i>
                                                            </button>
                                                        ` : ''}
                </div>
            `;

                    levelsList.appendChild(card);
                });

                if (addLevelBtn) {
                    addLevelBtn.style.display = (!isUsed && levels.length < 3) ? 'inline-flex' : 'none';
                }

                updatePreview();
            }

            if (addLevelBtn) {
                addLevelBtn.addEventListener('click', function() {
                    if (levels.length >= 3) return;

                    const usedIds = levels.map(level => String(level.unit_id));
                    const nextUnit = availableUnits.find(unit => !usedIds.includes(String(unit.id))) ||
                        availableUnits[0];

                    if (levels.length && !levels[levels.length - 1].contains_qty) {
                        levels[levels.length - 1].contains_qty = 10;
                    }

                    levels.push({
                        unit_id: nextUnit?.id || '',
                        contains_qty: ''
                    });

                    renderLevels();
                });
            }

            levelsList.addEventListener('click', function(event) {
                if (event.target.closest('.remove-level')) {
                    if (levels.length > 1 && !isUsed) {
                        levels.pop();
                        renderLevels();
                    }
                }
            });

            levelsList.addEventListener('change', function(event) {
                if (event.target.matches('.unit-select')) {
                    const index = Number(event.target.dataset.index);
                    levels[index].unit_id = event.target.value;
                    renderLevels();
                }
            });

            levelsList.addEventListener('input', function(event) {
                if (event.target.matches('.qty-input')) {
                    const index = Number(event.target.dataset.index);
                    levels[index].contains_qty = event.target.value;
                    updatePreview();
                }
            });

            form.addEventListener('submit', function(event) {
                if (levels.length < 1 || levels.length > 3) {
                    event.preventDefault();
                    alert('Packing must contain between 1 and 3 levels.');
                }
            });

            renderLevels();
        });
    </script>
@endpush
