define([], function() {
    console.log('Highlight content');
    document.querySelectorAll('[data-content-id]').forEach((row) => {
        row.addEventListener('mouseover', (event) => {
            const row = event.target.closest('[data-content-id]');
            const contentElementUid = row.dataset.contentId;
            const contentElement = document.getElementById(contentElementUid);
            const originContainerBackgroundColor = contentElement.parentElement.style.backgroundColor;
            row.dataset.originBackgroundColor = originContainerBackgroundColor;
            contentElement.parentElement.style.backgroundColor = 'red';
        });
        row.addEventListener('mouseout', (event) => {
            const row = event.target.closest('[data-content-id]');
            const contentElementUid = row.dataset.contentId;
            const contentElement = document.getElementById(contentElementUid);
            contentElement.parentElement.style.backgroundColor = row.dataset.originBackgroundColor;
        });
    });

});
