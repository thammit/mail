define(['html2canvas'], function(html2canvas) {
    const previewBodyElement = document.getElementById('mail-wizard-preview-body');
    previewBodyElement.querySelectorAll('[data-mailer-ping]').forEach(element => {
        element.parentNode.removeChild(element);
    });
    html2canvas(previewBodyElement, { scale: 1, logging: true }).then(function(canvas) {
        window.fetch(TYPO3.settings.ajaxUrls.mail_save_preview_image, {
            method: 'POST',
            body: {
                mailUid: TYPO3.settings.Mail.mailUid,
                dataUrl: canvas.toDataURL('image/jpeg', 0.8)
            }
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
