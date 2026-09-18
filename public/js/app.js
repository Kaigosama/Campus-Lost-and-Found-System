/*
 * CLAFS front-end behaviour. Requires api.js.
 *   [data-nav-toggle]                  mobile menu; .nav-group dropdowns close on outside click / Escape
 *   [data-confirm]                     confirm() before links, buttons and forms
 *   form[data-validate]                client-side rules mirroring the API (a convenience, not security)
 *   form[data-mock]                    validated only — handler not built yet
 *   form[data-api="add_item|update_status"]   submitted through ClafsApi
 *   input[type=file][data-preview]     image preview
 *   [data-table-filter]                quick text filter for a table
 */
(function () {
    var ALLOWED_DOMAINS = ['mymail.mapua.edu.ph', 'mapua.edu.ph'];
    var IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    var MAX_IMAGE_BYTES = 5 * 1024 * 1024;

    /* ---- Navigation ---- */
    var toggle = document.querySelector('[data-nav-toggle]');
    var nav = document.getElementById('main-nav');
    if (toggle && nav) {
        toggle.addEventListener('click', function () {
            toggle.setAttribute('aria-expanded', nav.classList.toggle('open') ? 'true' : 'false');
        });
    }
    function closeDropdowns(except) {
        document.querySelectorAll('.nav-group[open]').forEach(function (group) {
            if (group !== except) group.removeAttribute('open');
        });
    }
    document.addEventListener('click', function (event) { closeDropdowns(event.target.closest('.nav-group')); });
    document.addEventListener('keydown', function (event) { if (event.key === 'Escape') closeDropdowns(null); });

    /* ---- Confirmations (capture phase so a cancel also stops the submit handlers below) ---- */
    document.addEventListener('click', function (event) {
        var target = event.target.closest('a[data-confirm], button[data-confirm]');
        if (target && !window.confirm(target.dataset.confirm)) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    }, true);
    document.addEventListener('submit', function (event) {
        if (event.target.matches('form[data-confirm]') && !window.confirm(event.target.dataset.confirm)) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    }, true);

    /* ---- Alerts and field errors ---- */
    function alertIn(container, type, message, className) {
        if (!container) return;
        var existing = container.querySelector('.' + className);
        if (existing) existing.remove();
        if (!message) return;
        var node = document.createElement('div');
        node.className = 'alert alert-' + type + ' ' + className;
        node.setAttribute('role', 'status');
        node.textContent = message;
        container.insertBefore(node, container.firstChild);
        node.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    function notify(type, message) { alertIn(document.getElementById('main'), type, message, 'js-flash'); }
    function formAlert(form, type, message) { alertIn(form, type, message, 'form-alert'); }

    function groupOf(field) { return field.closest('.form-group') || field.parentElement; }

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

    /* ---- Validation ---- */
    function validateField(field) {
        if (field.disabled || field.type === 'hidden' || field.type === 'submit' || field.type === 'button') return '';
        var value = (field.value || '').trim();

        if (field.type === 'checkbox') return field.required && !field.checked ? 'Please tick this box to continue.' : '';
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
        if (field.hasAttribute('data-mapua-email') && ALLOWED_DOMAINS.indexOf((value.split('@')[1] || '').toLowerCase()) === -1) {
            return 'Use your Mapua email (@mymail.mapua.edu.ph or @mapua.edu.ph).';
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

    function validateForm(form) {
        var firstInvalid = null;
        form.querySelectorAll('input, select, textarea').forEach(function (field) {
            var message = validateField(field);
            message ? setError(field, message) : clearError(field);
            if (message && !firstInvalid) firstInvalid = field;
        });
        if (firstInvalid) firstInvalid.focus();
        return !firstInvalid;
    }

    document.querySelectorAll('form[data-validate]').forEach(function (form) {
        form.setAttribute('novalidate', '');
        form.addEventListener('input', function (event) {
            if (event.target.classList.contains('is-invalid')) {
                var message = validateField(event.target);
                message ? setError(event.target, message) : clearError(event.target);
            }
        });
    });

    /* ---- API-backed forms ---- */
    function fields(formData) {
        var data = {};
        formData.forEach(function (value, key) {
            if (!(value instanceof File)) data[key] = value;
        });
        return data;
    }

    function setBusy(form, busy) {
        form.querySelectorAll('button, select').forEach(function (el) { el.disabled = busy; });
    }

    function showApiError(form, error) {
        var errors = error.data && error.data.errors;
        if (errors) {
            Object.keys(errors).forEach(function (name) {
                var field = form.querySelector('[name="' + name + '"]');
                if (field) setError(field, errors[name]);
            });
        }
        formAlert(form, 'error', error.message);
    }

    var handlers = {
        add_item: function (form, data, formData) {
            return ClafsApi.addItem(form.dataset.type || 'lost', formData)
                .then(function (result) { window.location.href = result.url; });
        },
        update_status: function (form, data) {
            return ClafsApi.updateStatus(form.dataset.type || 'found', parseInt(data.id, 10), data.status)
                .then(function (result) {
                    notify('success', result.message);
                    setTimeout(function () { window.location.reload(); }, 600);
                });
        }
    };

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (form.hasAttribute('data-validate') && !validateForm(form)) {
            event.preventDefault();
            return;
        }
        if (form.hasAttribute('data-mock')) {
            event.preventDefault();
            formAlert(form, 'info', 'Looks good — this form passed validation. Saving is not connected yet.');
            return;
        }
        var handler = handlers[form.dataset.api];
        if (!handler || !window.ClafsApi) return;
        event.preventDefault();

        formAlert(form, '', '');
        var formData = new FormData(form); // before setBusy(): disabled fields are left out of FormData
        setBusy(form, true);
        handler(form, fields(formData), formData)
            .catch(function (error) {
                showApiError(form, error);
                setBusy(form, false);
            });
    });

    /* ---- Image preview ---- */
    document.querySelectorAll('input[type="file"][data-preview]').forEach(function (input) {
        var target = document.querySelector(input.dataset.preview);
        if (!target) return;
        var placeholder = target.innerHTML;
        var objectUrl = null;

        input.addEventListener('change', function () {
            if (objectUrl) URL.revokeObjectURL(objectUrl);
            objectUrl = null;
            var message = validateField(input);
            message ? setError(input, message) : clearError(input);

            var file = input.files && input.files[0];
            if (!file || message) {
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

    /* ---- Table filter ---- */
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
