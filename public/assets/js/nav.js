/* Mobile nav toggle + close open dropdowns when clicking elsewhere. */
(function () {
    var toggle = document.querySelector('[data-nav-toggle]');
    var nav = document.getElementById('main-nav');

    if (toggle && nav) {
        toggle.addEventListener('click', function () {
            var open = nav.classList.toggle('open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    document.addEventListener('click', function (event) {
        document.querySelectorAll('.nav-group[open]').forEach(function (group) {
            if (!group.contains(event.target)) {
                group.removeAttribute('open');
            }
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            document.querySelectorAll('.nav-group[open]').forEach(function (group) {
                group.removeAttribute('open');
            });
        }
    });
})();
