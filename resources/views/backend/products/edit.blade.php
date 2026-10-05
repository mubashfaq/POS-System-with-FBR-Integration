@extends('backend.master')

@section('title', 'Update Product')

@section('content')
<div class="card">
    <div class="card-body">
        <form action="{{ route('backend.admin.products.update',$product->id) }}" method="post" class="accountForm"
            enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <div class="card-body">
                <div class="row">
                    <div class="mb-3 col-md-6">
                        <label for="title" class="form-label">
                            Name
                            <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" placeholder="Enter title" name="name"
                            value="{{ old('name', $product->name) }}" required>
                    </div>
                    <div class="mb-3 col-md-6">
                        <label for="sku" class="form-label">
                            Sku
                            <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" placeholder="Enter sku" name="sku"
                            value="{{ old('sku',$product->sku)}}" required>
                    </div>
                    <div class="mb-3 col-md-6">
                        <label for="brand_id" class="form-label">
                            Brand
                        </label>
                        <select class="form-control select2" style="width: 100%;" name="brand_id">
                            <option value="">Select Brand</option>
                            @foreach ($brands as $item)
                            <option value={{ $item->id }} {{ $product->brand_id == $item->id ? 'selected' : '' }}>
                                {{ $item->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3 col-md-6">
                        <label for="category_id" class="form-label">
                            Category
                        </label>
                        <select class="form-control select2" style="width: 100%;" name="category_id">
                            <option value="">Select Category</option>
                            @foreach ($categories as $item)
                            <option value={{ $item->id }} {{ $product->category_id == $item->id ? 'selected' : '' }}>
                                {{ $item->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3 col-md-6">
                        <label for="price" class="form-label">
                            Price
                            <span class="text-danger">*</span>
                        </label>
                        <input type="number" step="0.01" min="0" class="form-control" placeholder="Enter price"
                            name="price" value="{{ old('price',$product->price) }}" required>
                    </div>

                    @if ($business_type === 'pos' || $business_type === 'pral')
                        <div class="mb-3 col-md-6">
                            <label for="PCTCode" class="form-label">
                            PCT Code
                                <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control " placeholder="Enter PCT Code"
                                name="PCTCode" value="{{ old('PCTCode', $product->PCTCode) }}" required>

                        </div>

                        <div class="mb-3 col-md-6">
                            <label for="pos_sale_tax" class="form-label">
                            POS Sale Tax
                                <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control " placeholder="Enter POS Sale Tax"
                                name="pos_sale_tax" value="{{ old('pos_sale_tax', $product->pos_sale_tax) }}" required>
                        </div>

                    @else

                        @if ($fbrIntegration)
                        

                            <div class="mb-3 col-md-6">
                                <label for="hsCode" class="form-label">
                                    Hs Code
                                    <span class="text-danger">*</span>
                                </label>
                                <select class="form-control select2" name="hsCode" id="hsCode" required>
                                    <option value="">Select HS Code</option>
                                    @foreach ($hsCodes as $code)
                                    <option value="{{ $code->hs_code}}"
                                        {{ $product->hsCode == $code->hs_code ? 'selected' : '' }}>
                                        {{ $code->hs_code }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
        
                            <div class="mb-3 col-md-6">
                                <label for="description" class="form-label">
                                    Description <span class="text-danger">*</span>
                                </label>
                                <textarea rows="3" cols="50" name="hs_description" id="description" class="form-control"
                                    readonly>{{$product->hs_description}}</textarea>
                            </div>
        
                            <div class="mb-3 col-md-6">
                                <label for="uom" class="form-label">
                                    UOM
                                    <span class="text-danger">*</span>
                                </label>
                                <select id="uom" class="form-control select2" style="width: 100%;" name="uom" required>
                                    <option value="">Select Unit</option>
                                    @foreach ($units as $u)
                                    <option value="{{ $u->description }}"
                                        {{ $product->uom == $u->description ? 'selected' : '' }}>
                                        {{ $u->description }}
                                    </option>
                                    @endforeach
                                </select>
        
                            </div>
        
                            <div class="mb-3 col-md-6">
                                <label for="salesTaxWithheldAtSource" class="form-label">
                                    sales Tax HeldAtSource
                                    <span class="text-danger">*</span>
                                </label>
                                <input type="number" class="form-control " placeholder="Enter sales Tax HeldAtSource"
                                    name="salesTaxWithheldAtSource" value="0" required>
        
                            </div>
        
        
                            <div class="mb-3 col-md-6">
                                <label for="sale_type" class="form-label">
                                    Sale Type
                                    <span class="text-danger">*</span>
                                </label>
                                <input type="hidden" name="sale_type_id" id="sale_type_id">
                                <select class="form-control select2" style="width: 100%;" name="sale_type" id="sale_type"
                                    required>
                                    <option value="">Select Sale Type</option>
                                    @foreach ($saleTypes as $st)
                                    <option value="{{ $st->transactioN_DESC }}" {{ $product->sale_type == $st->transactioN_DESC ? 'selected' : '' }}>
                                        {{ $st->transactioN_DESC }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
        
                            <div class="mb-3 col-md-6">
                                <label for="rate_of_tax_id" class="form-label">
                                    Rate of Tax
                                    <span class="text-danger">*</span>
                                </label>
                                <select class="form-control select2" style="width: 100%;" name="rate_of_tax" id="rate_of_tax"
                                    required>
                                    <option value="{{ $product->rate_of_tax }}">{{ $product->rate_of_tax}}</option>
        
                                </select>
        
                                <input type="hidden" name="rate_of_tax_id" id="rate_of_tax_id">
                            </div>
        
        
        
                            <!-- sro schedule -->
                            <div class="mb-3 col-md-6">
                                <label for="sro_item" class="form-label">
                                    Sro Schedule
                                    <span class="text-danger"></span>
                                </label>
                                <select class="form-control select2" style="width: 100%;" name="sro_schedule" id="sro_schedule">
                                    <option value="{{ $product->sro_schedule}}">{{ $product->sro_schedule}}</option>
        
                                </select>
        
                                <input type="hidden" id="sro_schedule_id" name="sro_schedule_id">
        
                            </div>
        
        
                            <div class="mb-3 col-md-6">
                                <label for="sro_item" class="form-label">
                                    Sro Item Code
                                    <span class="text-danger"></span>
                                </label>
                                <select class="form-control select2" style="width: 100%;" name="sro_item" id="sro_item">
                                    <option value="">Select SRO Item</option>
        
                                </select>
                            </div>

                            <div class="mb-3 col-md-6" id="retail_price_wrapper" style="display:none;">
                                <label for="fixed_notified_value_or_retail_price" class="form-label">
                                    Retail Price (MRP) <span class="text-danger">*</span>
                                </label>
                                <input type="number" step="0.01" min="1"
                                    class="form-control"
                                    name="fixed_notified_value_or_retail_price"
                                    id="fixed_notified_value_or_retail_price"
                                    value="{{ old('fixed_notified_value_or_retail_price', $product->fixed_notified_value_or_retail_price) }}"
                                    placeholder="Enter retail price">
                            </div>

                        @else
                            <div class="mb-3 col-md-6">
                                <label for="hsCode" class="form-label">Hs Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="hsCode" id="hsCode"
                                    placeholder="Enter HS Code" value="{{ $product->hsCode }}" required>
                            </div>
                        
                            <div class="mb-3 col-md-6">
                                <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                                <textarea rows="3" cols="50" name="hs_description" id="description" class="form-control"
                                    placeholder="Enter Description">{{ $product->hs_description }}</textarea>
                            </div>
                        
                            <div class="mb-3 col-md-6">
                                <label for="uom" class="form-label">UOM <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="uom" id="uom"
                                    placeholder="Enter UOM" value="{{ $product->uom }}" required>
                            </div>

                            <div class="mb-3 col-md-6">
                                <label for="salesTaxWithheldAtSource" class="form-label">
                                    sales Tax HeldAtSource <span class="text-danger">*</span>
                                </label>
                                <input type="number" class="form-control" placeholder="Enter sales Tax HeldAtSource"
                                    name="salesTaxWithheldAtSource" value="{{ $product->salesTaxWithheldAtSource }}" required>
                            </div>

                            <div class="mb-3 col-md-6">
                                <label for="sale_type" class="form-label">Sale Type <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="sale_type" id="sale_type"
                                    placeholder="Enter Sale Type" value="{{ $product->sale_type }}" required>
                            </div>
                        
                            <div class="mb-3 col-md-6">
                                <label for="rate_of_tax" class="form-label">Rate of Tax <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="rate_of_tax" id="rate_of_tax"
                                    placeholder="Enter Rate of Tax" value="{{ $product->rate_of_tax }}" required>
                            </div>
                        
                            <div class="mb-3 col-md-6">
                                <label for="sro_schedule" class="form-label">Sro Schedule</label>
                                <input type="text" class="form-control" name="sro_schedule" id="sro_schedule"
                                    placeholder="Enter Sro Schedule" value="{{ $product->sro_schedule }}">
                            </div>
                        
                            <div class="mb-3 col-md-6">
                                <label for="sro_item" class="form-label">Sro Item Code</label>
                                <input type="text" class="form-control" name="sro_item" id="sro_item"
                                    placeholder="Enter Sro Item Code" value="{{ $product->sro_item }}">
                            </div>

                            <div class="mb-3 col-md-6" id="retail_price_wrapper" style="display:none;">
                                <label for="fixed_notified_value_or_retail_price" class="form-label">
                                    Retail Price (MRP) <span class="text-danger">*</span>
                                </label>
                                <input type="number" step="0.01"  min="1"
                                    class="form-control"
                                    name="fixed_notified_value_or_retail_price"
                                    id="fixed_notified_value_or_retail_price"
                                    value="{{ old('fixed_notified_value_or_retail_price', $product->fixed_notified_value_or_retail_price) }}"
                                    placeholder="Enter retail price">
                            </div>
                                
                        @endif

                    @endif

                    <div class="mb-3 col-md-6">
                        <label for="discount_type" class="form-label">
                            Discount Type
                        </label>
                        <select class="form-control form-select" name="discount_type">
                            <option value="">Select Discount Type</option>
                            <option value="fixed" {{ $product->discount_type == 'fixed' ? 'selected' : '' }}>
                                Fixed
                            </option>
                            <option value="percentage" {{ $product->discount_type  == 'percentage' ? 'selected' : '' }}>
                                Percentage
                            </option>
                        </select>
                    </div>
                    <div class="mb-3 col-md-6">
                        <label for="discount_value" class="form-label">
                            Discount Amount
                        </label>
                        <input type="number" step="0.01" min="0" class="form-control" placeholder="Enter discount"
                            name="discount" value="{{ old('discount',$product->discount) }}">
                    </div>
                    <div class="mb-3 col-md-6">
                        <label for="purchase_price" class="form-label">
                            Purchase Price
                            <span class="text-danger">*</span>
                        </label>
                        <input type="number" step="0.01" min="0" class="form-control" placeholder="Enter purchase Price"
                            name="purchase_price" value="{{ old('purchase_price',$product->purchase_price) }}" required>
                    </div>
                    <div class="mb-3 col-md-6">
                        <label for="thumbnailInput" class="form-label">
                            Image
                        </label>
                        <div class="image-upload-container" id="imageUploadContainer">
                            <input type="file" class="form-control" name="product_image" id="thumbnailInput"
                                accept="image/*" style="display: none;">
                            <div class="thumb-preview" id="thumbPreviewContainer">
                                <img src="{{ asset('storage/' . $product->image) }}" alt="Thumbnail Preview"
                                    class="img-thumbnail" id="thumbnailPreview"
                                    onerror="this.onerror=null; this.src='{{ asset('assets/images/no-image.png') }}'">
                                <div class="upload-text d-none">
                                    <i class="fas fa-plus-circle"></i>
                                    <span>Upload Image</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3 col-md-12">
                        <label for="description" class="form-label">
                            Description
                        </label>
                        <textarea class="form-control" placeholder="Enter description"
                            name="description">{{ old('description',$product->description) }}</textarea>
                    </div>

                    <div class="mb-3 col-md-6">
                        <label for="expire_date" class="form-label">
                            Expire date
                        </label>

                        <div class="input-group date" id="reservationdate" data-target-input="nearest">
                            <input type="text" placeholder="Enter product expire date"
                                class="form-control datetimepicker-input" data-target="#reservationdate"
                                name="expire_date" value="{{ old('expire_date',$product->expire_date) }}" />
                            <div class="input-group-append" data-target="#reservationdate" data-toggle="datetimepicker">
                                <div class="input-group-text"><i class="fa fa-calendar"></i></div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3 col-md-12">
                        <div class="form-switch px-4">
                            <input type="hidden" name="status" value="0">
                            <input class="form-check-input" type="checkbox" name="status" id="active" value="1"
                                @if($product->status==1) checked @endif>
                            <label class="form-check-label" for="active">
                                Active
                            </label>
                        </div>
                    </div>
                </div>

                {{-- ========== ACCESSORIES SECTION ========== --}}
                <div class="card border mt-3">
                    <div class="card-header bg-light d-flex align-items-center justify-content-between">
                        <strong><i class="fas fa-puzzle-piece mr-2"></i> Accessories (Sub-Products)</strong>
                        <button type="button" class="btn btn-sm action-button" id="addAccessoryRow">
                            <i class="fas fa-plus"></i> Add Accessory
                        </button>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small">When this product is added to the cart, these sub-products will be automatically included.</p>
                        <table class="table table-bordered table-sm" id="accessoriesTable">
                            <thead class="thead-light">
                                <tr>
                                    <th>Sub-Product</th>
                                    <th style="width:140px">Default Qty</th>
                                    <th style="width:60px"></th>
                                </tr>
                            </thead>
                            <tbody id="accessoryRows">
                                {{-- Pre-populated by JS from selectedAccessories --}}
                            </tbody>
                        </table>
                    </div>
                </div>

                @include('backend.products._variations', ['mode' => 'edit', 'product' => $product])

                <div class="row mt-3">
                    <div class="col-md-6">
                        <button type="submit" class="btn action-button">Update</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
@push('script')
<script src="{{ asset('js/image-field.js') }}"></script>
<script>
    var fbrIntegrationEnabled = @json($fbrIntegration);
</script>
<script>
$(function() {
    //Date picker
    $('#reservationdate').datetimepicker({
        format: 'YYYY-MM-DD'
    });
})


$(document).ready(function() {
    // =========================
    // Initialize Select2
    // =========================
    $('.select2').select2();

    // =========================
    // Datepicker
    // =========================
    $('#reservationdate').datetimepicker({
        format: 'YYYY-MM-DD'
    });

    if(fbrIntegrationEnabled) {

        // =========================
        // Variables for Edit Mode
        // =========================
        let dbUom = "{{ $product->uom ?? '' }}";
        let dbSaleType = "{{ $product->sale_type ?? '' }}";
        let dbRateOfTax = "{{ $product->rate_of_tax ?? '' }}";
        let dbSroSchedule = "{{ $product->sro_schedule ?? '' }}";
        let dbSroItem = "{{ $product->sro_item ?? '' }}";

        let saleData = [];
        let sroData = [];
        let sroItemData = [];

        // =========================
        // HS Code → UOM
        // =========================
        function loadUoms(selectedHsCode, selectedUom) {
            if (!selectedHsCode) return;

            $.ajax({
                url: "{{ route('backend.admin.products.hsCode-desc', ':hs_code') }}".replace(':hs_code', selectedHsCode),
                type: 'GET',
                dataType: 'json',
                success: function(response) {

                    // Set the description
                    $('#description').val(response.description);

                    // Reset UOM dropdown
                    $('#uom').empty().append('<option value="">Select Unit</option>');

                    // Determine default UOM: edit mode or first from API
                    let defaultUom = selectedUom || (response.uom.length ? response.uom[0].description : null);

                    // Append options
                    $.each(response.uom, function(index, uom) {
                        let isSelected = uom.description === defaultUom;
                        let option = new Option(uom.description, uom.description, isSelected, isSelected);
                        $('#uom').append(option);
                    });

                    // Trigger Select2 update
                    $('#uom').trigger('change');
                },
                error: function(xhr, status, error) {
                    console.error('Error fetching units:', error);
                }
            });
        }

        // On page load (edit mode)
        if ($('#hsCode').val()) {
            loadUoms($('#hsCode').val(), dbUom);
        }

        // On HS Code change
        $('#hsCode').on('change', function() {
            loadUoms($(this).val(), null);
        });
        

        // =========================
        // Sale Type → Rate of Tax → SRO Schedule → SRO Item
        // =========================
        function loadSaleTypeData(sale_type) {
            $('#rate_of_tax').empty().append('<option value="">Select Sale Tax</option>');
            $('#rate_of_tax_id').val('');

            $('#sro_schedule').empty().append('<option value="">Select SRO Schedule</option>');
            $('#sro_schedule_id').val('');

            $('#sro_item').empty().append('<option value="">Select SRO Item</option>');
            $('#sro_item_id').val('');

            sroData = [];
            sroItemData = [];

            if (!sale_type) return;

            $.ajax({
                url: "{{ route('backend.admin.products.sale-type', ':sale_type') }}"
                    .replace(':sale_type', sale_type),
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    saleData = response.sale_data || [];

                    // Populate Rate of Tax
                    saleData.forEach(function(item) {
                        $('#rate_of_tax').append(new Option(item.ratE_DESC, item.ratE_DESC));
                    });

                    // Edit mode: select saved Rate of Tax
                    if (dbRateOfTax) {
                        $('#rate_of_tax').val(dbRateOfTax).trigger('change');
                    }
                }
            });
        }

        // Sale Type change event
        $('#sale_type').on('change', function() {
            let sale_type = $(this).val();
            loadSaleTypeData(sale_type);
        });

        // Retail Price field sirf 3rd Schedule Goods par dikhao
        function toggleRetailPrice() {
            const v = ($('#sale_type').val() || '').toLowerCase().replace(/\s+/g, ' ').trim();
            const on = v.indexOf('3rd schedule') !== -1;

            $('#retail_price_wrapper').toggle(on);
            $('#fixed_notified_value_or_retail_price').prop('required', on);
            if (!on) $('#fixed_notified_value_or_retail_price').val('');
        }

$('#sale_type').on('change', toggleRetailPrice);

        // Rate of Tax → SRO Schedule
        $('#rate_of_tax').on('change', function() {
            let selectedDesc = $(this).val();

            $('#sro_schedule').empty().append('<option value="">Select SRO Schedule</option>');
            $('#sro_schedule_id').val('');
            $('#sro_item').empty().append('<option value="">Select SRO Item</option>');
            $('#sro_item_id').val('');

            sroData = [];
            sroItemData = [];

            let selectedItem = saleData.find(item => item.ratE_DESC === selectedDesc);
            if (!selectedItem) return;

            $('#rate_of_tax_id').val(selectedItem.ratE_ID);

            // Fetch SRO Schedules
            $.ajax({
                url: "{{ route('backend.admin.products.sro-schedule', ':rate_tax_id') }}"
                    .replace(':rate_tax_id', selectedItem.ratE_ID),
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    sroData = response.sro_data || [];

                    sroData.forEach(function(item) {
                        $('#sro_schedule').append(new Option(item.srO_DESC, item.srO_DESC));
                    });

                    // Edit mode: select saved SRO Schedule
                    if (dbSroSchedule) {
                        $('#sro_schedule').val(dbSroSchedule).trigger('change');
                    }
                }
            });
        });

        // SRO Schedule → SRO Item
        $('#sro_schedule').on('change', function() {
            let selectedDesc = $(this).val();

            $('#sro_item').empty().append('<option value="">Select SRO Item</option>');
            $('#sro_item_id').val('');

            sroItemData = [];

            let selectedItem = sroData.find(item => item.srO_DESC === selectedDesc);
            if (!selectedItem) return;

            $('#sro_schedule_id').val(selectedItem.srO_ID);

            $.ajax({
                url: "{{ route('backend.admin.products.sro-Items-details', ':sro_schedule_id') }}"
                    .replace(':sro_schedule_id', selectedItem.srO_ID),
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    sroItemData = Array.isArray(response.sro_data) ? response.sro_data :
                                (response.sro_data?.data || []);

                    sroItemData.forEach(function(item) {
                        $('#sro_item').append(new Option(item.srO_ITEM_DESC, item.srO_ITEM_DESC));
                    });

                    // Edit mode: select saved SRO Item
                    if (dbSroItem) {
                        $('#sro_item').val(dbSroItem).trigger('change');
                    }
                }
            });
        });

        // SRO Item → Hidden ID
        $('#sro_item').on('change', function() {
            let selectedDesc = $(this).val();
            let selectedItem = sroItemData.find(item => item.srO_ITEM_DESC === selectedDesc);
            $('#sro_item_id').val(selectedItem ? selectedItem.srO_ITEM_ID : '');
        });

        // =========================
        // Trigger Edit Mode Values
        // =========================
        toggleRetailPrice();                 // pehle field dikhao/chhupao (edit page load par)

        if (dbSaleType) {
            loadSaleTypeData(dbSaleType);
        }
    }

    // ===============================
    // ACCESSORIES builder (edit mode)
    // ===============================
    const allProducts       = @json($allProducts);
    const selectedAccessories = @json($selectedAccessories); // {product_id: qty, ...}

    function buildProductOptions(selectedId) {
        let opts = '<option value="">-- Select Product --</option>';
        allProducts.forEach(function(p) {
            opts += `<option value="${p.id}" ${p.id == selectedId ? 'selected' : ''}>${p.name}</option>`;
        });
        return opts;
    }

    function addAccessoryRow(selectedId, qty) {
        const row = `
            <tr class="accessory-row">
                <td>
                    <select class="form-control form-control-sm accessory-product-select">
                        ${buildProductOptions(selectedId)}
                    </select>
                </td>
                <td>
                    <input type="number" class="form-control form-control-sm accessory-qty"
                           min="1" step="0.01" value="${qty || 1}">
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-danger remove-accessory">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>`;
        $('#accessoryRows').append(row);
        $('#accessoryRows .accessory-product-select').last().select2({ width: '100%' });
    }

    // Pre-populate existing accessories
    Object.entries(selectedAccessories).forEach(function([pid, qty]) {
        addAccessoryRow(pid, qty);
    });

    $('#addAccessoryRow').on('click', function() {
        addAccessoryRow(null, 1);
    });

    $(document).on('click', '.remove-accessory', function() {
        $(this).closest('tr').remove();
    });

    // Before form submit: build hidden inputs from rows
    $('form.accountForm').on('submit', function() {
        $(this).find('input[name^="accessories"]').remove();
        $('#accessoryRows tr.accessory-row').each(function() {
            const pid = $(this).find('.accessory-product-select').val();
            const qty = $(this).find('.accessory-qty').val();
            if (pid) {
                $('<input>').attr({
                    type: 'hidden',
                    name: `accessories[${pid}]`,
                    value: qty || 1
                }).appendTo('form.accountForm');
            }
        });
    });

});

</script>
@endpush