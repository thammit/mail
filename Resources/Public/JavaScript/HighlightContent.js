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

});
