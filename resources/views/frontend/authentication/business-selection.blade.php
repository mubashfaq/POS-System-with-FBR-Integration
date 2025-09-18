<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Business</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        /* Header Styling */
        .card-header {
            background-color: #000 !important; /* black background */
            color: #fff !important;           /* white text */
        }

        /* Create New Business Button */
        .btn-create {
            background-color: #ff6600; /* orange */
            color: #fff;
            border: none;
        }

        .btn-create:hover {
            background-color: #e65c00; /* darker orange on hover */
            color: #fff;
        }
        .btn-create-select {
            border: 1px solid #e65c00;
            color: #e65c00;
            background: transparent;
        }
        .btn-create-select:hover {
            background-color: #e65c00; /* darker orange on hover */
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-lg">
                    <div class="card-header text-center">
                        <h3>Select or Create Business</h3>
                    </div>
                    <div class="card-body">

                        @if(session('error'))
                            <div class="alert alert-danger">{{ session('error') }}</div>
                        @endif

                        <!-- Existing Businesses -->
                        @if($businesses->count() > 0)
                            <div class="mb-4">
                                <h5 class="mb-3">Your Businesses</h5>
                                <div class="row">
                                    @foreach($businesses as $business)
                                        <div class="col-md-6 mb-3">
                                            <div class="card h-100 shadow-sm">
                                                <div class="card-body">
                                                    <h5 class="card-title">{{ $business->business_name }}</h5>
                                                    <p class="card-text mb-1"><strong>Industry:</strong> {{ $business->industry }}</p>
                                                    <p class="card-text mb-1"><strong>City:</strong> {{ $business->city }}</p>
                                                    <p class="card-text"><strong>Country:</strong> {{ $business->country }}</p>
                                                </div>
                                                <div class="card-footer bg-white border-0">
                                                    <form action="{{ route('business.select') }}" method="POST">
                                                        @csrf
                                                        <input type="hidden" name="business_id" value="{{ $business->id }}">
                                                        <button type="submit" class="btn btn-create-select w-100">
                                                            Select
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            <hr>
                        @endif

                        <!-- Button to show create form -->
                        <div class="text-center mb-3">
                            <button id="toggleCreateForm" class="btn btn-create">
                                + Create New Business
                            </button>
                        </div>

                        <!-- Hidden Create Business Form -->
                        <div id="createForm" style="display: none;">
                            <h5>Create New Business</h5>
                            <form action="{{ route('business.create') }}" method="POST">
                                @csrf
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="business_name" class="form-label">Business Name *</label>
                                        <input type="text" class="form-control" id="business_name" name="business_name" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="industry" class="form-label">Industry *</label>
                                        <input type="text" class="form-control" id="industry" name="industry" required>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="ntn" class="form-label">NTN (Optional)</label>
                                        <input type="text" class="form-control" id="ntn" name="ntn">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="country" class="form-label">Country *</label>
                                        <input type="text" class="form-control" id="country" name="country" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="city" class="form-label">City *</label>
                                    <input type="text" class="form-control" id="city" name="city" required>
                                </div>
                                <button type="submit" class="btn btn-create w-100">Create Business</button>
                            </form>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

<script>
    const toggleBtn = document.getElementById("toggleCreateForm");
    const form = document.getElementById("createForm");

    toggleBtn.addEventListener("click", function () {
        if (form.style.display === "none" || form.style.display === "") {
            form.style.display = "block";
            toggleBtn.textContent = "✖ Close Form"; 
            toggleBtn.classList.remove("btn-create-select");
            toggleBtn.classList.add("btn-danger"); 
        } else {
            form.style.display = "none";
            toggleBtn.textContent = "+ Create New Business"; 
            toggleBtn.classList.remove("btn-danger");
            toggleBtn.classList.add("btn-create"); 
        }
    });
</script>

</body>
</html>
