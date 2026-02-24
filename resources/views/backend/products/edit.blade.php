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
                            <span class="text-danger">*</span>
                        </label>
                        <select class="form-control select2" style="width: 100%;" name="brand_id" required>
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
                            <span class="text-danger">*</span>
                        </label>
                        <select class="form-control select2" style="width: 100%;" name="category_id" required>
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

                    <div class="mb-3 col-md-6">
                        <label for="salesTaxWithheldAtSource" class="form-label">
                           PCT Code
                            <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control " placeholder="Enter PCT Code"
                            name="PCTCode" value="{{ old('PCTCode', $product->PCTCode) }}" required>

                    </div>

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
                <div class="row">
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
$(function() {
    //Date picker
    $('#reservationdate').datetimepicker({
        format: 'YYYY-MM-DD'
    });
})

// $(document).ready(function() {
//     // Initialize Select2 Elements
//     $('.select2').select2();

//     let selectedUomFromDb = "{{ $product->uom ?? '' }}";

//     // On page load (EDIT mode)
//     if ($('#hsCode').val()) {
//         loadUoms($('#hsCode').val(), selectedUomFromDb);
//     }

//     // On HS Code change
//     $('#hsCode').on('change', function () {
//         loadUoms($(this).val(), null);
//     });

//     function loadUoms(selectedHsCode, selectedUom) {
//         $.ajax({
//             url: "{{ route('backend.admin.products.hsCode-desc', ':hs_code') }}"
//                 .replace(':hs_code', selectedHsCode),
//             type: 'GET',
//             dataType: 'json',
//             success: function (response) {

//                 // Description set
//                 $('#description').val(response.description);

//                 // Reset UOM dropdown
//                 $('#uom').empty().append('<option value="">Select Unit</option>');

//                 // Append UOMs
//                 $.each(response.uom, function (index, uom) {

//                     let isSelected = selectedUom === uom.description;

//                     let option = new Option(
//                         uom.description, // text
//                         uom.description, // value
//                         isSelected,
//                         isSelected
//                     );

//                     $('#uom').append(option);
//                 });

//                 // Important for Select2
//                 $('#uom').trigger('change');
//             },
//             error: function (xhr, status, error) {
//                 console.error('Error fetching units:', error);
//             }
//         });
//     }


//     let saleData = [];
//     let sroData = [];
//     let sroItemData = [];

//     // ===============================
//     //  Sale Type → Rate of Tax
//     // ===============================
//     $('#sale_type').on('change', function() {

//         let sale_type = $(this).val();

//         // Reset all dependents
//         $('#rate_of_tax').empty().append('<option value="">Select Sale Tax</option>');
//         $('#rate_of_tax_id').val('');

//         $('#sro_schedule').empty().append('<option value="">Select SRO Schedule</option>');
//         $('#sro_schedule_id').val('');

//         $('#sro_item').empty().append('<option value="">Select SRO Item</option>');
//         $('#sro_item_id').val('');

//         saleData = [];
//         sroData = [];
//         sroItemData = [];

//         if (!sale_type) return;

//         $.ajax({
//             url: "{{ route('backend.admin.products.sale-type', ':sale_type') }}"
//                 .replace(':sale_type', sale_type),
//             type: 'GET',
//             dataType: 'json',
//             success: function(response) {

//                 saleData = response.sale_data || [];

//                 saleData.forEach(function(item) {
//                     $('#rate_of_tax').append(
//                         new Option(item.ratE_DESC, item.ratE_DESC)
//                     );
//                 });
//             }
//         });
//     });

//     // ===============================
//     //  Rate of Tax → SRO Schedule
//     // ===============================
//     $('#rate_of_tax').on('change', function() {

//         let selectedDesc = $(this).val();

//         $('#sro_schedule').empty().append('<option value="">Select SRO Schedule</option>');
//         $('#sro_schedule_id').val('');

//         $('#sro_item').empty().append('<option value="">Select SRO Item</option>');
//         $('#sro_item_id').val('');

//         sroData = [];
//         sroItemData = [];

//         let selectedItem = saleData.find(
//             item => item.ratE_DESC === selectedDesc
//         );

//         if (!selectedItem) {
//             $('#rate_of_tax_id').val('');
//             return;
//         }

//         $('#rate_of_tax_id').val(selectedItem.ratE_ID);

//         $.ajax({
//             url: "{{ route('backend.admin.products.sro-schedule', ':rate_tax_id') }}"
//                 .replace(':rate_tax_id', selectedItem.ratE_ID),
//             type: 'GET',
//             dataType: 'json',
//             success: function(response) {

//                 sroData = response.sro_data || [];

//                 sroData.forEach(function(item) {
//                     $('#sro_schedule').append(
//                         new Option(item.srO_DESC, item.srO_DESC)
//                     );
//                 });
//             }
//         });
//     });

//     // ===============================
//     //  SRO Schedule → SRO Item
//     // ===============================
//     $('#sro_schedule').on('change', function() {

//         let selectedDesc = $(this).val();

//         $('#sro_item').empty().append('<option value="">Select SRO Item</option>');
//         $('#sro_item_id').val('');
//         sroItemData = [];

//         let selectedItem = sroData.find(
//             item => item.srO_DESC === selectedDesc
//         );

//         if (!selectedItem) {
//             $('#sro_schedule_id').val('');
//             return;
//         }

//         $('#sro_schedule_id').val(selectedItem.srO_ID);

//         $.ajax({
//             url: "{{ route('backend.admin.products.sro-Items-details', ':sro_schedule_id') }}"
//                 .replace(':sro_schedule_id', selectedItem.srO_ID),
//             type: 'GET',
//             dataType: 'json',
//             success: function(response) {
//                 console.log(response);
//                 sroItemData = Array.isArray(response.sro_data) ?
//                     response.sro_data :
//                     (response.sro_data?.data || []);

//                 $('#sro_item').empty().append('<option value="">Select SRO Item</option>');
//                 $('#sro_item_id').val('');

//                 sroItemData.forEach(function(item) {
//                     $('#sro_item').append(
//                         new Option(item.srO_ITEM_DESC, item.srO_ITEM_DESC)
//                     );
//                 });
//             }
//         });
//     });

//     // ===============================
//     // SRO Item → set hidden ID
//     // ===============================
//     $('#sro_item').on('change', function() {

//         let selectedDesc = $(this).val();

//         let selectedItem = sroItemData.find(
//             item => item.srO_ITEM_DESC === selectedDesc
//         );

//         if (selectedItem) {
//             $('#sro_item_id').val(selectedItem.srO_ITEM_ID);
//         } else {
//             $('#sro_item_id').val('');
//         }
//     });

// });

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
    if (dbSaleType) {
        loadSaleTypeData(dbSaleType);
    }
});

</script>
@endpush