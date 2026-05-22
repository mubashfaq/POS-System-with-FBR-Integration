@include('frontend.layouts.header', [
    'pageTitle' => 'Bulk Filer Status Check',
    'metaDescription' => 'Check FBR filer/non-filer status in bulk by uploading an Excel file with CNICs.',
    'metaKeywords' => 'filer status check, FBR, CNIC, bulk check, Pakistan tax'
])

<style>
    .drop-zone {
        border: 2px dashed #1a56db;
        border-radius: 12px;
        background: #f8faff;
        padding: 2rem;
        text-align: center;
        transition: all 0.2s ease;
    }
    .drop-zone.drag-over {
        background: #eef2ff;
        border-color: #1d4ed8;
    }
    .drop-zone.file-ready {
        background: #f0fdf4;
        border-color: #16a34a;
    }
    .browse-label {
        display: inline-block;
        padding: 6px 18px;
        border: 1px solid #1a56db;
        border-radius: 6px;
        color: #1a56db;
        cursor: pointer;
        font-size: 14px;
        transition: all 0.15s;
        background: white;
    }
    .browse-label:hover {
        background: #1a56db;
        color: white;
    }
    .file-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: #dcfce7;
        color: #15803d;
        border-radius: 20px;
        padding: 6px 14px;
        font-size: 13px;
        font-weight: 500;
        margin-top: 10px;
    }
</style>

