{{-- Fonts, icons, styles and the saved light/dark theme. The theme is applied before the page paints so it never flashes. --}}
<script>
    (function () {
        var root = document.documentElement;
        var theme = null;
        var sidebar = null;

        try {
            theme = localStorage.getItem('ui-theme');
            sidebar = localStorage.getItem('ui-sidebar');
        } catch (error) {}

        // Dark is the default until the user picks a theme with the toggle.
        if (theme !== 'light' && theme !== 'dark') {
            theme = 'dark';
        }

        root.setAttribute('data-bs-theme', theme);

        if (sidebar === 'collapsed') {
            root.setAttribute('data-sidebar', 'collapsed');
        }
    })();
</script>
<meta name="color-scheme" content="light dark">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
@vite(['resources/css/app.css', 'resources/js/app.js'])
