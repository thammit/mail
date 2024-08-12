define([], function () {
    "use strict";
    const selector = '.mail-queue-table tbody tr.table-info .progress-bar';
    document.querySelectorAll(selector).forEach((runningProgressBar) => {
        const interval = setInterval(() => {
            window.fetch(TYPO3.settings.ajaxUrls.mail_queue_state + '&mail=' + parseInt(runningProgressBar.dataset.mailUid))
            .then((response) => {
                if (!response.ok) {
                    response.json().then((result) => {
                        console.error(result);
                    });
                    throw new Error(response.statusText);
                }
                return response.json();
            }).then((result) => {
                const status = Number(result.status);
                const recipientsHandled = parseInt(result.recipientsHandled);
                const deliveryProgress = parseInt(result.deliveryProgress);
                const numberOfRecipients = parseInt(runningProgressBar.getAttribute('aria-valuemax'));
                runningProgressBar.style.width = `${deliveryProgress}%`;
                runningProgressBar.innerText = `${deliveryProgress}%`;
                runningProgressBar.setAttribute('title', `${recipientsHandled}/${numberOfRecipients}`);
                runningProgressBar.setAttribute('aria-valuenow', `${deliveryProgress}%`);
                const tableRow = runningProgressBar.closest('tr');
                const scheduledBegin = tableRow.querySelector('.mail-scheduled-begin');
                if (scheduledBegin) {
                    scheduledBegin.innerText = result.scheduledBegin;
                }
                const scheduledEnd = tableRow.querySelector('.mail-scheduled-end');
                if (scheduledEnd) {
                    scheduledEnd.innerText = result.scheduledEnd;
                }
                if (status === 5) {
                    clearInterval(interval);
                    tableRow.classList.remove('table-info');
                    runningProgressBar.className = 'progress-bar bg-success';
                    const mailDeleteButton = tableRow.querySelector('.mail-delete-button');
                    if (mailDeleteButton) {
                        mailDeleteButton.style.display = 'none';
                    }
                    const mailReportDeleteButton = tableRow.querySelector('.mail-report-delete-button');
                    if (mailReportDeleteButton) {
                        mailReportDeleteButton.classList.remove('d-none');
                    }
                }
            });
        }, TYPO3.settings.Mail.refreshRate * 1000);
    });
});
