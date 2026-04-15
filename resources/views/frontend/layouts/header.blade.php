<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="{{ assetImage(readconfig('site_logo')) }}" type="image/x-icon">
    {{-- SEO Meta Tags --}}
    <title>
        {{ $pageTitle ?? 'Digital Invoicing System' }} | {{ readConfig('site_name') }}
    </title>

    <meta name="description" content="{{ $metaDescription ?? 'Manage invoices, billing, and business transactions efficiently with our digital invoicing system.' }}">
    
    <meta name="keywords" content="{{ $metaKeywords ?? 'invoice system, billing software, digital invoicing, POS, accounting software' }}">
    
    <meta name="author" content="{{ readConfig('site_name') }}">

    {{-- Canonical URL --}}
    <link rel="canonical" href="{{ url()->current() }}">

    {{-- Open Graph (for social sharing) --}}
    <meta property="og:title" content="{{ $pageTitle ?? 'Digital Invoicing System' }}">
    <meta property="og:description" content="{{ $metaDescription ?? 'Smart invoicing and billing solution for businesses.' }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:type" content="website">
    <meta property="og:image" content="{{ assetImage(readconfig('site_logo')) }}">

    {{-- Twitter Card --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $pageTitle ?? 'Digital Invoicing System' }}">
    <meta name="twitter:description" content="{{ $metaDescription ?? 'Smart invoicing and billing solution.' }}">
    <meta name="twitter:image" content="{{ assetImage(readconfig('site_logo')) }}">
    
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- Poppins -->
    <link rel="stylesheet"
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="{{ asset('assets/css/home.css') }}">
</head>
<body>
    <!-- NAVBAR -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white">
        <div class="container-fluid">
            <a class="navbar-brand" href="{{ route('frontend.home') }}">
                <img src="{{ assetImage(readconfig('site_logo')) }}" alt="{{ readConfig('site_name') }}" height="40" class="d-inline-block align-text-top me-2">
                {{ readConfig('site_name') }}
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <div class="ms-auto">
                    <a href="{{ route('login') }}" class="btn btn-login">Login</a>
                </div>
            </div>
        </div>
    </nav>