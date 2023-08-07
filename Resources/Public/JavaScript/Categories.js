define([], function() {
    const mailWizardPreview = document.getElementById('mail-wizard-preview');
    document.querySelectorAll('[data-content-id]').forEach((row) => {
        row.addEventListener('mouseover', (event) => {
            const row = event.target.closest('[data-content-id]');
            const contentElementUid = row.dataset.contentId;
            const contentElement = document.getElementById(contentElementUid);
            const originContainerBackgroundColor = contentElement.parentElement.style.backgroundColor;
            row.dataset.originBackgroundColor = originContainerBackgroundColor;
            contentElement.parentElement.style.backgroundColor = '#ea7676';
            mailWizardPreview.scrollTop = contentElement.offsetTop - 20;
        });
        row.addEventListener('mouseout', (event) => {
            const row = event.target.closest('[data-content-id]');
            const contentElementUid = row.dataset.contentId;
            const contentElement = document.getElementById(contentElementUid);
            contentElement.parentElement.style.backgroundColor = row.dataset.originBackgroundColor;
        });
    });
    document.querySelectorAll('.mail-content-category').forEach((element) => {
        element.addEventListener('change', (event) => {
            const categories = [...element.closest('[data-content-id]').querySelectorAll('.mail-content-category')].map((categoryCheckbox) => { return { category: parseInt(categoryCheckbox.dataset.category), checked: categoryCheckbox.checked }; });
            window.fetch(TYPO3.settings.ajaxUrls.mail_save_category_restrictions, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    mailUid: window.mailUid,
                    content: parseInt(element.dataset.content),
                    ategories
                })
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
});
