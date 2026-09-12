/*
 * Confirmation prompts for destructive or final actions.
 * Usage: <a href="…" data-confirm="Close this report?">  or  <form data-confirm="Reject this claim?">
 */
(function () {
    document.addEventListener('click', function (event) {
        var target = event.target.closest('a[data-confirm], button[data-confirm]');
        if (target && !window.confirm(target.dataset.confirm)) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    }, true);

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (form.matches('form[data-confirm]') && !window.confirm(form.dataset.confirm)) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    }, true);
})();
