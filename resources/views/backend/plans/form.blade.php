{{-- Name & Slug --}}
<div class="row g-3 mb-3">
    <div class="col-md-6">
        <label class="form-label fw-semibold">Plan Name <span class="text-danger">*</span></label>
        <input
            type="text"
            name="name"
            class="form-control @error('name') is-invalid @enderror"
            value="{{ old('name', $plan->name) }}"
            placeholder="e.g. Basic, Standard, Pro"
            required
        >
    </div>

    <div class="col-md-6">
        <label class="form-label fw-semibold">
            Slug <span class="text-danger">*</span>
            <span class="text-muted fw-normal small ms-1">(URL-safe, unique)</span>
        </label>
        <input
            type="text"
            name="slug"
            id="slug"
            class="form-control font-monospace @error('slug') is-invalid @enderror"
            value="{{ old('slug', $plan->slug) }}"
            placeholder="e.g. basic"
            required
        >
    </div>
</div>

{{-- Price & Sort Order --}}
<div class="row g-3 mb-3">
    <div class="col-md-6">
        <label class="form-label fw-semibold">Price ({{currency()->symbol??''}}/month) <span class="text-danger">*</span></label>
        <div class="input-group">
            <input
                type="number"
                name="price"
                class="form-control @error('price') is-invalid @enderror"
                value="{{ old('price', $plan->price ?? 0) }}"
                min="0"
                step="0.01"
                placeholder="0.00"
                required
            >
        </div>
    </div>

    <div class="col-md-6">
        <label class="form-label fw-semibold">Sort Order <span class="text-danger">*</span></label>
        <input
            type="number"
            name="sort_order"
            class="form-control @error('sort_order') is-invalid @enderror"
            value="{{ old('sort_order', $plan->sort_order ?? $sortOrder ?? 1) }}"
            min="1"
            required
        >
    </div>

    
</div>


<div class="row g-3 mb-4">
    <div class="col-md-6">
        <label class="form-label fw-semibold">
            <i class="bi bi-receipt text-primary me-1"></i>
            POS Invoice Limit <span class="text-danger">*</span>
        </label>
        <input type="number" name="max_pos_invoices"
            class="form-control @error('max_pos_invoices') is-invalid @enderror"
            value="{{ old('max_pos_invoices', $plan->max_pos_invoices ?? '') }}"
            min="-1" placeholder="e.g. 10" required>
    </div>
 
    {{-- Digital Invoice Limit --}}
    <div class="col-md-6">
        <label class="form-label fw-semibold">
            <i class="bi bi-phone text-success me-1"></i>
            Digital Invoice Limit <span class="text-danger">*</span>
        </label>
        <input type="number" name="max_digital_invoices"
            class="form-control @error('max_digital_invoices') is-invalid @enderror"
            value="{{ old('max_digital_invoices', $plan->max_digital_invoices ?? '') }}"
            min="-1" placeholder="e.g. 5" required>
    </div>
</div>
<div class="row g-3 mt-2 mb-2">

    <div class="col-md-6">
        <label class="form-label fw-semibold">
            <i class="bi bi-people text-info me-1"></i>
            Max Users <span class="text-danger">*</span>
        </label>
        <input
            type="number"
            name="max_users"
            class="form-control @error('max_users') is-invalid @enderror"
            value="{{ old('max_users', $plan->max_users ?? '') }}"
            min="-1"
            placeholder="e.g. 5"
            required
        >

    {{-- <div class="col-md-4">
        <label class="form-label fw-semibold">
            <i class="bi bi-box text-warning me-1"></i>
            Max Products
        </label>
        <input
            type="number"
            name="max_products"
            class="form-control @error('max_products') is-invalid @enderror"
            value="{{ old('max_products', $plan->max_products ?? '') }}"
            min="-1"
            placeholder="e.g. 200"
            required
        >
        @error('max_products')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="form-text">Total products per business (not monthly).</div>
    </div> --}}
</div> 




    <!-- ✅ Features Column -->
    <div class="col-md-6">
        <label class="form-label fw-semibold">Features</label>

        <div class="row">
            @foreach($features as $key => $label)
                <div class="col-6 mb-2"> <!-- 2 per row inside same column -->
                    <div class="form-check">
                        <input
                            class="form-check-input feature-checkbox"
                            type="checkbox"
                            name="features[]"
                            id="feature_{{ $key }}"
                            value="{{ $key }}"
                            @if(old('features'))
                                {{ in_array($key, old('features', [])) ? 'checked' : '' }}
                            @else
                                {{ in_array($key, $plan->features ?? []) ? 'checked' : '' }}
                            @endif
                        >
                        <label class="form-check-label" for="feature_{{ $key }}">
                            {{ $label }}
                        </label>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- ✅ Status Column -->
    <div class="col-md-6">
        <label class="form-label fw-semibold">Status</label>

        <div class="form-check form-switch mt-2">
            <input
                class="form-check-input"
                type="checkbox"
                name="is_active"
                id="is_active"
                value="1"
                {{ old('is_active', $plan->is_active ?? true) ? 'checked' : '' }}
            >
            <label class="form-check-label" for="is_active">
                Active (visible to businesses)
            </label>
        </div>
    </div>

</div>

 
<div class="row g-3 mb-2">
    {{-- POS Invoice Limit --}}
    
</div>
 

{{-- Auto-generate slug from name --}}
@push('scripts')
<script>
    const nameInput = document.querySelector('input[name="name"]');
    const slugInput = document.getElementById('slug');
    let slugManuallyEdited = slugInput.value.length > 0;

    nameInput.addEventListener('input', function () {
        if (slugManuallyEdited) return;
        slugInput.value = this.value
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9\s-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-');
    });

    slugInput.addEventListener('input', function () {
        slugManuallyEdited = true;
    });

</script>
@endpush