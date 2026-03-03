@include('frontend.layouts.header', ['pageTitle' => 'Pricing'])

<style>
    body {
        font-family: 'Poppins', sans-serif;
        background: #ffffff;
        color: #000;
    }

    /* HERO SECTION */
    .hero-price {
        padding: 120px 0 100px;
        position: relative;
        overflow: hidden;
    }

    .hero-price::before {
        content: "";
        position: absolute;
        width: 600px;
        height: 600px;
        background: radial-gradient(circle, rgba(255, 102, 0, 0.15) 0%, rgba(255, 102, 0, 0) 70%);
        top: -200px;
        right: -200px;
    }

    .hero-price h1 {
        font-size: 56px;
        font-weight: 800;
    }

    .hero-price span {
        color: #ff6600;
    }

    .hero-price p {
        font-size: 18px;
        color: #555;
        margin-top: 20px;
        margin-bottom: 30px;
    }

    .btn-primary-custom {
        background: #ff6600;
        color: #fff;
        padding: 14px 32px;
        border-radius: 40px;
        font-weight: 600;
        border: none;
    }

    .btn-primary-custom:hover {
        background: #000;
        color: #fff;
    }

    .btn-outline-custom {
        border: 2px solid #000;
        padding: 12px 30px;
        border-radius: 40px;
        font-weight: 600;
        margin-left: 10px;
    }

    .btn-outline-custom:hover {
        background: #000;
        color: #fff;
    }

    /* Pricing Section */
    .pricing {
        padding: 80px 0 120px;
    }

    .pricing-card {
        background: #fff;
        border-radius: 20px;
        padding: 45px 35px;
        border: 1px solid #eee;
        transition: 0.3s ease;
    }

    .pricing-card:hover {
        border-color: #ff6600;
        transform: translateY(-6px);
    }

    .popular {
        border: 2px solid #ff6600;
    }

    .badge-popular {
        background: #ff6600;
        color: #fff;
        padding: 6px 18px;
        border-radius: 30px;
        font-size: 13px;
        font-weight: 600;
        position: absolute;
        top: -15px;
        right: 30px;
    }


    .price {
        font-size: 42px;
        font-weight: 800;
        color: #ff6600;
        margin: 20px 0;
    }

    .price-features li {
        margin-bottom: 12px;
    }

    .pricing-note {
        color: #6b7280;
        font-size: 13px;
    }
</style>

<!-- HERO -->
<section class="hero-price">
    <div class="container">
        <div class="row align-items-center">

            <div class="col-lg-6">
                <h1>Smart POS System <span>with FBR Integration</span></h1>
                <p>Powerful, secure and fully compliant Point of Sale software connected with FBR real-time invoicing.
                    Perfect for retail stores, supermarkets and multi-branch businesses.</p>
                {{-- <button class="btn btn-primary-custom">Start Free Trial</button> --}}
                {{-- <button class="btn btn-outline-custom">View Demo</button> --}}
            </div>

            <div class="col-lg-6 text-center">
                <img src="https://cdn-icons-png.flaticon.com/512/3135/3135706.png" width="360" class="img-fluid">
            </div>

        </div>
    </div>
</section>

<!-- PRICING -->
<section class="pricing">
    <div class="container">
        <div class="row g-4">

            <div class="col-lg-4">
                <div class="pricing-card text-center">
                    <h4>Basic</h4>
                    <div class="price">Rs 1000</div>
                    <p class="text-muted">Per Month</p>
                    <ul class="list-unstyled price-features mt-4">
                        <li>✔ 5 Invoices</li>
                        {{-- <li>✔ FBR Integration</li> --}}
                        {{-- <li>✔ Basic Reports</li> --}}
                        {{-- <li>✔ 1 User</li> --}}
                        {{-- <li>✔ Email Support</li> --}}
                    </ul>
                    <a href="https://wa.me/+923235741450?text=Hello,%20I%20need%20Basic%20Package?" target="_blank" class="btn btn-primary-custom mt-3">Get Started</a>
                </div>
            </div>

            <div class="col-lg-4 position-relative">
                <div class="pricing-card text-center popular">
                    <div class="badge-popular">Most Popular</div>
                    <h4>Standard</h4>
                    <div class="price">Rs 2000</div>
                    <p>Per Month</p>
                    <ul class="list-unstyled price-features mt-4">
                        <li>✔ 20 Invoices</li>
                        {{-- <li>✔ Inventory System</li> --}}
                        {{-- <li>✔ Advanced Reports</li> --}}
                        {{-- <li>✔ 5 Users</li> --}}
                        {{-- <li>✔ WhatsApp Support</li> --}}
                    </ul>
                    <a href="https://wa.me/+923235741450?text=Hello,%20I%20need%20Standard%20Package?" target="_blank" class="btn btn-primary-custom mt-3">Get Started</a>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="pricing-card text-center">
                    <h4>Premium</h4>
                    <div class="price">Rs 4500</div>
                    <p class="text-muted">Per Month</p>
                    <ul class="list-unstyled price-features mt-4">
                        <li>✔ Unlimited Invoices</li>
                        {{-- <li>✔ Multi Branch</li> --}}
                        {{-- <li>✔ Unlimited Users</li> --}}
                        {{-- <li>✔ Custom Branding</li> --}}
                        {{-- <li>✔ 24/7 Priority Support</li> --}}
                    </ul>
                    <a href="https://wa.me/+923235741450?text=Hello,%20I%20need%20Premium%20Package?" target="_blank" class="btn btn-primary-custom mt-3">Get Started</a>
                </div>
            </div>

        </div>
    </div>
    <div class="text-center mt-4">
        <small class="pricing-note">
            * FBR integration is not included in these packages.
            Please contact us separately for FBR integration services.
        </small>
    </div>
</section>


@include('frontend.layouts.footer')