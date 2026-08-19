$(function () {
    // Initialize SummerNote
    $(".summerNote").summernote({
        height: 200,
    });

    //Initialize Select2 Elements
    $(".select2").select2();
});

function previewThumbnail(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function (e) {
            var thumbnailPreview =
                input.parentNode.querySelector(".thumbnail-preview");
            if (thumbnailPreview) {
                thumbnailPreview.src = e.target.result;
            }
        };

        reader.readAsDataURL(input.files[0]);
    }
}

function showToast(message, type = 'success') {

    const bgClass = {
        success: 'bg-success',
        error: 'bg-danger',
        warning: 'bg-warning'
    }[type] || 'bg-success';

    const toastHtml = `
        <div style="position: fixed; top: 1rem; right: 1rem; z-index:9999;"
             class="toast align-items-center ${bgClass} border-0 text-white"
             role="alert"
             data-delay="2000">
            <div class="toast-body">
                ${message}
            </div>
        </div>`;

    const $toast = $(toastHtml);

    $('body').append($toast);

    const toast = new bootstrap.Toast($toast[0]);

    toast.show();

    $toast.on('hidden.bs.toast', function () {
        $(this).remove();
    });
}
