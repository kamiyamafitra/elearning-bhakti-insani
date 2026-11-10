import 'bootstrap';
import { initFilePreview } from './file-preview';

initFilePreview();

const rememberSetting = (key, value) => {
    try {
        localStorage.setItem(key, value);
    } catch (error) {
        // Private windows can block storage; the choice then only lasts for this page.
    }
};

document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const root = document.documentElement;
        const theme = root.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';

        const apply = () => root.setAttribute('data-bs-theme', theme);
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (reduceMotion) {
            apply();
        } else if (document.startViewTransition) {
            // Cross-fade the whole page in one smooth step.
            document.startViewTransition(apply);
        } else {
            root.classList.add('theme-switching');
            apply();
            window.setTimeout(() => root.classList.remove('theme-switching'), 400);
        }
        rememberSetting('ui-theme', theme);
    });
});

// Collapsed sidebar shows icons only, so each link keeps its name as a tooltip.
document.querySelectorAll('.app-sidebar .nav-link').forEach((link) => {
    const label = link.querySelector('.nav-link__label');

    if (label && !link.title) {
        link.title = label.textContent.trim();
    }
});

document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
    const root = document.documentElement;
    const sync = () => {
        const collapsed = root.getAttribute('data-sidebar') === 'collapsed';
        const label = collapsed ? 'Lebarkan menu' : 'Ciutkan menu';

        button.setAttribute('aria-label', label);
        button.title = label;
        button.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    };

    sync();

    button.addEventListener('click', () => {
        if (root.getAttribute('data-sidebar') === 'collapsed') {
            root.removeAttribute('data-sidebar');
            rememberSetting('ui-sidebar', 'expanded');
        } else {
            root.setAttribute('data-sidebar', 'collapsed');
            rememberSetting('ui-sidebar', 'collapsed');
        }

        sync();
    });
});

document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    const input = document.querySelector(button.dataset.passwordToggle);

    if (!input) {
        return;
    }

    button.addEventListener('click', () => {
        const isHidden = input.type === 'password';

        input.type = isHidden ? 'text' : 'password';
        button.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
        button.setAttribute('aria-label', isHidden ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');

        const icon = button.querySelector('i');

        if (icon) {
            icon.classList.toggle('bi-eye', !isHidden);
            icon.classList.toggle('bi-eye-slash', isHidden);
        }
    });
});
