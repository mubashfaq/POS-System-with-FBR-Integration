@php
    // Existing prices for edit form
    $unitPriceValues = [];

    // Preserve submitted values after validation errors
    $oldUnitPrices = old('unit_prices');

    if (is_array($oldUnitPrices)) {
        foreach ($oldUnitPrices as $price) {
            if (isset($price['unit_id'])) {
                $unitPriceValues[$price['unit_id']] = $price['sale_price'] ?? '';
            }
        }
    } elseif (isset($product) && $product) {
        $unitPriceValues = $product->unitPrices->pluck('sale_price', 'unit_id')->all();
    }
@endphp

{{-- ========== PACKAGING UNIT PRICES ========== --}}
<div id="unitPricesCard" style="display: none;">

    <div class="unit-prices-header">
        <div class="unit-prices-heading">
            <div class="unit-prices-icon">
                <i class="fas fa-money-bill-wave"></i>
            </div>

            <div>
                <h6 class="unit-prices-title">Packaging Unit Prices</h6>
                <p class="unit-prices-description">
                    Set custom sale prices for each packaging unit.
                </p>
            </div>
        </div>

        <span class="unit-prices-badge">
            <i class="fas fa-info-circle mr-1"></i> Optional
        </span>
    </div>

    <div class="unit-prices-body">

        <div class="unit-prices-info">
            <i class="fas fa-lightbulb"></i>
            <span>
                Leave the price blank to automatically calculate it using
                <strong>Base Price × Conversion Factor</strong>.
            </span>
        </div>

        <div class="unit-prices-table-container">
            <table class="table unit-prices-table">
                <thead>
                    <tr>
                        <th>Packaging Unit</th>
                        <th>Factor (Base Qty)</th>
                        <th style="min-width: 230px;">Custom Sale Price</th>
                    </tr>
                </thead>

                <tbody id="unitPricesRows"></tbody>
            </table>
        </div>

        <div class="unit-prices-help">
            <i class="fas fa-info-circle"></i>
            Custom prices override the automatically calculated prices for the respective units.
        </div>

    </div>
</div>

@push('style')
    <style>
        /* ===============================
                       PACKAGING UNIT PRICES
                    =============================== */

        #unitPricesCard {
            background: #fff;
            border: 1px solid #e8e8e8;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 3px 15px rgba(0, 0, 0, .04);
            margin-top: 18px;
            margin-bottom: 20px;
        }

        #unitPricesCard .unit-prices-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            padding: 18px 22px;
            border-bottom: 1px solid #eeeeee;
        }

        #unitPricesCard .unit-prices-heading {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        #unitPricesCard .unit-prices-icon {
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            border-radius: 10px;
            background: #fff3e8;
            color: #fd6802;
            font-size: 17px;
        }

        #unitPricesCard .unit-prices-title {
            margin: 0;
            color: #333;
            font-size: 15px;
            font-weight: 700;
        }

        #unitPricesCard .unit-prices-description {
            margin: 4px 0 0;
            color: #888;
            font-size: 11px;
        }

        #unitPricesCard .unit-prices-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 10px;
            border-radius: 7px;
            background: #fff3e8;
            color: #fd6802;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
        }

        #unitPricesCard .unit-prices-body {
            padding: 20px 22px;
        }

        #unitPricesCard .unit-prices-info {
            display: flex;
            align-items: flex-start;
            gap: 9px;
            margin-bottom: 15px;
            padding: 11px 13px;
            border: 1px solid #f3dfcf;
            border-radius: 8px;
            background: #fffaf6;
            color: #76513b;
            font-size: 11px;
            line-height: 1.6;
        }

        #unitPricesCard .unit-prices-info i {
            margin-top: 2px;
            color: #fd6802;
        }

        #unitPricesCard .unit-prices-table-container {
            border: 1px solid #e5e5e5;
            border-radius: 9px;
            overflow: hidden;
        }

        #unitPricesCard .unit-prices-table {
            width: 100%;
            margin: 0;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 12px;
        }

        #unitPricesCard .unit-prices-table thead th {
            padding: 12px 14px;
            border: 0;
            border-bottom: 1px solid #e5e5e5;
            background: #f8f8f8;
            color: #555;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .3px;
            white-space: nowrap;
        }

        #unitPricesCard .unit-prices-table tbody td {
            padding: 12px 14px;
            border: 0;
            border-bottom: 1px solid #eeeeee;
            color: #444;
            vertical-align: middle;
        }

        #unitPricesCard .unit-prices-table tbody tr:last-child td {
            border-bottom: 0;
        }

        #unitPricesCard .unit-prices-table tbody tr:hover {
            background: #fffaf6;
        }

        #unitPricesCard .unit-price-unit {
            color: #333;
            font-size: 13px;
            font-weight: 600;
        }

        #unitPricesCard .unit-price-short-name {
            margin-left: 4px;
            color: #888;
            font-size: 11px;
        }

        #unitPricesCard .unit-price-factor {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 6px;
            background: #f3f4f6;
            color: #596273;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
        }

        #unitPricesCard .unit-price-input {
            height: 36px;
            border: 1px solid #dddddd;
            border-radius: 7px;
            color: #333;
            font-size: 12px;
            box-shadow: none;
        }

        #unitPricesCard .unit-price-input:focus {
            border-color: #fd6802;
            box-shadow: 0 0 0 2px rgba(253, 104, 2, .08);
        }

        #unitPricesCard .unit-price-input::placeholder {
            color: #aaa;
            font-size: 11px;
        }

        #unitPricesCard .unit-prices-help {
            display: flex;
            align-items: flex-start;
            gap: 7px;
            margin-top: 12px;
            color: #888;
            font-size: 11px;
            line-height: 1.5;
        }

        #unitPricesCard .unit-prices-help i {
            margin-top: 2px;
            color: #fd6802;
        }

        @media (max-width: 768px) {
            #unitPricesCard .unit-prices-header {
                align-items: flex-start;
                flex-direction: column;
                padding: 15px;
            }

            #unitPricesCard .unit-prices-body {
                padding: 15px;
            }

            #unitPricesCard .unit-prices-table {
                min-width: 600px;
            }
        }
    </style>
