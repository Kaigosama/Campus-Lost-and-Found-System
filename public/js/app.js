/*
 * CLAFS front-end behaviour. Requires api.js.
 *   [data-nav-toggle]                  mobile menu; .nav-group dropdowns close on outside click / Escape
 *   [data-confirm]                     confirm() before links, buttons and forms
 *   form[data-validate]                client-side rules mirroring the API (a convenience, not security)
 *   form[data-api="…"]                 submitted through ClafsApi with fetch(); see `handlers` for the names
 *     data-done="notify|replace|remove"  what to do with the form after success (default: notify)
 *   [data-status-for="found-3"]        badge that is updated in place after a status change
 *   form[data-live-search]             found-items search: results are fetched from api/get_items.php as you type
 *   [data-filter-toggle]               "More filters" button that collapses .filter-extra groups on phones
 *   .form-group .form-hint / errors    linked to their field with aria-describedby
 *   [data-next-holiday]                filled with the next office closure from the public-holiday API
 *   input[type=file][data-preview]     image preview
 *   [data-table-filter]                quick text filter for a table
 */
(function () {
    var ALLOWED_DOMAINS = ['mymail.mapua.edu.ph', 'mapua.edu.ph'];
    var IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    var MAX_IMAGE_BYTES = 5 * 1024 * 1024;
    var BASE = document.documentElement.getAttribute('data-base') || '';
    var CONFIG_NODE = document.getElementById('clafs-config');   // JSON data block from footer.php (CSP forbids inline scripts)
    var STATUS_LABELS = (CONFIG_NODE && JSON.parse(CONFIG_NODE.textContent).statusLabels) || {};
    var REDUCED_MOTION = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function formatDate(iso) {
        if (!iso) return '—';
        return new Date(iso + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }

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
    function alertNode(type, message) {
        var node = document.createElement('div');
        node.className = 'alert alert-' + type;
        node.setAttribute('role', 'status');
        node.textContent = message;
        return node;
    }
    function alertIn(container, type, message, className) {
        if (!container) return;
        var existing = container.querySelector('.' + className);
        if (existing) existing.remove();
        if (!message) return;
        var node = alertNode(type, message);
        node.classList.add(className);
        container.insertBefore(node, container.firstChild);
        node.scrollIntoView({ behavior: REDUCED_MOTION ? 'auto' : 'smooth', block: 'nearest' });
    }
    function notify(type, message) { alertIn(document.getElementById('main'), type, message, 'js-flash'); }
    function formAlert(form, type, message) { alertIn(form, type, message, 'form-alert'); }

    function groupOf(field) { return field.closest('.form-group') || field.parentElement; }

    // Add an id to a field's aria-describedby so screen readers read that hint or error on focus.
    function describeBy(field, node, suffix) {
        if (!node.id) node.id = (field.id || field.name) + '-' + suffix;
        var ids = (field.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
        if (ids.indexOf(node.id) === -1) field.setAttribute('aria-describedby', ids.concat(node.id).join(' '));
    }

    // Link every static hint (e.g. "JPG, PNG or WEBP…") and server-rendered error to the field in its group.
    document.querySelectorAll('.form-group span.form-hint, .form-group .form-error').forEach(function (node) {
        var field = groupOf(node).querySelector('input:not([type="hidden"]), select, textarea');
        if (field) describeBy(field, node, node.classList.contains('form-error') ? 'error' : 'hint');
    });

    function setError(field, message) {
        var group = groupOf(field);
        var node = group.querySelector('.form-error');
        if (!node) {
            node = document.createElement('span');
            node.className = 'form-error';
            node.setAttribute('aria-live', 'polite');
            group.appendChild(node);
        }
        describeBy(field, node, 'error');
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

    /* ---- Password policy (same rules as password_error() in src/validation.php) ---- */
    var PASSWORD_MIN = 8;
    var PASSWORD_RULES = [
        { test: function (v) { return v.length >= PASSWORD_MIN; }, label: 'At least ' + PASSWORD_MIN + ' characters', error: 'Password must be at least ' + PASSWORD_MIN + ' characters.' },
        { test: function (v) { return /[a-z]/.test(v); }, label: 'Contains a lowercase letter', error: 'Password must contain at least one lowercase letter.' },
        { test: function (v) { return /[A-Z]/.test(v); }, label: 'Contains an uppercase letter', error: 'Password must contain at least one uppercase letter.' },
        { test: function (v) { return /[0-9]/.test(v); }, label: 'Contains a number', error: 'Password must contain at least one number.' },
        { test: function (v) { return /[^A-Za-z0-9]/.test(v); }, label: 'Contains a special character', error: 'Password must contain at least one special character.' }
    ];
    function passwordError(value) {
        for (var i = 0; i < PASSWORD_RULES.length; i++) {
            if (!PASSWORD_RULES[i].test(value)) return PASSWORD_RULES[i].error;
        }
        return value.length > 72 ? 'Password must be 72 characters or fewer.' : '';
    }

    // Live checklist and Weak / Moderate / Strong label under input[data-password-policy]. A UI aid only:
    // the server rejects any password that misses a rule.
    document.querySelectorAll('input[data-password-policy]').forEach(function (input) {
        var meter = document.createElement('div');
        meter.className = 'password-meter';
        meter.id = input.id + '-meter';
        meter.innerHTML = '<div class="password-strength"><span>Password strength: <span class="strength-label" aria-live="polite">—</span></span>'
            + '<span class="strength-bar" aria-hidden="true"><span></span></span></div>'
            + '<ul class="password-rules">' + PASSWORD_RULES.map(function (rule) {
                return '<li><span class="sr-only">Not met: </span>' + escapeHtml(rule.label) + '</li>';
            }).join('') + '</ul>';
        groupOf(input).insertBefore(meter, input.nextSibling);
        describeBy(input, meter, 'meter');
        var items = meter.querySelectorAll('li');

        function update() {
            var met = 0;
            PASSWORD_RULES.forEach(function (rule, i) {
                var ok = rule.test(input.value);
                if (ok) met++;
                items[i].classList.toggle('met', ok);
                items[i].firstChild.textContent = ok ? 'Met: ' : 'Not met: ';
            });
            var level = !input.value ? '' : met <= 2 ? 'weak' : met < PASSWORD_RULES.length ? 'moderate' : 'strong';
            meter.className = 'password-meter' + (level ? ' strength-' + level : '');
            meter.querySelector('.strength-label').textContent = level ? level.charAt(0).toUpperCase() + level.slice(1) : '—';
        }
        input.addEventListener('input', update);
        update();
    });

    // Confirm-password fields must be typed: block paste, cut and drag-and-drop. UX only, not a security control.
    document.querySelectorAll('input[data-no-paste]').forEach(function (input) {
        ['paste', 'cut', 'drop'].forEach(function (type) {
            input.addEventListener(type, function (event) {
                event.preventDefault();
                setError(input, 'Please type your password again instead of pasting it.');
            });
        });
    });

    /* ---- Validation ---- */
    function labelOf(field) { return field.dataset.label || ''; }

    function validateField(field) {
        if (field.disabled || field.type === 'hidden' || field.type === 'submit' || field.type === 'button') return '';
        var value = (field.value || '').trim();
        var label = labelOf(field);

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
        if (field.required && !value) return label ? label + ' is required.' : 'This field is required.';
        if (!value) return '';

        if (field.hasAttribute('data-password-policy')) return passwordError(field.value);
        var minLength = parseInt(field.getAttribute('minlength'), 10);
        if (minLength && value.length < minLength) {
            return label ? label + ' must contain at least ' + minLength + ' characters.' : 'Must be at least ' + minLength + ' characters.';
        }
        var maxLength = parseInt(field.getAttribute('maxlength'), 10);
        if (maxLength && value.length > maxLength) return 'Must be ' + maxLength + ' characters or fewer.';
        if (field.hasAttribute('data-name') && !/^\p{L}[\p{L}\p{M} .'\-]*$/u.test(value)) {
            return 'Use letters only (spaces, hyphens, apostrophes and periods are allowed).';
        }
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

    function check(field) {
        var message = validateField(field);
        message ? setError(field, message) : clearError(field);
    }

    document.querySelectorAll('form[data-validate]').forEach(function (form) {
        form.setAttribute('novalidate', '');
        // Leaving a field (Tab, click elsewhere) validates it; focus is never trapped in an invalid field.
        form.addEventListener('focusout', function (event) {
            if (event.target.matches('input, select, textarea')) check(event.target);
        });
        form.addEventListener('input', function (event) {
            var field = event.target;
            if (field.classList.contains('is-invalid')) check(field);
            // Re-check "confirm password" as soon as either password changes, once something is typed there.
            var confirm = field.dataset.match ? field : form.querySelector('[data-match="' + field.name + '"]');
            if (confirm && confirm.value) check(confirm);
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
        form.setAttribute('aria-busy', busy ? 'true' : 'false');
    }

    function showApiError(form, error) {
        var errors = error.data && error.data.errors;
        if (errors) {
            Object.keys(errors).forEach(function (name) {
                var field = form.querySelector('[name="' + name + '"]');
                if (field) setError(field, errors[name]);
            });
        }
        // Inline forms (a status <select> in a table cell) have nowhere to show a message; use the page banner.
        form.querySelector('.form-group') ? formAlert(form, 'error', error.message) : notify('error', error.message);
    }

    // Update every badge that shows this record's status, e.g. setBadge('found-3', 'returned').
    function setBadge(key, status) {
        document.querySelectorAll('[data-status-for="' + key + '"]').forEach(function (badge) {
            badge.className = 'badge badge-' + status;
            badge.textContent = STATUS_LABELS[status] || status;
        });
    }

    // After success: data-done="replace" swaps the form (or its closest [data-replace]) for a success alert,
    // "remove" deletes it (or its closest [data-remove]), anything else shows a page banner.
    function finish(form, message) {
        var mode = form.dataset.done || 'notify';
        if (mode === 'replace') {
            (form.closest('[data-replace]') || form).replaceWith(alertNode('success', message));
        } else if (mode === 'remove') {
            (form.closest('[data-remove]') || form).remove();
            notify('success', message);
        } else {
            notify('success', message);
        }
    }

    var handlers = {
        add_item: function (form, data, formData) {
            return ClafsApi.addItem(form.dataset.type || 'lost', formData)
                .then(function (result) { window.location.href = result.url; });
        },
        update_item: function (form, data, formData) {
            return ClafsApi.updateItem(form.dataset.type || 'lost', parseInt(data.id, 10), formData)
                .then(function (result) { window.location.href = result.url; });
        },
        update_status: function (form, data) {
            var type = form.dataset.type || 'found';
            return ClafsApi.updateStatus(type, parseInt(data.id, 10), data.status, data.item_id)
                .then(function (result) {
                    setBadge(type + '-' + result.id, result.status);
                    (result.rejected_claims || []).forEach(function (claimId) { setBadge('claim-' + claimId, 'rejected'); });
                    finish(form, result.message);
                });
        },
        claim: function (form, data) {
            return ClafsApi.createClaim(data).then(function (result) { finish(form, result.message); });
        },
        moderate: function (form, data) {
            return ClafsApi.moderateItem(parseInt(data.id, 10), data.moderation, data.review_note, data.storage_location)
                .then(function (result) { finish(form, result.message); });
        },
        review: function (form, data) {
            return ClafsApi.reviewClaim(parseInt(data.claim_id, 10), data.decision, data.review_note)
                .then(function (result) {
                    setBadge('claim-' + result.claim_id, result.status);
                    var handover = document.querySelector('[data-handover="' + result.claim_id + '"]');
                    if (handover && result.status === 'approved') handover.hidden = false;
                    finish(form, result.message);
                });
        },
        withdraw: function (form, data) {
            return ClafsApi.withdrawClaim(parseInt(data.claim_id, 10))
                .then(function (result) { finish(form, result.message); });
        },
        update_user: function (form, data) {
            var changes = {};
            if ('role' in data) changes.role = data.role;
            if ('is_active' in data) changes.is_active = data.is_active;
            if ('unlock' in data) changes.unlock = data.unlock;
            return ClafsApi.updateUser(parseInt(data.user_id, 10), changes).then(function (result) {
                setBadge('user-' + result.user_id, result.is_active ? 'active' : 'inactive');
                if (!result.locked) {
                    var lockBadge = document.querySelector('[data-locked-for="' + result.user_id + '"]');
                    if (lockBadge) lockBadge.remove();
                }
                var toggle = form.querySelector('[data-toggle-active]');
                if (toggle) { // flip the Deactivate / Reactivate button so it can be used again without a reload
                    var active = !!result.is_active;
                    form.querySelector('[name="is_active"]').value = active ? '0' : '1';
                    toggle.textContent = active ? 'Deactivate' : 'Reactivate';
                    toggle.className = 'btn btn-sm ' + (active ? 'btn-secondary' : 'btn-success');
                    form.dataset.confirm = active ? 'Deactivate this account? They will no longer be able to log in.' : 'Reactivate this account?';
                }
                finish(form, result.message);
            });
        }
    };

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (form.hasAttribute('data-validate') && !validateForm(form)) {
            event.preventDefault();
            return;
        }
        var handler = handlers[form.dataset.api];
        if (!handler || !window.ClafsApi) return;
        event.preventDefault();

        formAlert(form, '', '');
        var formData = new FormData(form); // before setBusy(): disabled fields are left out of FormData
        if (event.submitter && event.submitter.name) formData.append(event.submitter.name, event.submitter.value);
        setBusy(form, true);
        handler(form, fields(formData), formData)
            .then(function () { if (document.body.contains(form)) setBusy(form, false); })
            .catch(function (error) {
                showApiError(form, error);
                setBusy(form, false);
            });
    });

    /* ---- Live search (found items) ---- */
    var liveForm = document.querySelector('form[data-live-search]');
    var liveResults = document.querySelector('[data-live-results]');
    if (liveForm && liveResults && window.ClafsApi) {
        var liveCount = document.querySelector('[data-live-count]');
        var liveTimer = null;
        var liveSeq = 0;

        function liveParams() {
            var params = {};
            new FormData(liveForm).forEach(function (value, key) { if (value !== '') params[key] = value; });
            return params;
        }

        function cardHtml(item) {
            var href = BASE + '/view_item.php?type=found&id=' + item.item_id;
            var photo = item.image_url
                ? '<img src="' + escapeHtml(BASE + '/' + item.image_url) + '" alt="' + escapeHtml(item.item_name) + '" class="photo" loading="lazy">'
                : '<div class="photo photo-placeholder" role="img" aria-label="No photo available">'
                  + '<svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">'
                  + '<path d="M4 7h3l2-2h6l2 2h3v12H4z"/><circle cx="12" cy="13" r="3.5"/></svg><span>No photo</span></div>';
            var desc = String(item.description || '').replace(/\s+/g, ' ').trim();
            if (desc.length > 110) desc = desc.slice(0, 109) + '…';
            return '<article class="item-card">'
                + '<div class="item-card-photo">' + photo + '</div>'
                + '<div class="item-card-body">'
                + '<span class="item-card-category">' + escapeHtml(item.category) + '</span>'
                + '<h3 class="item-card-title"><a href="' + escapeHtml(href) + '">' + escapeHtml(item.item_name) + '</a></h3>'
                + '<p class="item-card-desc">' + escapeHtml(desc) + '</p>'
                + '<dl class="item-card-meta"><div><dt>Found</dt><dd>' + escapeHtml(formatDate(item.date_found)) + '</dd></div>'
                + '<div><dt>Where</dt><dd>' + escapeHtml(item.location_found) + '</dd></div></dl>'
                + '</div>'
                + '<div class="item-card-footer"><span class="badge badge-' + escapeHtml(item.status) + '">' + escapeHtml(STATUS_LABELS[item.status] || item.status) + '</span>'
                + '<span class="btn btn-outline btn-sm" aria-hidden="true">View details</span></div>'
                + '</article>';
        }

        function renderResults(items, query) {
            if (liveCount) {
                liveCount.textContent = items.length + ' item' + (items.length === 1 ? '' : 's') + ' in storage' + (query ? ' matching "' + query + '"' : '');
            }
            if (!items.length) {
                liveResults.innerHTML = '<div class="empty-state"><div class="empty-icon" aria-hidden="true">&#128269;</div>'
                    + '<h2>No items match your search</h2><p>Try a different keyword or clear the filters.</p></div>';
                return;
            }
            liveResults.innerHTML = '<div class="item-grid">' + items.map(cardHtml).join('') + '</div>';
        }

        function runSearch() {
            var params = liveParams();
            var mine = ++liveSeq;
            liveResults.setAttribute('aria-busy', 'true');
            ClafsApi.getItems(Object.assign({ type: 'found', limit: 100 }, params))
                .then(function (result) {
                    if (mine !== liveSeq) return; // a newer search has started
                    renderResults(result.items, params.q || '');
                    var qs = new URLSearchParams(params).toString();
                    window.history.replaceState(null, '', liveForm.getAttribute('action') + (qs ? '?' + qs : ''));
                })
                .catch(function (error) { notify('error', 'Search failed: ' + error.message); })
                .then(function () { if (mine === liveSeq) liveResults.removeAttribute('aria-busy'); });
        }

        liveForm.addEventListener('input', function (event) {
            if (event.target.type !== 'search') return;
            clearTimeout(liveTimer);
            liveTimer = setTimeout(runSearch, 300);
        });
        liveForm.addEventListener('change', function () { clearTimeout(liveTimer); runSearch(); });
        liveForm.addEventListener('submit', function (event) { event.preventDefault(); clearTimeout(liveTimer); runSearch(); });
    }

    /* ---- "More filters" toggle: on phones, hide the secondary filters unless one is in use ---- */
    document.querySelectorAll('[data-filter-toggle]').forEach(function (button) {
        var bar = button.closest('.filter-bar');
        var extras = bar.querySelectorAll('.filter-extra');
        if (!extras.length) return;
        extras.forEach(function (group, i) { group.id = group.id || 'filter-extra-' + i; });
        button.setAttribute('aria-controls', Array.prototype.map.call(extras, function (g) { return g.id; }).join(' '));

        var inUse = Array.prototype.some.call(bar.querySelectorAll('.filter-extra input, .filter-extra select'), function (f) {
            return f.value !== '' && !(f.name === 'sort' && f.value === 'newest');
        });
        function set(open) {
            bar.classList.toggle('is-collapsed', !open);
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
            button.textContent = open ? 'Fewer filters' : 'More filters';
        }
        set(inUse);
        button.hidden = false;
        button.addEventListener('click', function () { set(bar.classList.contains('is-collapsed')); });
    });

    /* ---- Next office closure (external public-holiday API) ---- */
    var holidayNodes = document.querySelectorAll('[data-next-holiday]');
    if (holidayNodes.length && window.ClafsApi) {
        var today = new Date();
        var todayIso = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-' + String(today.getDate()).padStart(2, '0');
        var year = today.getFullYear();

        ClafsApi.getHolidays(year)
            .then(function (holidays) {
                var upcoming = holidays.filter(function (h) { return h.date >= todayIso; });
                // Late in the year the remaining holidays may all be in the next one.
                return upcoming.length ? upcoming : ClafsApi.getHolidays(year + 1);
            })
            .then(function (upcoming) {
                var next = upcoming[0];
                holidayNodes.forEach(function (node) {
                    if (!next) { node.textContent = 'No upcoming holidays on record.'; return; }
                    node.innerHTML = 'Next closure: <strong>' + escapeHtml(formatDate(next.date)) + '</strong> — ' + escapeHtml(next.localName || next.name);
                    if (node.dataset.nextHoliday === 'list') {
                        node.innerHTML = '<strong>Upcoming holidays (office closed):</strong> ' + upcoming.slice(0, 3).map(function (h) {
                            return escapeHtml(formatDate(h.date)) + ' (' + escapeHtml(h.localName || h.name) + ')';
                        }).join(' · ');
                    }
                });
            })
            .catch(function () {
                holidayNodes.forEach(function (node) { node.textContent = 'Holiday schedule unavailable right now.'; });
            });
    }

    /* ---- Lightbox: click a photo to enlarge it (skipped when the photo is itself a link) ---- */
    var lightbox = null;
    function openLightbox(img) {
        if (!lightbox) {
            lightbox = document.createElement('dialog');
            lightbox.className = 'lightbox';
            lightbox.innerHTML = '<button type="button" class="lightbox-close" aria-label="Close">&times;</button>'
                + '<img alt=""><p class="lightbox-caption"></p>';
            document.body.appendChild(lightbox);
            lightbox.querySelector('.lightbox-close').addEventListener('click', function () { lightbox.close(); });
            lightbox.addEventListener('click', function (event) { if (event.target === lightbox) lightbox.close(); }); // backdrop
        }
        var full = lightbox.querySelector('img');
        full.src = img.currentSrc || img.src;
        full.alt = img.alt;
        lightbox.querySelector('.lightbox-caption').textContent = img.alt;
        lightbox.showModal();
    }
    document.addEventListener('click', function (event) {
        var img = event.target.closest('img[data-lightbox]');
        if (img && !img.closest('a') && typeof HTMLDialogElement === 'function') {
            event.preventDefault();
            openLightbox(img);
        }
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
            img.setAttribute('data-lightbox', '');
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

    /* ---- Cookie consent: the choice lives in localStorage (it is not itself a cookie) ---- */
    var CONSENT_KEY = 'clafs-cookie-consent';
    var banner = document.querySelector('[data-cookie-banner]');
    function readConsent() { try { return localStorage.getItem(CONSENT_KEY); } catch (e) { return null; } }
    function applyConsent(choice) {
        // Logging in sets the necessary session cookie, so after "Reject" the log-in form explains that instead of submitting.
        document.querySelectorAll('form[data-needs-cookies]').forEach(function (form) {
            var note = form.querySelector('.js-cookie-note');
            var button = form.querySelector('button[type="submit"]');
            if (choice === 'rejected') {
                if (!note) {
                    note = alertNode('warning', 'You rejected cookies. Logging in needs one necessary session cookie, so accept cookies to log in.');
                    note.classList.add('js-cookie-note');
                    var accept = document.createElement('button');
                    accept.type = 'button';
                    accept.className = 'btn btn-secondary btn-sm mt-1';
                    accept.textContent = 'Accept necessary cookie';
                    accept.setAttribute('data-cookie-choice', 'accepted');
                    note.appendChild(document.createElement('br'));
                    note.appendChild(accept);
                    form.insertBefore(note, form.firstChild);
                }
                button.disabled = true;
            } else {
                if (note) note.remove();
                button.disabled = false;
            }
        });
    }
    if (banner) {
        var consent = readConsent();
        banner.hidden = !!consent;
        applyConsent(consent);
        document.addEventListener('click', function (event) {
            var choice = event.target.closest('[data-cookie-choice]');
            if (choice) {
                try { localStorage.setItem(CONSENT_KEY, choice.dataset.cookieChoice); } catch (e) { /* private mode: ask again next visit */ }
                banner.hidden = true;
                applyConsent(choice.dataset.cookieChoice);
            } else if (event.target.closest('[data-cookie-settings]')) {
                banner.hidden = false;
                banner.querySelector('[data-cookie-choice]').focus();
            }
        });
    }

    /* ---- Bar widths (CSP blocks inline style attributes) ---- */
    document.querySelectorAll('[data-width]').forEach(function (bar) {
        bar.style.width = bar.dataset.width + '%';
    });
})();
