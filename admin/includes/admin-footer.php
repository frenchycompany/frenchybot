    </main>
    <script>
    // Copy to clipboard helper
    function copyToClipboard(text, btn) {
        navigator.clipboard.writeText(text).then(function() {
            var orig = btn.textContent;
            btn.textContent = 'Copie !';
            setTimeout(function() { btn.textContent = orig; }, 2000);
        });
    }
    </script>
</body>
</html>
