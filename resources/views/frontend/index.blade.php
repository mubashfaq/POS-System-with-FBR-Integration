@include('frontend.layouts.header', [
    'pageTitle' => 'Digital Invoicing & POS System',
    'metaDescription' => 'Manage invoices, sales, and inventory with our powerful digital invoicing and POS system for modern businesses.',
    'metaKeywords' => 'digital invoicing, POS system, inventory management, billing software Pakistan'
])

<!-- HERO SECTION -->
<section class="hero">
    <div class="container hero-content">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h1>FBR Integrated POS Software & Inventory Management System in Pakistan</h1>
                <p>The all-in-one Point of Sale solution designed for modern retailers. Manage sales, inventory,
                    customers, and expenses effortlessly from a single dashboard.</p>

                <!-- Trust Bullets -->
                <ul class="list-unstyled mb-3">
                    <li class="mb-2">✔ FBR POS / Digital Invoicing System Integrated</li>
                    <li class="mb-2">✔ Works Offline & Online</li>
                    <li class="mb-2">✔ Trusted by Retailers Across Pakistan</li>
                </ul>

                <div class="mb-2">
                    <a href="{{ route('login') }}" class="btn-primary-custom">Login to Dashboard</a>
                    <a href="{{ route('plans.index') }}" class="btn-secondary-custom">View Prices</a>
                </div>

                <!-- Pricing Hint -->
                <small class="text-white-50">Starting from Rs. 4,999 / month</small>
            </div>
            <div class="col-lg-6">
                <div class="hero-image-box">
                    <img src="{{ asset('assets/images/Pos-1.webp') }}" alt="{{ readConfig('site_name') }} Dashboard" loading="eager" class="img-fluid">
                </div>
            </div>
        </div>
    </div>
</section>

<!-- SHOWCASE SECTION - 3 IMAGES -->
<section class="showcase">
    <div class="container">
        <h2 class="section-title">POS Software, Inventory Control & Sales Reporting</h2>
        <div class="showcase-grid">
            <div class="showcase-card">
                <img src="{{ asset('assets/images/invoice-01.webp') }}" alt="POS Interface" loading="lazy" class="showcase-image">
                <div class="showcase-content">
                    <h4>Fast & Intuitive POS / Digital Invoicing System</h4>
                    <p>Process transactions quickly with our user-friendly interface. Support for barcode scanning,
                        multiple payment methods, and custom receipts.</p>
                </div>
            </div>
            <div class="showcase-card">
                <img src="{{ asset('assets/images/invoice-02.jpeg') }}" alt="Inventory Management" loading="lazy"
                    class="showcase-image">
                <div class="showcase-content">
                    <h4>Smart Inventory Control</h4>
                    <p>Track stock levels in real-time, manage suppliers, receive purchase alerts, and never run out of
                        your best-selling items.</p>
                </div>
            </div>
            <div class="showcase-card">
                <img src="{{ asset('assets/images/invoice-03.webp') }}" alt="Analytics & Reporting" loading="lazy"
                    class="showcase-image">
                <div class="showcase-content">
                    <h4>Actionable Analytics</h4>
                    <p>Gain deep insights into your business performance with detailed sales reports, profit/loss
                        analysis, and customer trends.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FEATURES SECTION -->
<section class="features" id="features">
    <div class="container">
        <h2 class="section-title">Features of Our Retail POS & Inventory System</h2>
        <div class="row">
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="feature-card">
                    <div class="feature-icon">💻</div>
                    <h3>Powerful Point of Sale</h3>
                    <p>Handle sales, returns, and exchanges seamlessly with a powerful POS system.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="feature-card">
                    <div class="feature-icon">📦</div>
                    <h3>Inventory Management</h3>
                    <p>Control stock efficiently with variant tracking and automated reordering.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="feature-card">
                    <div class="feature-icon">👥</div>
                    <h3>Customer & Supplier CRM</h3>
                    <p>Manage customers and suppliers with built-in CRM and loyalty features.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="feature-card">
                    <div class="feature-icon">💰</div>
                    <h3>Expense Tracking</h3>
                    <p>Track and manage business expenses to understand your net profit.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="feature-card">
                    <div class="feature-icon">📊</div>
                    <h3>Detailed Reporting</h3>
                    <p>Gain insights with detailed reports on sales, inventory, taxes, and performance.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="feature-card">
                    <div class="feature-icon">🔐</div>
                    <h3>User Roles & Permissions</h3>
                    <p>Secure your data with role-based access control for staff.</p>
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

