<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="{{ assetImage(readconfig('site_logo')) }}" type="image/x-icon">
    <title>
        {{ $pageTitle ?? 'Digital Invoicing System' }} | {{ readConfig('site_name') }}
    </title>
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Poppins -->
    <link rel="stylesheet"
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <style>
        :root {
            --primary: #000;
            --primary-dark: #0052a3;
            --secondary: #10b981;
            --accent: #059669;
            --dark: #1f2937;
            --light-gray: #f9fafb;
            --text-gray: #6b7280;
            --border-gray: #e5e7eb;
        }

        body {
            font-family: 'Poppins', BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            color: var(--dark);
            background: white;
        }

        /* NAVBAR STYLES */
        .navbar {
            padding: 0.75rem 0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            position: sticky;
            top: 0;
            z-index: 100;
            background: transparent;
        }

        .navbar-brand {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary) !important;
        }

        .btn-login {
            background: var(--primary);
            color: white;
            padding: 0.6rem 1.4rem;
            border-radius: 6px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        /* HERO SECTION - Dashboard Theme Version */
        .hero {
            background: #fd7e14;
            color: #ffffff;
            padding: 6rem 0;
            min-height: 650px;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        /* Dark overlay for depth */
        .hero::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(to right, rgba(0,0,0,0.35), rgba(0,0,0,0.1));
        }

        /* Soft light effect */
        .hero::before {
            content: "";
            position: absolute;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(255,255,255,0.15), transparent 70%);
            top: -150px;
            right: -150px;
        }

        /* Content above overlays */
        .hero-content {
            position: relative;
            z-index: 2;
        }

        /* Heading */
        .hero h1 {
            font-size: 3.5rem;
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 1.5rem;
            text-shadow: 0 4px 10px rgba(0,0,0,0.3);
        }

        /* Paragraph */
        .hero p {
            font-size: 1.25rem;
            margin-bottom: 2rem;
            opacity: 0.95;
            max-width: 600px;
        }

        /* ===== Buttons ===== */

        /* Primary Button (Black - Strong Contrast) */
        .btn-primary-custom {
            background: #000;
            color: #fff;
            padding: 1rem 2.5rem;
            border: none;
            border-radius: 6px;
            font-size: 1.1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            margin-right: 1rem;
        }

        .btn-primary-custom:hover,
        .btn-login:hover {
            background: #fd7e14;   /* match pricing theme */
            color: #fff;
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(255,102,0,0.4);
        }

        /* Secondary Button (White Contrast) */
        .btn-secondary-custom {
            background: #fff;
            color: #fd6802;
            padding: 1rem 2.5rem;
            border: none;
            border-radius: 6px;
            font-size: 1.1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-secondary-custom:hover {
            background: #f2f2f2;
            transform: translateY(-4px);
        }

        /* ===== Image Styling ===== */

        .hero-image-box {
            position: relative;
            z-index: 2;
            animation: slideUp 0.8s ease-out;
        }

        .hero-image-box img {
            width: 100%;
            max-height: 500px;
            object-fit: cover;
            border-radius: 16px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
        }

        /* Animation */
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* FEATURES SECTION */
        .features {
            padding: 5rem 0;
            background: var(--light-gray);
        }

        .section-title {
            font-size: 2.5rem;
            text-align: center;
            margin-bottom: 3rem;
            color: var(--dark);
            font-weight: 700;
        }

        .feature-card {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            border: 1px solid var(--border-gray);
            height: 100%;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
            border-color: var(--secondary);
            background: linear-gradient(135deg, rgba(0, 102, 204, 0.05) 0%, rgba(16, 185, 129, 0.05) 100%);
        }

        .feature-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .feature-card h3 {
            font-size: 1.3rem;
            margin-bottom: 0.75rem;
            color: var(--dark);
        }

        .feature-card p {
            color: var(--text-gray);
            font-size: 0.95rem;
            line-height: 1.7;
        }

        /* SHOWCASE SECTION - 3 IMAGES */
        .showcase {
            padding: 5rem 0;
            background: white;
        }

        .showcase-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 3rem;
        }

        .showcase-card {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            background: white;
        }

        .showcase-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
        }

        .showcase-image {
            width: 100%;
            height: 250px;
            object-fit: cover;
        }

        .showcase-content {
            padding: 1.5rem;
        }

        .showcase-content h4 {
            font-size: 1.2rem;
            margin-bottom: 0.75rem;
            color: var(--dark);
            font-weight: 600;
        }

        .showcase-content p {
            color: var(--text-gray);
            font-size: 0.95rem;
            line-height: 1.6;
        }

        /* BENEFITS SECTION */
        .benefits {
            padding: 5rem 0;
            background: var(--light-gray);
        }

        .benefits-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin-top: 3rem;
        }

        .benefit-item {
            display: flex;
            gap: 1.5rem;
            padding: 1.5rem;
            background: linear-gradient(135deg, rgba(0, 102, 204, 0.08) 0%, rgba(16, 185, 129, 0.08) 100%);
            border-radius: 10px;
            border-left: 4px solid var(--secondary);
        }

        .benefit-icon {
            font-size: 2rem;
            flex-shrink: 0;
        }

        .benefit-item h4 {
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
            color: var(--dark);
        }

        .benefit-item p {
            color: var(--text-gray);
            font-size: 0.9rem;
        }

        /* ABOUT SECTION */
        .about {
            padding: 5rem 0;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }

        .about-image {
            max-width: 100%;
            height: auto;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
        }

        .about-content h2 {
            font-size: 2.2rem;
            margin-bottom: 1.5rem;
            color: var(--dark);
        }

        .about-content p {
            color: var(--text-gray);
            font-size: 1.05rem;
            margin-bottom: 1.5rem;
            line-height: 1.8;
        }

        .about-list {
            list-style: none;
            margin-top: 2rem;
        }

        .about-list li {
            margin-bottom: 1rem;
            color: var(--text-gray);
        }

        .about-list strong {
            color: var(--dark);
        }

       /* FOOTER */
        .footer {
            background: var(--dark);
            color: white;
            padding: 1rem 0;
        }

        .footer-bottom {
            color: #9ca3af;
            font-size: 0.9rem;
        }

        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .footer-links {
            display: flex;
            gap: 15px;
        }

        .footer-links a {
            color: #9ca3af;
            text-decoration: none;
            transition: 0.3s;
        }

        .footer-links a:hover {
            color: white;
        }

        /* WHATSAPP BUTTON */
        .whatsapp-float {
            position: fixed;
            width: 60px;
            height: 60px;
            bottom: 30px;
            right: 30px;
            background-color: #25D366;
            color: #FFF;
            border-radius: 50%;
            text-align: center;
            font-size: 30px;
            box-shadow: 2px 2px 8px rgba(0,0,0,0.3);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: 0.3s ease-in-out;
            text-decoration: none;
        }

        .whatsapp-float:hover {
            background-color: #1ebe5d;
            transform: scale(1.1);
            color: #fff;
        }

        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2.2rem;
            }

            .hero p {
                font-size: 1rem;
            }

            .section-title {
                font-size: 1.8rem;
            }

            .cta h2 {
                font-size: 1.8rem;
            }

            .btn-primary-custom,
            .btn-secondary-custom {
                display: block;
                width: 100%;
                margin-right: 0;
            }
        }
    </style>
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