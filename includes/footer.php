    </div><!-- /.container -->

    <footer class="bg-light text-center text-muted py-3 mt-5 border-top">
        <p class="mb-0">&copy; <?= date('Y') ?> E-Wallet. All rights reserved.</p>
    </footer>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
    document.querySelectorAll('.nav-feature-locked').forEach(el => {
        el.addEventListener('click', function (e) {
            e.preventDefault();
            alert('This feature is only available for verified accounts.');
        });
    });
    </script>
</body>
</html>
