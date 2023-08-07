define([], function() {
    "use strict";
    const selector = '.mail-queue-table tbody tr:not(.table-success) .progress-bar';
    document.querySelectorAll(selector).forEach((runningProgressBar) => {
        const interval = setInterval(() => {
            window.fetch(TYPO3.settings.ajaxUrls.mail_queue_state + '&mail=' + parseInt(runningProgressBar.dataset.mailUid))
            .then((response) => {
                if (!response.ok) {
                    response.json().then((result) => { console.error(result);});
                    throw new Error(response.statusText);
                }
                return response.json();
            }).then((result) => {
                const finished = Boolean(result.finished);
                const numberOfSent = parseInt(result.numberOfSent);
                const numberOfRecipients = parseInt(runningProgressBar.getAttribute('aria-valuemax'));
                let percentOfSent = 100 / numberOfRecipients * numberOfSent;
                if (numberOfRecipients === 0) {
                    percentOfSent = 100;
                } else {
                    if (percentOfSent > 100) {
                        percentOfSent = 100;
                    }
                    if (percentOfSent < 0) {
                        percentOfSent = 0;
                    }
                }
                runningProgressBar.style.width = `${percentOfSent}%`;
                runningProgressBar.setAttribute('aria-valuenow', String(result.sent));
                runningProgressBar.innerText = `${result.sent}/${numberOfRecipients}`;
                if (finished || percentOfSent === 100) {
                    runningProgressBar.className = percentOfSent === 100 ? 'progress-bar bg-success' : 'progress-bar';
                    const mailDeleteButton = runningProgressBar.closest('tr').querySelector('.mail-delete-button');
                    if (finished && mailDeleteButton) {
                        mailDeleteButton.style.display = 'none';
                    }
                    clearInterval(interval);
                }
            });
        }, 60000);
    });
});
