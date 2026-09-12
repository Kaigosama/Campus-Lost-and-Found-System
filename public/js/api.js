/*
 * Centralised fetch() wrappers for the api/ endpoints. Loaded before app.js.
 *
 *   ClafsApi.getItems({ type: 'found', q: 'bag', category: 'Bags' })   → { ok, type, count, items }
 *   ClafsApi.addItem('lost', { item_name, category, date_lost, location_lost, description })
 *   ClafsApi.updateStatus('found', 3, 'returned')
 *
 * Every call resolves with the decoded JSON body, or rejects with an Error whose
 * .status is the HTTP code and .data the body (e.g. .data.errors for 422).
 */
window.ClafsApi = (function () {
    var base = (document.documentElement.getAttribute('data-base') || '') + '/api/';

    function request(endpoint, options) {
        options = options || {};
        var url = base + endpoint;
        var init = {
            method: options.method || 'GET',
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        };

        if (options.query) {
            var params = new URLSearchParams();
            Object.keys(options.query).forEach(function (key) {
                var value = options.query[key];
                if (value !== undefined && value !== null && value !== '') params.append(key, value);
            });
            var qs = params.toString();
            if (qs) url += '?' + qs;
        }

        if (options.body) {
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

    return {
        getItems: function (params) {
            return request('get_items.php', { query: params });
        },
        addItem: function (type, fields) {
            return request('add_item.php', { method: 'POST', body: Object.assign({ type: type }, fields) });
        },
        updateStatus: function (type, id, status) {
            return request('update_status.php', { method: 'POST', body: { type: type, id: id, status: status } });
        }
    };
})();
