define(['html2canvas'], function(html2canvas) {
    html2canvas(document.querySelector('#mail-wizard-preview-body'), { scale: 1, logging: true }).then(function(canvas) {
        window.fetch(window.savePreviewImageAjaxUri, {
            method: 'POST',
            body: canvas.toDataURL('image/jpeg', 0.8)
        }).then((response) => {
            if (!response.ok) {
                response.json().then((result) => { top.TYPO3.Notification.error(result.title, result.message);});
                throw new Error(response.statusText);
            }
            return response.json();
        }).then((result) => {
            top.TYPO3.Notification.success(result.title, result.message);
        });
    });
});
