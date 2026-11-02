@extends('backend.master')
@section('title', 'Create Invoice Template')
@section('content')
    <div class="card">
        <div class="card-body">
            <form action="{{ route('backend.admin.invoice-templates.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                @include('backend.invoice-templates._form')
                <button class="btn action-button mt-3">Create Template</button>
            </form>
        </div>
    </div>
@endsection