<!-- TRUST SECTION -->
<section class="trust">
    <div class="container text-center">
        <h2 class="section-title">Trusted by Retailers Across Pakistan</h2>
        <p class="mb-5">Thousands of businesses rely on our POS and invoicing system to manage sales and growth.</p>

        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="trust-card">
                    <img src="{{ asset('assets/images/trust-01.webp') }}" loading="lazy" alt="Retail shop using POS system in Pakistan">
                    <p>“This system transformed our daily sales tracking!”</p>
                </div>
            </div>

            <div class="col-md-4 mb-4">
                <div class="trust-card">
                    <img src="{{ asset('assets/images/trust-02.webp') }}" loading="lazy" alt="Grocery store using inventory system">
                    <p>“Easy to use and perfect for inventory management.”</p>
                </div>
            </div>

            <div class="col-md-4 mb-4">
                <div class="trust-card">
                    <img src="{{ asset('assets/images/trust-03.webp') }}" loading="lazy" alt="Clothing store using digital invoicing system">
                    <p>“Highly recommended for retail businesses in Pakistan.”</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FAQ SECTION -->
<section class="faq">
    <div class="container">
        <h2 class="section-title text-center">Frequently Asked Questions</h2>

        <div class="accordion" id="faqAccordion">

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button" data-bs-toggle="collapse" data-bs-target="#faq1">
                        Does your system support FBR integration?
                    </button>
                </h2>
                <div id="faq1" class="accordion-collapse collapse show">
                    <div class="accordion-body">
                        Yes, our system supports FBR POS integration for compliant invoicing in Pakistan.
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#faq2">
                        Does it work offline?
                    </button>
                </h2>
                <div id="faq2" class="accordion-collapse collapse">
                    <div class="accordion-body">
                        Yes, the system works offline and syncs data when internet is available.
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#faq3">
                        Do you offer multi-branch support?
                    </button>
                </h2>
                <div id="faq3" class="accordion-collapse collapse">
                    <div class="accordion-body">
                        Yes, you can manage multiple branches from a single dashboard.
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#faq4">
                        Is it compatible with barcode scanners?
                    </button>
                </h2>
                <div id="faq4" class="accordion-collapse collapse">
                    <div class="accordion-body">
                        Yes, it supports standard barcode scanners for fast billing.
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- CTA SECTION -->
<section class="cta">
    <div class="container">
        <h2>Ready to Digitize Your Store?</h2>
        <p>Start managing your sales, inventory, and invoices with ease.</p>

        <a href="{{ route('login') }}" class="btn-primary-custom">Start Free Trial</a>
        <a href="{{ route('plans.index') }}" class="btn-secondary-custom">View Pricing</a>
    </div>
</section>

<!-- SEO SECTION -->
<section class="seo-section">
    <div class="container">
        <p>
            Our POS software in Pakistan is designed to meet modern business needs, including FBR POS integration, real-time inventory management, and seamless billing. Whether you run a retail shop, grocery store, or multi-branch business, our digital invoicing system helps streamline operations and boost profitability.
        </p>
    </div>
</section>

<!-- ABOUT SECTION -->
<section class="about">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6 about-content">
                <h2>About {{ readConfig('site_name') }}</h2>
                <p>{{ readConfig('site_name') }} is a leading Point of Sale and Inventory Management solution tailored
                    for businesses in Pakistan. We empower retailers to streamline their operations, reduce costs, and
                    grow their revenue through technology.</p>
                <p>Whether you run a small retail shop or a large chain of stores, {{ readConfig('site_name') }}
                    provides the tools you need to succeed in today's competitive market.</p>
                <ul class="about-list">
                    <li><strong>✓ FBR Integrated:</strong> Fully compliant with FBR POS / Digital Invoicing System
                        integration requirements.</li>
                    <li><strong>✓ Multi-Location Support:</strong> Manage multiple branches from a central admin panel.
                    </li>
                    <li><strong>✓ Real-Time Sync:</strong> Seamless synchronization between online and offline data.
                    </li>
                </ul>
            </div>
            <div class="col-lg-6 text-center">
                <img src="{{ asset('assets/images/invoice.jpeg') }}" loading="lazy" alt="{{ readConfig('site_name') }} Overview"
                    class="about-image">
            </div>
        </div>
    </div>
</section>

@include('frontend.layouts.footer')