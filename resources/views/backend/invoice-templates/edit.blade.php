@extends('backend.master')
@section('title', 'Edit Invoice Template')
@section('content')
    <div class="card">
        <div class="card-body">
            <form action="{{ route('backend.admin.invoice-templates.update', $template->id) }}" method="POST"
                enctype="multipart/form-data">
                @csrf @method('PUT')
                @include('backend.invoice-templates._form')
                <button class="btn action-button mt-3">Update</button>
            </form>
        </div>
    </div>
@endsection
