/*
 * fetch() wrappers for api/. Each call resolves with the JSON body, or rejects with an
 * Error carrying .status (HTTP code) and .data (body, e.g. .data.errors for 422).
 */
window.ClafsApi = (function () {
    var base = (document.documentElement.getAttribute('data-base') || '') + '/api/';

    function request(endpoint, options) {
        options = options || {};
        var url = base + endpoint;
        var init = { method: options.method || 'GET', headers: { 'Accept': 'application/json' }, credentials: 'same-origin' };

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

    function post(endpoint, body) {
        return request(endpoint, { method: 'POST', body: body });
    }

    return {
        getItems:     function (params)           { return request('get_items.php', { query: params }); },
        updateStatus: function (type, id, status) { return post('update_status.php', { type: type, id: id, status: status }); },
        // fields: a plain object, or a FormData (multipart — carries the "photo" file)
        addItem: function (type, fields) {
            if (fields instanceof FormData) {
                fields.set('type', type);
                return post('add_item.php', fields);
            }
            return post('add_item.php', Object.assign({ type: type }, fields));
        }
    };
})();
