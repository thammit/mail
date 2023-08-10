import AjaxRequest from "@typo3/core/ajax/ajax-request.js";

class QueueRefresher {
    constructor(refreshRate) {
        document.querySelectorAll('.mail-queue-table tbody tr.table-info .progress-bar').forEach((runningProgressBar) => {
            this.updateMailQueues(runningProgressBar);
            const interval = setInterval(() => {
                this.updateMailQueues(runningProgressBar, interval);
            }, refreshRate * 1000);
        });
    }

    updateMailQueues(runningProgressBar, interval) {
        const request = new AjaxRequest(TYPO3.settings.ajaxUrls.mail_queue_state)
        .withQueryArguments({mail: parseInt(runningProgressBar.dataset.mailUid)})
        .get();
        request.then(async (response) => {
            return response.resolve('json');
        }).then((result) => {
            const sent = Boolean(result.sent);
            const recipientsHandled = parseInt(result.recipientsHandled);
            const deliveryProgress = parseInt(result.deliveryProgress);
            const numberOfRecipients = parseInt(runningProgressBar.getAttribute('aria-valuemax'));
            runningProgressBar.style.width = `${deliveryProgress}%`;
            runningProgressBar.innerText = `${deliveryProgress}%`;
            runningProgressBar.setAttribute('title', `${recipientsHandled}/${numberOfRecipients}`);
            runningProgressBar.setAttribute('aria-valuenow', String(result.sent));
            const tableRow = runningProgressBar.closest('tr');
            const scheduledBegin = tableRow.querySelector('.mail-scheduled-begin');
            if (scheduledBegin) {
                scheduledBegin.innerText = result.scheduledBegin;
            }
            const scheduledEnd = tableRow.querySelector('.mail-scheduled-end');
            if (scheduledEnd) {
                scheduledEnd.innerText = result.scheduledEnd;
            }
            if (sent) {
                if (interval) {
                    clearInterval(interval);
                }
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
    }
}

new QueueRefresher(TYPO3.settings.Mail.refreshRate);
