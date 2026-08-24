(function () {
    'use strict';
    document.querySelectorAll('.cai-plan form').forEach((form) => {
        form.addEventListener('submit', () => {
            const button = form.querySelector('button');
            if (!button) return;
            button.disabled = true;
            button.textContent = 'Apertura pagamento…';
        });
    });
}());