@endpush

@push('script')
    <script>
        // ===============================
        // PACKING & UNIT PRICES
        // Shared: Create + Edit Product
        // ===============================

        $(function() {
            const existingUnitPrices = @json($unitPriceValues);

            function escapeHtml(value) {
                return String(value ?? '').replace(/[&<>"']/g, function(char) {
                    return {
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;',
                        "'": '&#039;'
                    } [char];
                });
            }

            function filterPackings() {
                const selectedUnit = $('#product_unit_id').val();
                const packingSelect = $('#default_packing_id');
                const currentPacking = packingSelect.val();

                let currentValid = false;

                packingSelect.find('option').each(function() {
                    if (!this.value) {
                        this.disabled = false;
                        return;
                    }

                    const baseUnit = $(this).data('base-unit');

                    const isValid = selectedUnit && String(baseUnit) === String(selectedUnit);

                    // this.disabled = !isValid;

                    if (isValid && this.value === currentPacking) {
                        currentValid = true;
                    }
                });

                // if (!currentValid && currentPacking) {
                //     packingSelect.val('').trigger('change.select2');
                //     loadPackingLevels('');
                // }
            }

            function loadPackingLevels(packingId) {
                const card = $('#unitPricesCard');
                const rows = $('#unitPricesRows');

                if (!packingId) {
                    card.hide();
                    rows.empty();
                    return;
                }

                $.get(`/admin/packings/${encodeURIComponent(packingId)}/levels`)
                    .done(function(res) {
                        rows.empty();

                        if (!res.levels || !res.levels.length) {
                            card.hide();
                            return;
                        }

                        res.levels.forEach(function(lvl, idx) {
                            const existingVal = existingUnitPrices[lvl.unit_id] !== undefined ?
                                existingUnitPrices[lvl.unit_id] : '';

                            const row = `
                            <tr>
                                <td>
                                    <span class="unit-price-unit">
                                        ${escapeHtml(lvl.name)}
                                    </span>

                                    <span class="unit-price-short-name">
                                        (${escapeHtml(lvl.short_name)})
                                    </span>

                                    <input
                                        type="hidden"
                                        name="unit_prices[${idx}][unit_id]"
                                        value="${escapeHtml(lvl.unit_id)}">
                                </td>

                                <td>
                                    <span class="unit-price-factor">
                                        ${escapeHtml(lvl.base_qty)} Base Units
                                    </span>
                                </td>

                                <td>
                                    <input
                                        type="number"
                                        step="0.0001"
                                        min="0"
                                        name="unit_prices[${idx}][sale_price]"
                                        value="${escapeHtml(existingVal)}"
                                        class="form-control unit-price-input"
                                        placeholder="Leave blank for automatic price">
                                </td>
                            </tr>
                        `;

                            rows.append(row);
                        });

                        card.show();
                    })
                    .fail(function() {
                        card.hide();
                        rows.empty();

                        console.error('Unable to load packing levels.');
                    });
            }

            $('#product_unit_id')
                .off('change.unitPrices')
                .on('change.unitPrices', function() {
                    filterPackings();
                });

            $('#default_packing_id')
                .off('change.unitPrices')
                .on('change.unitPrices', function() {
                    loadPackingLevels($(this).val());
                });

            // Initial setup
            filterPackings();

            const selectedPacking = $('#default_packing_id').val();

            if (selectedPacking) {
                loadPackingLevels(selectedPacking);
            }
        });
    </script>
@endpush
