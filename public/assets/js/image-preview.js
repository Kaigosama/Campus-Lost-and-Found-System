/*
 * Shows the chosen image inside a preview box before upload.
 * Usage: <input type="file" data-preview="#photoPreview">  <div id="photoPreview" class="photo-preview">…</div>
 */
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
