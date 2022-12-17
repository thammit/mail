define([], function() {
    "use strict";
    const previewModals = document.getElementsByClassName('js-mail-preview-modal');
    const previewModalIframe = document.getElementById('previewModal').getElementsByTagName('iframe');
    const previewModalTitle = document.getElementById('previewModal').getElementsByClassName('modal-title');
    [...previewModals].forEach((element) => {
        element.addEventListener('click', (event) => {
            event.preventDefault();
            previewModalIframe[0].setAttribute('src', element.getAttribute('href'));
            previewModalTitle[0].innerHTML = element.dataset.modalTitle;
        });
    });
});
