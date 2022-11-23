define([], function() {
    document.querySelectorAll('.mail-content-category').forEach((element) => {
        element.addEventListener('change', (event) => {
            const categories = [...element.closest('[data-content-id]').querySelectorAll('.mail-content-category')].map((categoryCheckbox) => { return { category: parseInt(categoryCheckbox.dataset.category), checked: categoryCheckbox.checked }; });
            window.fetch(window.saveCategoryRestrictionsAjaxUri, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ content: parseInt(element.dataset.content), categories })
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
