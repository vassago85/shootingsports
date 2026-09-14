document.addEventListener('click', (event) => {
    const burger = event.target.closest('#burger');

    if (! burger) {
        return;
    }

    const menu = document.getElementById('mobile-menu');

    if (! menu) {
        return;
    }

    const open = menu.classList.toggle('open');
    burger.setAttribute('aria-expanded', open ? 'true' : 'false');
});

