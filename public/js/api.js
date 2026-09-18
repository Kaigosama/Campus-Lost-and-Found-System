/*
 * fetch() wrappers for api/ (the CLAFS JSON API) and for the external public-holiday API.
 * Each call resolves with the JSON body, or rejects with an Error carrying .status (HTTP code)
 * and .data (body, e.g. .data.errors for 422).
 */
window.ClafsApi = (function () {
    var base = (document.documentElement.getAttribute('data-base') || '') + '/api/';

    // Nager.Date — free public-holiday API, no key needed. https://date.nager.at/Api
    var HOLIDAY_API = 'https://date.nager.at/api/v3/PublicHolidays/';

    function request(url, options) {
        options = options || {};
        var init = { method: options.method || 'GET', headers: { 'Accept': 'application/json' }, credentials: options.credentials || 'same-origin' };

        if (options.query) {
            var params = new URLSearchParams();
            Object.keys(options.query).forEach(function (key) {
                var value = options.query[key];
                if (value !== undefined && value !== null && value !== '') params.append(key, value);
            });
            var qs = params.toString();
            if (qs) url += '?' + qs;
        }
        if (options.body instanceof FormData) {
            init.body = options.body;
        } else if (options.body) {
            init.headers['Content-Type'] = 'application/json';
            init.body = JSON.stringify(options.body);
        }

        return fetch(url, init).then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (data) {
                if (!response.ok || data.ok === false) {
                    var error = new Error(data.error || ('Request failed (' + response.status + ')'));
                    error.status = response.status;
                    error.data = data;
                    throw error;
                }
                return data;
            });
        });
    }

    function get(endpoint, query) {
        return request(base + endpoint, { query: query });
    }
    function post(endpoint, body) {
        return request(base + endpoint, { method: 'POST', body: body });
    }

    // Adds `type` to a FormData (multipart, carries the "photo" file) or a plain object.
    function withType(type, fields, extra) {
        extra = extra || {};
        if (fields instanceof FormData) {
            fields.set('type', type);
            Object.keys(extra).forEach(function (key) { fields.set(key, extra[key]); });
            return fields;
        }
        return Object.assign({ type: type }, fields, extra);
    }

    return {
        /* ---- CLAFS JSON API (api/) ---- */
        getItems:     function (params)                   { return get('get_items.php', params); },
        addItem:      function (type, fields)             { return post('add_item.php', withType(type, fields)); },
        updateItem:   function (type, id, fields)         { return post('update_item.php', withType(type, fields, { id: id })); },
        updateStatus: function (type, id, status, itemId) { return post('update_status.php', { type: type, id: id, status: status, item_id: itemId }); },
        createClaim:  function (fields)                   { return post('claims.php', Object.assign({ action: 'create' }, fields)); },
        reviewClaim:  function (claimId, decision, note)  { return post('claims.php', { action: 'review', claim_id: claimId, decision: decision, review_note: note }); },
        withdrawClaim: function (claimId)                 { return post('claims.php', { action: 'withdraw', claim_id: claimId }); },
        updateUser:   function (userId, changes)          { return post('update_user.php', Object.assign({ user_id: userId }, changes)); },

        /* ---- External API: Philippine public holidays (office closures) ---- */
        // Resolves with [{ date: 'YYYY-MM-DD', name, localName }, …] for the year, sorted by date.
        getHolidays: function (year) {
            return request(HOLIDAY_API + year + '/PH', { credentials: 'omit' });
        }
    };
})();
