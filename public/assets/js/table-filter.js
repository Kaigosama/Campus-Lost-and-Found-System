/*
 * Instant text filter for tables already on the page (staff/admin management screens).
 * Usage: <input type="search" data-table-filter="#itemsTable">
 *        <table id="itemsTable">…</table>
 *        <p data-filter-empty hidden>No matches.</p>   (optional)
 */
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
