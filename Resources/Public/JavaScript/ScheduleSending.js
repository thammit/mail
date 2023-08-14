define([], function() {
    const form = document.getElementById('mail-wizard-schedule-sending-form');
    const submitButton = document.getElementById('mail-wizard-schedule-sending-form-submit-button');
    const checkboxes = document.getElementsByName('mail[recipientGroups][]');
    const isChecked = function() {
        for (let i = 0; i < checkboxes.length; i++) {
            if (checkboxes[i].checked) {
                return true;
            }
        }
        return false;
    }

    for (let i = 0; i < checkboxes.length; i++) {
        checkboxes[i].addEventListener('change', () => {
            submitButton.disabled = !isChecked();
        });
    }
    submitButton.disabled = !isChecked();
    });
