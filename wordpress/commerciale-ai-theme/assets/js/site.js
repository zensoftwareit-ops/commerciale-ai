(function () {
    'use strict';

    const header = document.getElementById('site-header');
    const button = document.querySelector('.menu-toggle');
    const menu = document.getElementById('primary-menu');

    const updateHeader = () => header && header.classList.toggle('is-scrolled', window.scrollY > 8);
    updateHeader();
    window.addEventListener('scroll', updateHeader, { passive: true });

    if (button && menu) {
        button.addEventListener('click', () => {
            const open = menu.classList.toggle('is-open');
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        menu.addEventListener('click', (event) => {
            if (event.target.closest('a')) {
                menu.classList.remove('is-open');
                button.setAttribute('aria-expanded', 'false');
            }
        });
    }
}());
