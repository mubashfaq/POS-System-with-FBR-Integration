<div class="row">
    <div class="mb-3 col-lg-4">
        <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
        <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror"
            value="{{ old('name', $template->name ?? '') }}" required>
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="mb-3 col-lg-4">
        <label for="key" class="form-label">Key <span class="text-danger">*</span> <small
                class="text-muted">(Unique identifier, e.g. modern)</small></label>
        <input type="text" name="key" id="key" class="form-control @error('key') is-invalid @enderror"
            value="{{ old('key', $template->key ?? '') }}" required>
        @error('key')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="mb-3 col-lg-4">
        <label for="view" class="form-label">View Path <span class="text-danger">*</span> <small
                class="text-muted">(Laravel Blade view name)</small></label>
        <input type="text" name="view" id="view" class="form-control @error('view') is-invalid @enderror"
            value="{{ old('view', $template->view ?? '') }}" placeholder="backend.orders.invoice-templates.template_02"
            required>
        @error('view')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="mb-3 col-lg-4">
        <label for="sort_order" class="form-label">Sort Order</label>
        <input type="number" name="sort_order" id="sort_order" min="0"
            class="form-control @error('sort_order') is-invalid @enderror"
            value="{{ old('sort_order', $template->sort_order ?? 0) }}">
        @error('sort_order')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="mb-3 col-lg-4">
        <label class="form-label d-block">Default</label>
        <div class="form-check">
            <input type="hidden" name="is_default" value="0">
            <input type="checkbox" name="is_default" id="is_default" value="1" class="form-check-input"
                {{ old('is_default', $template->is_default ?? 0) ? 'checked' : '' }}>
            <label for="is_default" class="form-check-label">Fallback if business hasn't chosen</label>
        </div>
    </div>

    <div class="mb-3 col-lg-4">
        <label class="form-label d-block">Status</label>
        <div class="form-check">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" id="is_active" value="1" class="form-check-input"
                {{ old('is_active', $template->is_active ?? 1) ? 'checked' : '' }}>
            <label for="is_active" class="form-check-label">Active (visible in picker)</label>
        </div>
    </div>

    <div class="mb-3 col-12">
        <label class="form-label">Thumbnail</label>
        <div class="row g-3 align-items-center">
            @if (!empty($template->image))
                <div class="col-md-3">
                    <img src="{{ asset($template->image) }}" alt="Current thumbnail" height="80"
                        class="img-thumbnail">
                    <input type="hidden" name="image" value="{{ $template->image }}">
                </div>
            @endif

            <div class="{{ !empty($template->image) ? 'col-md-9' : 'col-12' }}">
                <div class="image-upload-container" id="imageUploadContainer">
                    <input type="file" class="form-control @error('image_file') is-invalid @enderror"
                        name="image_file" id="thumbnailInput" accept="image/*" style="display: none;">

                    <div class="thumb-preview" id="thumbPreviewContainer">
                        <img src="{{ asset('backend/assets/images/blank.png') }}" alt="Thumbnail Preview"
                            class="img-thumbnail d-none" id="thumbnailPreview">
                        <div class="upload-text">
                            <i class="fas fa-plus-circle"></i>
                            <span>Upload Image</span>
                        </div>
                    </div>

                    @error('image_file')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $('#thumbPreviewContainer').on('click', function() {
        $('#thumbnailInput').trigger('click');
    });

    $('#thumbnailInput').on('change', function() {
        const file = this.files[0];

        if (file) {
            const reader = new FileReader();

            reader.onload = function(e) {
                $('#thumbnailPreview')
                    .attr('src', e.target.result)
                    .removeClass('d-none');

                $('#thumbPreviewContainer .upload-text').hide();
            };

            reader.readAsDataURL(file);
        }
    });
</script>
