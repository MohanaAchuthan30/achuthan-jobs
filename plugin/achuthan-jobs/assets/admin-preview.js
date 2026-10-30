/* Achuthan Jobs: access-controlled document preview. This only controls UI; WordPress performs auth and validation. */
(function () {
    'use strict';
    const viewer = document.getElementById('aj-document-preview');
    if (!viewer) return;
    const frame = document.getElementById('aj-private-document-frame');
    const label = document.getElementById('aj-preview-filename');
    let lastButton = null;
    function closePreview() {
        viewer.hidden = true;
        document.body.classList.remove('aj-preview-active');
        // Clear the private document when closing the viewer.
        frame.setAttribute('src', 'about:blank');
        if (lastButton) lastButton.focus();
    }
    document.addEventListener('submit', function (event) {
        const form = event.target.closest('.aj-preview-form');
        if (!form) return;
        lastButton = event.submitter || form.querySelector('button');
        label.textContent = (lastButton && lastButton.getAttribute('data-aj-filename')) || 'Private document';
        frame.name = 'aj-private-document-frame';
        viewer.hidden = false;
        document.body.classList.add('aj-preview-active');
        viewer.querySelector('[data-aj-preview-close]').focus();
        // Do not prevent submit: POST is sent directly to iframe with WP nonce.
    });
    viewer.addEventListener('click', function (event) {
        if (event.target.closest('[data-aj-preview-close]')) closePreview();
    });
    document.addEventListener('keydown', function (event) {
        if (!viewer.hidden && event.key === 'Escape') closePreview();
    });
})();
