<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="{{ assetImage(readconfig('site_logo')) }}" type="image/x-icon">
    <title>Digital Invoicing System | {{ readConfig('site_name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
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

        .btn-login:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 102, 204, 0.3);
            color: white;
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

        .btn-primary-custom:hover {
            background: #1a1a1a;
            transform: translateY(-4px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.4);
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
            text-align: center;
        }

        .footer-bottom {
            color: #9ca3af;
            font-size: 0.9rem;
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
            <a class="navbar-brand" href="#">
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

    <!-- HERO SECTION -->
    <section class="hero">
        <div class="container hero-content">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1>Empower Your Business with {{ readConfig('site_name') }}</h1>
                    <p>The all-in-one Point of Sale solution designed for modern retailers. Manage sales, inventory, customers, and expenses effortlessly from a single dashboard.</p>
                    <div>
                        <a href="{{ route('login') }}" class="btn-primary-custom">Login to Dashboard</a>
                        {{-- <a href="#features" class="btn-secondary-custom">View Features</a> --}}
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="hero-image-box">
                        <img src="{{ asset('assets/images/Pos-1.png') }}" alt="{{ readConfig('site_name') }} Dashboard" class="img-fluid">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- SHOWCASE SECTION - 3 IMAGES -->
    <section class="showcase">
        <div class="container">
            <h2 class="section-title">Streamline Your Operations</h2>
            <div class="showcase-grid">
                <div class="showcase-card">
                    <img src="{{ asset('assets/images/invoice-01.jpeg') }}" alt="POS Interface" class="showcase-image">
                    <div class="showcase-content">
                        <h4>Fast & Intuitive POS</h4>
                        <p>Process transactions quickly with our user-friendly interface. Support for barcode scanning, multiple payment methods, and custom receipts.</p>
                    </div>
                </div>
                <div class="showcase-card">
                    <img src="{{ asset('assets/images/invoice-02.jpeg') }}" alt="Inventory Management" class="showcase-image">
                    <div class="showcase-content">
                        <h4>Smart Inventory Control</h4>
                        <p>Track stock levels in real-time, manage suppliers, receive purchase alerts, and never run out of your best-selling items.</p>
                    </div>
                </div>
                <div class="showcase-card">
                    <img src="{{ asset('assets/images/invoice-03.jpeg') }}" alt="Analytics & Reporting" class="showcase-image">
                    <div class="showcase-content">
                        <h4>Actionable Analytics</h4>
                        <p>Gain deep insights into your business performance with detailed sales reports, profit/loss analysis, and customer trends.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FEATURES SECTION -->
    <section class="features" id="features">
        <div class="container">
            <h2 class="section-title">Everything You Need to Grow</h2>
            <div class="row">
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon">💻</div>
                        <h3>Powerful Point of Sale</h3>
                        <p>A robust POS interface that handles sales, returns, and exchanges seamlessly. Works online and offline to keep your business running.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon">📦</div>
                        <h3>Inventory Management</h3>
                        <p>Complete control over your stock. Track variants, manage expiration dates, and automate reordering processes.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon">👥</div>
                        <h3>Customer & Supplier CRM</h3>
                        <p>Build relationships with built-in CRM. Track customer purchase history, manage supplier contacts, and offer loyalty programs.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon">💰</div>
                        <h3>Expense Tracking</h3>
                        <p>Monitor your business expenses effortlessly. Categorize operational costs to get a clear picture of your net profit.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon">📊</div>
                        <h3>Detailed Reporting</h3>
                        <p>Make data-driven decisions with comprehensive reports on sales, inventory, taxes, and employee performance.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon">🔐</div>
                        <h3>User Roles & Permissions</h3>
                        <p>Secure your data with granular access controls. Assign specific roles and permissions to your staff members.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- BENEFITS SECTION -->
    <section class="benefits">
        <div class="container">
            <h2 class="section-title">Why Choose {{ readConfig('site_name') }}?</h2>
            <div class="benefits-grid">
                <div class="benefit-item">
                    <div class="benefit-icon">🚀</div>
                    <div>
                        <h4>Boost Efficiency</h4>
                        <p>Automate manual tasks and speed up checkout times, allowing you to serve more customers.</p>
                    </div>
                </div>
                <div class="benefit-item">
                    <div class="benefit-icon">📈</div>
                    <div>
                        <h4>Increase Profitability</h4>
                        <p>Identify top-selling products and optimize your inventory to maximize margins.</p>
                    </div>
                </div>
                <div class="benefit-item">
                    <div class="benefit-icon">☁️</div>
                    <div>
                        <h4>Cloud-Based Access</h4>
                        <p>Access your business data from anywhere, anytime, on any device.</p>
                    </div>
                </div>
                <div class="benefit-item">
                    <div class="benefit-icon">🛡️</div>
                    <div>
                        <h4>Secure & Reliable</h4>
                        <p>Your data is protected with enterprise-grade security and regular backups.</p>
                    </div>
                </div>
                <div class="benefit-item">
                    <div class="benefit-icon">🤝</div>
                    <div>
                        <h4>Dedicated Support</h4>
                        <p>Our support team is always ready to help you resolve any issues quickly.</p>
                    </div>
                </div>
                <div class="benefit-item">
                    <div class="benefit-icon">✨</div>
                    <div>
                        <h4>Easy to Use</h4>
                        <p>Intuitive interface requires minimal training, so your team can get started immediately.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ABOUT SECTION -->
    <section class="about">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 about-content">
                    <h2>About {{ readConfig('site_name') }}</h2>
                    <p>{{ readConfig('site_name') }} is a leading Point of Sale and Inventory Management solution tailored for businesses in Pakistan. We empower retailers to streamline their operations, reduce costs, and grow their revenue through technology.</p>
                    <p>Whether you run a small retail shop or a large chain of stores, {{ readConfig('site_name') }} provides the tools you need to succeed in today's competitive market.</p>
                    <ul class="about-list">
                        <li><strong>✓ FBR Integrated:</strong> Fully compliant with FBR POS integration requirements.</li>
                        <li><strong>✓ Multi-Location Support:</strong> Manage multiple branches from a central admin panel.</li>
                        <li><strong>✓ Real-Time Sync:</strong> Seamless synchronization between online and offline data.</li>
                    </ul>
                </div>
                <div class="col-lg-6 text-center">
                    <img src="{{ asset('assets/images/invoice.jpeg') }}" alt="{{ readConfig('site_name') }} Overview" class="about-image">
                </div>
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer class="footer">
        <div class="container">
            <div class="footer-bottom">
                <p>&copy; {{ date('Y') }} {{ readConfig('site_name') }}. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
