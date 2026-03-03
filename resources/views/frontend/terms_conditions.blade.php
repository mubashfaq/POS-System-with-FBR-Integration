@include('frontend.layouts.header', ['pageTitle' => 'Terms and Conditions'])

    <style>
        body {
            font-family: 'Poppins';
            background-color: #f8f9fa;
            color: #334155;
            line-height: 1.6;
        }

        .terms-header {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            color: white;
            padding: 80px 0;
            margin-bottom: 50px;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .sidebar-sticky {
            position: sticky;
            top: 20px;
        }

        .list-group-item {
            border: none;
            padding: 12px 20px;
            font-weight: 500;
            color: #64748b;
            transition: all 0.3s;
        }

        .list-group-item.active {
            background-color: #e2e8f0;
            color: #0f172a;
            border-left: 4px solid #3b82f6;
        }

        h2 {
            font-weight: 700;
            color: #0f172a;
            margin-top: 40px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }

        h2 i {
            font-size: 0.8em;
            margin-right: 15px;
            color: #3b82f6;
        }

        .last-updated {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .highlight-box {
            background-color: #eff6ff;
            border-left: 4px solid #3b82f6;
            padding: 20px;
            margin: 20px 0;
            border-radius: 0 8px 8px 0;
        }
    </style>


    <header class="terms-header text-center">
        <div class="container">
            <h1 class="display-4 fw-bold">Terms and Conditions</h1>
            <p class="last-updated">Last Updated: January 19, 2026</p>
        </div>
    </header>

    <div class="container mb-5">
        <div class="row">
            <div class="col-lg-3 d-none d-lg-block">
                <div class="sidebar-sticky">
                    <div class="list-group shadow-sm">
                        <a href="#nature" class="list-group-item list-group-item-action active">1. Nature of Service</a>
                        <a href="#compliance" class="list-group-item list-group-item-action">2. FBR Compliance</a>
                        <a href="#prohibited" class="list-group-item list-group-item-action">3. Prohibited Use</a>
                        <a href="#payments" class="list-group-item list-group-item-action">4. Fees & Payments</a>
                        <a href="#liability" class="list-group-item list-group-item-action">5. Liability</a>
                        <a href="#privacy" class="list-group-item list-group-item-action">6. Privacy</a>
                        <a href="#termination" class="list-group-item list-group-item-action">7. Termination</a>
                        <a href="#law" class="list-group-item list-group-item-action">8. Governing Law</a>
                    </div>
                </div>
            </div>

            <div class="col-lg-9">
                <div class="card p-4 p-md-5 bg-white">
                    <p class="lead">These Terms and Conditions ("Terms") govern your access to and use of
                        <strong>PakKhata</strong> ("the Software"), a SaaS platform owned and operated by <strong>[Your
                            Company Name]</strong> ("the Company"). By subscribing to or using the Software, you agree
                        to be bound by these Terms.</p>

                    <section id="nature">
                        <h2><i class="fas fa-info-circle"></i> 1. Nature of Service</h2>
                        <p>PakKhata provides an automated invoicing and bookkeeping interface designed to facilitate
                            data transmission to the Federal Board of Revenue (FBR) in Pakistan.</p>
                        <ul>
                            <li><strong>Not a Tax Professional:</strong> PakKhata is a technical tool. The Company does
                                not provide legal, accounting, or tax advice.</li>
                            <li><strong>User Responsibility:</strong> You are solely responsible for the accuracy,
                                legality, and integrity of the data entered into the system.</li>
                        </ul>
                    </section>

                    <section id="compliance">
                        <h2><i class="fas fa-check-double"></i> 2. FBR Compliance & Integration</h2>
                        <p>While PakKhata is designed to meet FBR digital invoicing standards (including QR code
                            generation and JSON transmission), the Company is not responsible for:</p>
                        <div class="highlight-box">
                            Failures in transmission caused by FBR server outages, your internet connectivity, or
                            incorrect user credentials.
                        </div>
                        <p><strong>Audit Logs:</strong> In compliance with Sales Tax Rules 2006, the Software maintains
                            immutable logs of transactions. You acknowledge that these logs may be shared with tax
                            authorities if required by law.</p>
                    </section>

                    <section id="prohibited">
                        <h2><i class="fas fa-ban"></i> 3. Prohibited Use</h2>
                        <p>You agree <strong>NOT</strong> to use the Software to:</p>
                        <ul>
                            <li><strong>Skimming:</strong> Intentionally delete or alter sales records to evade tax
                                liabilities.</li>
                            <li><strong>Reverse Engineering:</strong> Attempt to copy, modify, or decompile the
                                Software’s source code.</li>
                            <li><strong>Unauthorized Access:</strong> Use the Software for any business entity other
                                than the one registered.</li>
                        </ul>
                    </section>

                    <section id="payments">
                        <h2><i class="fas fa-credit-card"></i> 4. Fees and Payments</h2>
                        <p>Subscriptions are billed <strong>[Monthly/Annually]</strong>. Non-payment of fees will result
                            in the suspension of the API connection to FBR, which may lead to legal penalties for your
                            business.</p>
                    </section>

                    <section id="liability">
                        <h2><i class="fas fa-exclamation-triangle"></i> 5. Limitation of Liability</h2>
                        <p>To the maximum extent permitted by Pakistani law, the Company shall not be liable for:</p>
                        <ul>
                            <li>Tax penalties or fines imposed by the FBR.</li>
                            <li>Loss of business profits due to software downtime.</li>
                            <li>Data loss resulting from user negligence.</li>
                        </ul>
                        <p class="fw-bold">Total liability is limited to the amount paid by the user for the service in
                            the twelve (12) months preceding the claim.</p>
                    </section>

                    <section id="privacy">
                        <h2><i class="fas fa-shield-alt"></i> 6. Data Privacy & Security</h2>
                        <p>Your data is handled according to our Privacy Policy. We utilize <strong>AES-256
                                encryption</strong> to protect your financial records. However, no digital transmission
                            is 100% secure.</p>
                    </section>

                    <section id="termination">
                        <h2><i class="fas fa-door-open"></i> 7. Termination</h2>
                        <p>Either party may terminate this agreement with <strong>[30 days]</strong> written notice.
                            Upon termination, you are responsible for exporting your data.</p>
                    </section>

                    <section id="law">
                        <h2><i class="fas fa-gavel"></i> 8. Governing Law</h2>
                        <p>These Terms shall be governed by and construed in accordance with the laws of the
                            <strong>Islamic Republic of Pakistan</strong>. Any disputes shall be subject to the
                            exclusive jurisdiction of the courts in [City, e.g., Lahore/Karachi].</p>
                    </section>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.addEventListener('scroll', event => {
            const navLinks = document.querySelectorAll('.list-group-item');
            const sections = document.querySelectorAll('section');

            let current = '';
            sections.forEach(section => {
                const sectionTop = section.offsetTop;
                if (pageYOffset >= sectionTop - 100) {
                    current = section.getAttribute('id');
                }
            });

            navLinks.forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href').includes(current)) {
                    link.classList.add('active');
                }
            });
        });
    </script>
@include('frontend.layouts.footer')