<div class="container py-4">

    {{-- Page Header --}}
    <div class="mb-4">
        <h4 class="mb-0 fw-bold">Bulk Filer Status Check</h4>
        <p class="text-muted mb-0 small">
            Upload an Excel file containing CNICs to check FBR registration status in bulk
        </p>
    </div>

    {{-- Alert area --}}
    <div id="alertArea"></div>

    {{-- Upload Card --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-4">

            <div class="row align-items-start">

                {{-- Left: drop zone --}}
                <div class="col-md-8">
                    <h6 class="fw-semibold mb-1">Upload Excel File</h6>
                    <p class="text-muted small mb-3">
                        Formats: <strong>.xlsx, .xls, .csv</strong> &nbsp;|&nbsp;
                        Max: <strong>10MB</strong> &nbsp;|&nbsp;
                        Must have a column header containing <strong>"cnic"</strong>
                    </p>

                    <div class="drop-zone" id="dropZone">
                        <i class="fas fa-file-excel fa-2x mb-2" style="color:#1a56db;"></i>
                        <p class="mb-1 fw-medium text-dark" id="dropLabel">
                            Drag &amp; drop your Excel file here
                        </p>
                        <p class="text-muted small mb-3">Supports .xlsx, .xls, .csv — Max 10MB</p>

                        <label for="excelFile" class="browse-label">
                            <i class="fas fa-folder-open me-1"></i> Browse File
                        </label>

                        <div id="fileSelectedInfo" style="display:none;">
                            <div class="file-badge">
                                <i class="fas fa-check-circle"></i>
                                <span id="selectedFileName"></span>
                            </div>
                        </div>
                    </div>

                    <input type="file"
                           id="excelFile"
                           name="excel_file"
                           accept=".xlsx,.xls,.csv"
                           style="position:absolute; width:1px; height:1px; opacity:0; pointer-events:none;">
                </div>

                {{-- Right: format hint --}}
                <div class="col-md-4 mt-3 mt-md-0">
                    <div class="bg-light rounded-3 p-3">
                        <h6 class="fw-semibold mb-2 small">
                            <i class="fas fa-info-circle text-primary me-1"></i>
                            Expected Excel Format
                        </h6>
                        <table class="table table-sm table-bordered mb-0 small">
                            <thead class="table-dark">
                                <tr>
                                    <th>Name</th>
                                    <th>CNIC</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td>Ali Ahmed</td><td>3520112345671</td></tr>
                                <tr><td>Sara Khan</td><td>4210198765432</td></tr>
                            </tbody>
                        </table>
                        <hr class="my-2">
                        <small>
                            <i class="fas fa-download me-1 text-primary"></i>
                            <a href="{{ asset('samples/bulk_filer_sample.xlsx') }}" class="text-primary">
                                Download Sample File
                            </a>
                        </small>
                    </div>
                </div>

            </div>{{-- end row --}}

            {{-- Action Buttons --}}
            <div class="mt-3 d-flex gap-2 flex-wrap">
                <button id="checkBtn" class="btn-login" onclick="checkBulk()" disabled>
                    <i class="fas fa-search me-2"></i>Check Status
                </button>
                <button id="downloadBtn" class="btn btn-outline-success" onclick="downloadResult()" style="display:none;">
                    <i class="fas fa-download me-2"></i>Download
                </button>
                <button class="btn btn-outline-secondary" onclick="resetForm()">
                    <i class="fas fa-redo me-2"></i>Reset
                </button>
            </div>

        </div>
    </div>

    {{-- Progress --}}
    <div id="progressSection" style="display:none;" class="mb-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-3">
                <div class="d-flex align-items-center gap-3 mb-2">
                    <div class="spinner-border spinner-border-sm text-primary"></div>
                    <span class="fw-medium">Processing your file, please wait...</span>
                </div>
                <div class="progress" style="height:6px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary w-100"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div id="summarySection" style="display:none;" class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center p-3">
                <div class="fs-3 fw-bold text-dark" id="totalCount">0</div>
                <div class="small text-muted">Total Checked</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center p-3">
                <div class="fs-3 fw-bold text-success" id="regCount">0</div>
                <div class="small text-muted">Registered</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center p-3">
                <div class="fs-3 fw-bold text-danger" id="notRegCount">0</div>
                <div class="small text-muted">Not Registered</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center p-3">
                <div class="fs-3 fw-bold text-warning" id="skippedCount">0</div>
                <div class="small text-muted">Skipped / Invalid</div>
            </div>
        </div>
    </div>

    {{-- Results Table --}}
    <div id="resultsSection" style="display:none;">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <h6 class="fw-bold mb-0">Results</h6>
                <input type="text"
                       id="tableSearch"
                       class="form-control form-control-sm w-auto"
                       placeholder="Search name or CNIC..."
                       oninput="filterTable()">
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">#</th>
                                <th>Name</th>
                                <th>CNIC</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="resultsBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Hidden download form --}}
    <form id="downloadForm"
          action="{{ route('filer.bulk.download') }}"
          method="POST"
          enctype="multipart/form-data"
          style="display:none;">
        @csrf
        <input type="file" id="hiddenFile" name="excel_file">
    </form>

</div>

<script>
(function () {
    'use strict';

    let uploadedFile = null;

    document.addEventListener('DOMContentLoaded', function () {
        const input    = document.getElementById('excelFile');
        const dropZone = document.getElementById('dropZone');

        input.addEventListener('change', function (e) {
            if (e.target.files.length > 0) handleFile(e.target.files[0]);
        });

        dropZone.addEventListener('dragover', function (e) {
            e.preventDefault();
            e.stopPropagation();
            dropZone.classList.add('drag-over');
        });

        dropZone.addEventListener('dragleave', function (e) {
            e.preventDefault();
            e.stopPropagation();
            dropZone.classList.remove('drag-over');
        });

        dropZone.addEventListener('drop', function (e) {
            e.preventDefault();
            e.stopPropagation();
            dropZone.classList.remove('drag-over');
            const file = e.dataTransfer.files[0];
            if (file) handleFile(file);
        });
    });

    function handleFile(file) {
        const allowed = ['xlsx', 'xls', 'csv'];
        const ext     = file.name.split('.').pop().toLowerCase();

        if (!allowed.includes(ext)) {
            showAlert('danger', 'Invalid file type. Please upload .xlsx, .xls, or .csv');
            return;
        }
        if (file.size > 10 * 1024 * 1024) {
            showAlert('danger', 'File too large. Maximum size is 10MB.');
            return;
        }

        uploadedFile = file;

        document.getElementById('dropLabel').textContent          = 'File ready:';
        document.getElementById('selectedFileName').textContent   = file.name;
        document.getElementById('fileSelectedInfo').style.display = 'block';
        document.getElementById('dropZone').classList.add('file-ready');
        document.getElementById('checkBtn').disabled              = false;
        document.getElementById('downloadBtn').style.display      = 'none';
        document.getElementById('summarySection').style.display   = 'none';
        document.getElementById('resultsSection').style.display   = 'none';
    }

    window.checkBulk = async function () {
        if (!uploadedFile) {
            showAlert('warning', 'Please select a file first.');
            return;
        }

        const btn = document.getElementById('checkBtn');
        btn.disabled  = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';

        document.getElementById('progressSection').style.display = 'block';
        document.getElementById('summarySection').style.display  = 'none';
        document.getElementById('resultsSection').style.display  = 'none';
        document.getElementById('downloadBtn').style.display     = 'none';

        const formData = new FormData();
        formData.append('excel_file', uploadedFile);
        formData.append('_token', '{{ csrf_token() }}');

        try {
            const response = await fetch('{{ route("filer.bulk.check") }}', {
                method: 'POST',
                body:   formData,
            });

            const result = await response.json();

            document.getElementById('progressSection').style.display = 'none';
            btn.innerHTML = '<i class="fas fa-search me-2"></i>Check Status';
            btn.disabled  = false;

            if (!result.success) {
                showAlert('danger', result.message || 'Something went wrong.');
                return;
            }

            document.getElementById('totalCount').textContent   = result.total;
            document.getElementById('regCount').textContent     = result.registered;
            document.getElementById('notRegCount').textContent  = result.not_reg;
            document.getElementById('skippedCount').textContent = result.skipped;
            document.getElementById('summarySection').style.display = 'flex';

            const tbody = document.getElementById('resultsBody');
            tbody.innerHTML = '';
            result.data.forEach(function (row, i) {
                const badgeClass = row.registered === true
                    ? 'bg-success'
                    : row.registered === false
                        ? 'bg-danger'
                        : 'bg-warning text-dark';

                tbody.insertAdjacentHTML('beforeend',
                    '<tr>' +
                        '<td class="ps-3 text-muted small">' + (i + 1) + '</td>' +
                        '<td>' + escHtml(row.name) + '</td>' +
                        '<td><code>' + escHtml(row.cnic) + '</code></td>' +
                        '<td><span class="badge ' + badgeClass + ' px-2 py-1">' + escHtml(row.status) + '</span></td>' +
                    '</tr>'
                );
            });

            document.getElementById('resultsSection').style.display = 'block';
            document.getElementById('downloadBtn').style.display    = 'inline-block';

            try {
                const dt = new DataTransfer();
                dt.items.add(uploadedFile);
                document.getElementById('hiddenFile').files = dt.files;
            } catch (e) {}

        } catch (err) {
            document.getElementById('progressSection').style.display = 'none';
            btn.innerHTML = '<i class="fas fa-search me-2"></i>Check Status';
            btn.disabled  = false;
            showAlert('danger', 'Network error. Please try again.');
        }
    };

    window.downloadResult = function () {
        document.getElementById('downloadForm').submit();
    };

    window.filterTable = function () {
        const query = document.getElementById('tableSearch').value.toLowerCase();
        document.querySelectorAll('#resultsBody tr').forEach(function (tr) {
            tr.style.display = tr.textContent.toLowerCase().includes(query) ? '' : 'none';
        });
    };

    window.resetForm = function () {
        uploadedFile = null;
        document.getElementById('excelFile').value                = '';
        document.getElementById('dropLabel').textContent          = 'Drag & drop your Excel file here';
        document.getElementById('fileSelectedInfo').style.display = 'none';
        document.getElementById('dropZone').classList.remove('file-ready', 'drag-over');
        document.getElementById('checkBtn').disabled              = true;
        document.getElementById('checkBtn').innerHTML             = '<i class="fas fa-search me-2"></i>Check Status';
        document.getElementById('downloadBtn').style.display      = 'none';
        document.getElementById('summarySection').style.display   = 'none';
        document.getElementById('resultsSection').style.display   = 'none';
        document.getElementById('progressSection').style.display  = 'none';
    };

    function showAlert(type, message) {
        const area = document.getElementById('alertArea');
        const div  = document.createElement('div');
        div.className = 'alert alert-' + type + ' alert-dismissible fade show';
        div.innerHTML = message + ' <button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        area.appendChild(div);
        setTimeout(function () { div.remove(); }, 5000);
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

})();
</script>

@include('frontend.layouts.footer')