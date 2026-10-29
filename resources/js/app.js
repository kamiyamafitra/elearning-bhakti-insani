import 'bootstrap';

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
