import DateTimePicker from "@typo3/backend/date-time-picker.js";

class ScheduleSending {
    constructor() {
        DateTimePicker.initialize(document.getElementById('mail-distribution-time'));
        const form = document.getElementById('mail-wizard-schedule-sending-form');
        const submitButton = document.getElementById('mail-wizard-schedule-sending-form-submit-button');
        this.checkboxes = document.getElementsByName('mail[recipientGroups][]');

        for (let i = 0; i < this.checkboxes.length; i++) {
            this.checkboxes[i].addEventListener('change', () => {
                submitButton.disabled = !this.isChecked();
            });
        }
        submitButton.disabled = !this.isChecked();
    }

    isChecked() {
        for (let i = 0; i < this.checkboxes.length; i++) {
            if (this.checkboxes[i].checked) {
                return true;
            }
        }
        return false;
    }
}

new ScheduleSending();
