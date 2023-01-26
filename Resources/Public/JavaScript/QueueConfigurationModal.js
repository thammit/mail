var __importDefault = this && this.__importDefault || function (t) {
    return t && t.__esModule ? t : {default: t};
};
define(['require', 'exports', 'jquery', 'TYPO3/CMS/Backend/Modal', 'TYPO3/CMS/Backend/Enum/Severity', 'TYPO3/CMS/Core/Event/RegularEvent'], (function (require, exports, jquery, Modal, Severity, RegularEvent) {
    'use strict';
    return new class {
        constructor() {
            this.selector = '.js-mail-queue-configuration-modal';
            this.initialize();
        }

        initialize() {
            new RegularEvent('click', (function (t) {
                t.preventDefault();
                const mailQueueConfigurationModalConfig = {
                    type: Modal.types.default,
                    title: this.dataset.modalTitle,
                    size: Modal.sizes.medium,
                    severity: Severity.SeverityEnum.notice,
                    content: jquery(document.getElementById(this.dataset.modalIdentifier).innerHTML),
                    additionalCssClasses: ['mail-queue-configuration-modal'],
                    callback: t => {
                        t.on('submit', '.mail-queue-configuration-form', e => {
                            t.trigger('modal-dismiss');
                        });
                        t.on('button.clicked', e => {
                            if ('save' === e.target.getAttribute('name')) {
                                t.find('form').trigger('submit');
                            } else {
                                t.trigger('modal-dismiss');
                            }
                        });
                    },
                    buttons: [
                        {
                            text: this.dataset.buttonCloseText,
                            btnClass: 'btn-default',
                            name: 'cancel'
                        },
                        {
                            text: this.dataset.buttonOkText,
                            active: true,
                            btnClass: 'btn-primary',
                            name: 'save'
                        }
                    ]
                };
                Modal.advanced(mailQueueConfigurationModalConfig);
            })).delegateTo(document, this.selector);
        }
    };
}));
