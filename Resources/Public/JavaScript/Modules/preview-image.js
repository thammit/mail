import AjaxRequest from "@typo3/core/ajax/ajax-request.js";
import Notification from "@typo3/backend/notification.js";
import html2canvas from '@mediaessenz/mail/html2canvas.js';

class PreviewImage {
    constructor(mailUid) {
        const previewBodyElement = document.getElementById('mail-wizard-preview-body');
        previewBodyElement.querySelectorAll('[data-mailer-ping]').forEach(element => {
            element.parentNode.removeChild(element);
        });
        html2canvas(previewBodyElement, { scale: 1,logging: true }).then(function(canvas) {
            const request =  new  AjaxRequest(TYPO3.settings.ajaxUrls.mail_save_preview_image).post(
                    {
                        mailUid,
                        dataUrl: canvas.toDataURL('image/jpeg', 0.8)
                    },
                    {
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json'
                        }
                    }
            )
            request.then(async (response) => {
                return response.resolve('json');
            }).then((result) => {
                Notification.success(result.title, result.message);
            }).catch((response) => {
                response.resolve('json').then((result) => { Notification.error(result.title, result.message);});
            });
        });
    }
}

new PreviewImage(TYPO3.settings.Mail.mailUid);
