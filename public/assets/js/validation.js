/*
 * Client-side validation for forms marked <form data-validate>.
 * This mirrors the rules the server will enforce; it is a convenience, not security.
 *
 * Supported field hints:
 *   required, minlength, maxlength, type="email", type="date" with max, type="file"
 *   data-mapua-email       → must end with an allowed Mapua domain
 *   data-match="password"  → must equal the field with that name
 *   data-min-words="5"     → at least N words (used for proof descriptions)
 *
 * Forms marked data-mock stop before submitting and show a notice — the backend
 * is not connected yet. Remove data-mock once handlers exist.
 */
(function () {
    var ALLOWED_DOMAINS = ['mymail.mapua.edu.ph', 'mapua.edu.ph'];
    var IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    var MAX_IMAGE_BYTES = 5 * 1024 * 1024;

    function errorNode(field) {
        var group = field.closest('.form-group') || field.parentElement;
        var node = group.querySelector('.form-error');
        if (!node) {
            node = document.createElement('span');
            node.className = 'form-error';
            node.setAttribute('aria-live', 'polite');
            group.appendChild(node);
        }
        return node;
    }

    function setError(field, message) {
        field.classList.add('is-invalid');
        field.setAttribute('aria-invalid', 'true');
        errorNode(field).textContent = message;
    }

    function clearError(field) {
        field.classList.remove('is-invalid');
        field.removeAttribute('aria-invalid');
        var group = field.closest('.form-group') || field.parentElement;
        var node = group.querySelector('.form-error');
        if (node) node.textContent = '';
    }

    function validateField(field) {
        if (field.disabled || field.type === 'hidden' || field.type === 'submit' || field.type === 'button') {
            return '';
        }
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

        if (field.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
            return 'Enter a valid email address.';
        }

        if (field.hasAttribute('data-mapua-email')) {
            var domain = value.split('@')[1] ? value.split('@')[1].toLowerCase() : '';
            if (ALLOWED_DOMAINS.indexOf(domain) === -1) {
                return 'Use your Mapua email (@mymail.mapua.edu.ph or @mapua.edu.ph).';
            }
        }

        if (field.dataset.match) {
            var other = field.form.querySelector('[name="' + field.dataset.match + '"]');
            if (other && other.value !== field.value) return 'Passwords do not match.';
        }

        if (field.type === 'date' && field.max && value > field.max) return 'Date cannot be in the future.';
        if (field.type === 'date' && field.min && value < field.min) return 'Date is too early.';

        var minWords = parseInt(field.dataset.minWords, 10);
        if (minWords && value.split(/\s+/).length < minWords) {
            return 'Please give a little more detail (at least ' + minWords + ' words).';
        }

        return '';
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
            var field = event.target;
            if (field.classList.contains('is-invalid')) {
                var message = validateField(field);
                message ? setError(field, message) : clearError(field);
            }
        });
        form.addEventListener('change', function (event) {
            var field = event.target;
            if (field.type === 'file' || field.tagName === 'SELECT') {
                var message = validateField(field);
                message ? setError(field, message) : clearError(field);
            }
        });
    });
})();
