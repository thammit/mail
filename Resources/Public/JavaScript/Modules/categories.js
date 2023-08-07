import AjaxRequest from "@typo3/core/ajax/ajax-request.js";
import Notification from "@typo3/backend/notification.js";

class Categories {
        constructor( mailUid) {
            const mailWizardPreview = document.getElementById('mail-wizard-preview');
            document.querySelectorAll('[data-content-id]').forEach((row) => {
                row.addEventListener('mouseover', (event) => {
                    const row = event.target.closest('[data-content-id]');
                    const contentElementUid = row.dataset.contentId;
                    const contentElement = document.getElementById(contentElementUid);
                    if (contentElement) {
                        const originContainerBackgroundColor = contentElement.parentElement.style.backgroundColor;
                        row.dataset.originBackgroundColor = originContainerBackgroundColor;
                        contentElement.parentElement.style.backgroundColor = '#ea7676';
                        mailWizardPreview.scrollTop = contentElement.offsetTop - 20;
                }
                });
                row.addEventListener('mouseout', (event) => {
                    const row = event.target.closest('[data-content-id]');
                    const contentElementUid = row.dataset.contentId;
                    const contentElement = document.getElementById(contentElementUid);
                    if (contentElement) {
                    contentElement.parentElement.style.backgroundColor = row.dataset.originBackgroundColor;
                    }
                });
            });
            document.querySelectorAll('.mail-content-category').forEach((element) => {
                element.addEventListener('change', (event) => {
                    const categories = [...element.closest('[data-content-id]').querySelectorAll('.mail-content-category')].map((categoryCheckbox) => { return { category: parseInt(categoryCheckbox.dataset.category), checked: categoryCheckbox.checked }; });
                                        const request =  new  AjaxRequest(TYPO3.settings.ajaxUrls.mail_save_category_restrictions).post(
                                                {
                                                    mailUid,
                                                    content: parseInt(element.dataset.content),
                                                    categories
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
            });
        }
}

new Categories(window.mailUid);
