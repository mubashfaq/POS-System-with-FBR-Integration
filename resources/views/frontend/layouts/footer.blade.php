<!-- FOOTER -->
<footer class="footer">
    <div class="container">
        <div class="footer-content">

            <p class="mt-2">
                Copyright &copy; {{ date('Y') }} {{ readConfig('site_name') }}.
                All rights reserved.
            </p>

            <div class="footer-links">
                <a href="{{ route('frontend.home') }}">Home</a>
                <a href="{{ route('frontend.terms-conditions') }}">Terms & Conditions</a>
                <a href="{{ route('frontend.pricing') }}">Pricing</a>
            </div>

            <span>
                Designed & Developed By Techuick
            </span>

        </div>
    </div>
</footer>

<!-- WhatsApp Button -->
<a href="https://wa.me/+923235741450?text=Hello,%20I%20need%20more%20information?" class="whatsapp-float" target="_blank">
    <i class="fab fa-whatsapp"></i>
</a>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>