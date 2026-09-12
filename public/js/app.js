/*
 * CLAFS front-end behaviour. Each section is independent and activates only when
 * its data-* hook is present on the page. Requires api.js to be loaded first.
 *
 *   1. Navigation      [data-nav-toggle], .nav-group dropdowns
 *   2. Confirmations   [data-confirm] on links, buttons and forms
 *   3. Validation      <form data-validate> (+ data-mock stops submission until the backend exists)
 *   4. Image preview   <input type="file" data-preview="#target">
 *   5. Table filter    <input data-table-filter="#table">, [data-filter-count], [data-filter-empty]
 *   6. API forms       <form data-api="update_status" data-type="found|lost"> → ClafsApi.updateStatus()
 */

/* ---------------------------------------------------------------- 1. Navigation */
(function () {
    var toggle = document.querySelector('[data-nav-toggle]');
    var nav = document.getElementById('main-nav');

    if (toggle && nav) {
        toggle.addEventListener('click', function () {
            var open = nav.classList.toggle('open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    function closeDropdowns(except) {
        document.querySelectorAll('.nav-group[open]').forEach(function (group) {
            if (group !== except) group.removeAttribute('open');
        });
    }

    document.addEventListener('click', function (event) {
        closeDropdowns(event.target.closest('.nav-group'));
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeDropdowns(null);
    });
})();

/* ---------------------------------------------------------------- 2. Confirmations */
(function () {
    // Capture phase so a cancelled confirm also stops the API-form handler below.
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

/* ---------------------------------------------------------------- 3. Validation */
(function () {
    /*
     * Mirrors the rules the server enforces (api/add_item.php); a convenience, not security.
     * Field hints: required, minlength, maxlength, type=email, type=date with max/min, type=file,
     *   data-mapua-email       → must end with an allowed Mapua domain
     *   data-match="password"  → must equal the field with that name
     *   data-min-words="5"     → at least N words (used for proof descriptions)
     * Forms marked data-mock stop before submitting and show a notice. Remove data-mock once handlers exist.
     */
    var ALLOWED_DOMAINS = ['mymail.mapua.edu.ph', 'mapua.edu.ph'];
    var IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    var MAX_IMAGE_BYTES = 5 * 1024 * 1024;

    function groupOf(field) {
        return field.closest('.form-group') || field.parentElement;
    }

    function setError(field, message) {
        var group = groupOf(field);
        var node = group.querySelector('.form-error');
        if (!node) {
            node = document.createElement('span');
            node.className = 'form-error';
            node.setAttribute('aria-live', 'polite');
            group.appendChild(node);
        }
        node.textContent = message;
        field.classList.add('is-invalid');
        field.setAttribute('aria-invalid', 'true');
    }

    function clearError(field) {
        field.classList.remove('is-invalid');
        field.removeAttribute('aria-invalid');
        var node = groupOf(field).querySelector('.form-error');
        if (node) node.textContent = '';
    }

    function validateField(field) {
        if (field.disabled || field.type === 'hidden' || field.type === 'submit' || field.type === 'button') return '';
        var value = (field.value || '').trim();

        if (field.type === 'checkbox') {
            return field.required && !field.checked ? 'Please tick this box to continue.' : '';
        }

        if (field.type === 'file') {
            if (field.required && !field.files.length) return 'Please choose a file.';
            if (field.files.length) {
                var file = field.files[0];
                if (IMAGE_TYPES.indexOf(file.type) === -1) return 'Only JPG, PNG or WEBP images are allowed.';
                if (file.size > MAX_IMAGE_BYTES) return 'Image must be 5 MB or smaller.';
            }
            return '';
        }

        if (field.required && !value) return 'This field is required.';
        if (!value) return '';

        var minLength = parseInt(field.getAttribute('minlength'), 10);
        if (minLength && value.length < minLength) return 'Must be at least ' + minLength + ' characters.';

        var maxLength = parseInt(field.getAttribute('maxlength'), 10);
        if (maxLength && value.length > maxLength) return 'Must be ' + maxLength + ' characters or fewer.';

        if (field.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) return 'Enter a valid email address.';

        if (field.hasAttribute('data-mapua-email')) {
            var domain = (value.split('@')[1] || '').toLowerCase();
            if (ALLOWED_DOMAINS.indexOf(domain) === -1) return 'Use your Mapua email (@mymail.mapua.edu.ph or @mapua.edu.ph).';
        }

        if (field.dataset.match) {
            var other = field.form.querySelector('[name="' + field.dataset.match + '"]');
            if (other && other.value !== field.value) return 'Passwords do not match.';
        }

        if (field.type === 'date' && field.max && value > field.max) return 'Date cannot be in the future.';
        if (field.type === 'date' && field.min && value < field.min) return 'Date is too early.';

        var minWords = parseInt(field.dataset.minWords, 10);
        if (minWords && value.split(/\s+/).length < minWords) return 'Please give a little more detail (at least ' + minWords + ' words).';

        return '';
    }

    function recheck(field) {
        var message = validateField(field);
        message ? setError(field, message) : clearError(field);
    }

    function showMockNotice(form) {
        var existing = form.querySelector('.mock-notice');
        if (existing) existing.remove();
        var notice = document.createElement('div');
        notice.className = 'alert alert-info mock-notice';
        notice.setAttribute('role', 'status');
        notice.innerHTML = '<strong>Looks good!</strong> This form passed validation. Submitting will work once the backend is connected.';
        form.insertBefore(notice, form.firstChild);
        notice.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    document.querySelectorAll('form[data-validate]').forEach(function (form) {
        form.setAttribute('novalidate', '');

        form.addEventListener('submit', function (event) {
            var firstInvalid = null;
            form.querySelectorAll('input, select, textarea').forEach(function (field) {
                var message = validateField(field);
                if (message) {
                    setError(field, message);
                    if (!firstInvalid) firstInvalid = field;
                } else {
                    clearError(field);
                }
            });

            if (firstInvalid) {
                event.preventDefault();
                firstInvalid.focus();
                return;
            }
            if (form.hasAttribute('data-mock')) {
                event.preventDefault();
                showMockNotice(form);
            }
        });

        // Re-check a field as the user fixes it.
        form.addEventListener('input', function (event) {
            if (event.target.classList.contains('is-invalid')) recheck(event.target);
        });
        form.addEventListener('change', function (event) {
            if (event.target.type === 'file' || event.target.tagName === 'SELECT') recheck(event.target);
        });
    });
})();

/* ---------------------------------------------------------------- 4. Image preview */
(function () {
    document.querySelectorAll('input[type="file"][data-preview]').forEach(function (input) {
        var target = document.querySelector(input.dataset.preview);
        if (!target) return;

        var placeholder = target.innerHTML;
        var objectUrl = null;

        input.addEventListener('change', function () {
            if (objectUrl) {
                URL.revokeObjectURL(objectUrl);
                objectUrl = null;
            }

            var file = input.files && input.files[0];
            if (!file || file.type.indexOf('image/') !== 0) {
                target.innerHTML = placeholder;
                return;
            }

            objectUrl = URL.createObjectURL(file);
            var img = document.createElement('img');
            img.src = objectUrl;
            img.alt = 'Preview of selected photo';
            target.innerHTML = '';
            target.appendChild(img);
        });
    });
})();

/* ---------------------------------------------------------------- 5. Table filter */
(function () {
    document.querySelectorAll('[data-table-filter]').forEach(function (input) {
        var table = document.querySelector(input.dataset.tableFilter);
        if (!table || !table.tBodies.length) return;

        var rows = Array.prototype.slice.call(table.tBodies[0].rows);
        var wrap = table.closest('.table-wrap') || table.parentElement;
        var emptyNote = wrap ? wrap.parentElement.querySelector('[data-filter-empty]') : null;
        var countNode = document.querySelector('[data-filter-count="' + input.dataset.tableFilter + '"]');

        function apply() {
            var query = input.value.trim().toLowerCase();
            var shown = 0;
            rows.forEach(function (row) {
                var match = !query || row.textContent.toLowerCase().indexOf(query) !== -1;
                row.hidden = !match;
                if (match) shown++;
            });
            if (emptyNote) emptyNote.hidden = shown > 0;
            if (countNode) countNode.textContent = shown + ' of ' + rows.length;
        }

        input.addEventListener('input', apply);
        apply();
    });
})();

/* ---------------------------------------------------------------- 6. API forms */
(function () {
    if (!window.ClafsApi) return;

    // One page-level notice slot at the top of <main>, replaced on each result.
    function notify(type, message) {
        var main = document.getElementById('main');
        if (!main) return;
        var existing = main.querySelector('.js-flash');
        if (existing) existing.remove();
        var alert = document.createElement('div');
        alert.className = 'alert alert-' + type + ' js-flash';
        alert.setAttribute('role', 'status');
        alert.textContent = message;
        main.insertBefore(alert, main.firstChild);
        alert.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form.matches('form[data-api="update_status"]')) return;
        event.preventDefault();

        var id = form.querySelector('[name="id"]');
        var status = form.querySelector('[name="status"]');
        if (!id || !status) return;

        form.querySelectorAll('select, button').forEach(function (el) { el.disabled = true; });

        ClafsApi.updateStatus(form.dataset.type || 'found', parseInt(id.value, 10), status.value)
            .then(function (result) {
                notify(result.mock ? 'info' : 'success', result.message || 'Status updated.');
            })
            .catch(function (error) {
                notify('error', error.message);
            })
            .then(function () {
                form.querySelectorAll('select, button').forEach(function (el) { el.disabled = false; });
            });
    });
})();
