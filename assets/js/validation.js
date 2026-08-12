(function () {
    'use strict';

    function showError(input, message) {
        input.classList.add('invalid');
        let el = input.parentElement.querySelector('.form-error');
        if (!el) {
            el = document.createElement('div');
            el.className = 'form-error';
            input.parentElement.appendChild(el);
        }
        el.textContent = message;
    }

    function clearError(input) {
        input.classList.remove('invalid');
        const el = input.parentElement.querySelector('.form-error');
        if (el) el.remove();
    }

    document.querySelectorAll('form[data-validate]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            let valid = true;

            form.querySelectorAll('[required]').forEach(function (input) {
                if (!input.value.trim()) {
                    showError(input, 'This field is required.');
                    valid = false;
                } else {
                    clearError(input);
                }
            });

            form.querySelectorAll('input[type="email"]').forEach(function (input) {
                if (input.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input.value)) {
                    showError(input, 'Enter a valid email address.');
                    valid = false;
                }
            });

            const pw = form.querySelector('input[name="password"]');
            const confirm = form.querySelector('input[name="confirm_password"]');
            if (pw && confirm && confirm.value && pw.value !== confirm.value) {
                showError(confirm, 'Passwords do not match.');
                valid = false;
            }

            form.querySelectorAll('input[data-min-amount]').forEach(function (input) {
                const min = parseFloat(input.getAttribute('data-min-amount'));
                const val = parseFloat(input.value);
                if (input.value && (isNaN(val) || val < min)) {
                    showError(input, 'Amount must be at least ' + min.toFixed(2) + '.');
                    valid = false;
                }
            });

            if (!valid) e.preventDefault();
        });
    });
})();
