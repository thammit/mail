import AjaxRequest from "@typo3/core/ajax/ajax-request.js";

class QueueRefresher {
    constructor() {
        const selector = '.mail-queue-table tbody tr:not(.table-success) .progress-bar';
        //const selector = '.mail-queue-table tbody .progress-bar';
        document.querySelectorAll(selector).forEach((runningProgressBar) => {
            const interval = setInterval(function () {
                this.fetchCurrentState(runningProgressBar, interval);
            }, 60000);
        });
    }

    fetchCurrentState(runningProgressBar, interval) {
        const request = new AjaxRequest(TYPO3.settings.ajaxUrls.mail_queue_state)
        .withQueryArguments({mail: parseInt(runningProgressBar.dataset.mailUid)})
        .get();
        request.then(async (response) => {
            return response.resolve('json');
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
    }
}

new QueueRefresher();
