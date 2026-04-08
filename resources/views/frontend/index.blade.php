@include('frontend.layouts.header', ['pageTitle' => 'Home'])

<!-- HERO SECTION -->
<section class="hero">
    <div class="container hero-content">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h1>Empower Your Business with {{ readConfig('site_name') }}</h1>
                <p>The all-in-one Point of Sale solution designed for modern retailers. Manage sales, inventory,
                    customers, and expenses effortlessly from a single dashboard.</p>
                <div>
                    <a href="{{ route('login') }}" class="btn-primary-custom">Login to Dashboard</a>
                    <a href="{{ route('frontend.pricing') }}" class="btn-secondary-custom">View Prices</a>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="hero-image-box">
                    <img src="{{ asset('assets/images/Pos-1.png') }}" alt="{{ readConfig('site_name') }} Dashboard"
                        class="img-fluid">
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
                    <h4>Fast & Intuitive POS / Digital Invoicing System</h4>
                    <p>Process transactions quickly with our user-friendly interface. Support for barcode scanning,
                        multiple payment methods, and custom receipts.</p>
                </div>
            </div>
            <div class="showcase-card">
                <img src="{{ asset('assets/images/invoice-02.jpeg') }}" alt="Inventory Management"
                    class="showcase-image">
                <div class="showcase-content">
                    <h4>Smart Inventory Control</h4>
                    <p>Track stock levels in real-time, manage suppliers, receive purchase alerts, and never run out of
                        your best-selling items.</p>
                </div>
            </div>
            <div class="showcase-card">
                <img src="{{ asset('assets/images/invoice-03.png') }}" alt="Analytics & Reporting"
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
        <h2 class="section-title">Everything You Need to Grow</h2>
        <div class="row">
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="feature-card">
                    <div class="feature-icon">💻</div>
                    <h3>Powerful Point of Sale</h3>
                    <p>A robust POS / Digital Invoicing System interface that handles sales, returns, and exchanges
                        seamlessly. Works online and offline to keep your business running.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="feature-card">
                    <div class="feature-icon">📦</div>
                    <h3>Inventory Management</h3>
                    <p>Complete control over your stock. Track variants, manage expiration dates, and automate
                        reordering processes.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="feature-card">
                    <div class="feature-icon">👥</div>
                    <h3>Customer & Supplier CRM</h3>
                    <p>Build relationships with built-in CRM. Track customer purchase history, manage supplier contacts,
                        and offer loyalty programs.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="feature-card">
                    <div class="feature-icon">💰</div>
                    <h3>Expense Tracking</h3>
                    <p>Monitor your business expenses effortlessly. Categorize operational costs to get a clear picture
                        of your net profit.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="feature-card">
                    <div class="feature-icon">📊</div>
                    <h3>Detailed Reporting</h3>
                    <p>Make data-driven decisions with comprehensive reports on sales, inventory, taxes, and employee
                        performance.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="feature-card">
                    <div class="feature-icon">🔐</div>
                    <h3>User Roles & Permissions</h3>
                    <p>Secure your data with granular access controls. Assign specific roles and permissions to your
                        staff members.</p>
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
                <img src="{{ asset('assets/images/invoice.jpeg') }}" alt="{{ readConfig('site_name') }} Overview"
                    class="about-image">
            </div>
        </div>
    </div>
</section>

@include('frontend.layouts.footer')