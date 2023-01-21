<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Controller;

use MEDIAESSENZ\Mail\Utility\BackendUserUtility;
use MEDIAESSENZ\Mail\Utility\LanguageUtility;
use MEDIAESSENZ\Mail\Utility\TypoScriptUtility;
use MEDIAESSENZ\Mail\Utility\ViewUtility;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Extbase\Mvc\Exception\StopActionException;

class ConfigurationController  extends AbstractController
{
    protected string $tsConfigPrefix = 'mod.web_modules.mail.';

    /**
     * @return ResponseInterface
     */
    public function indexAction(): ResponseInterface
    {
        if (!isset($this->implodedParams['plainParams'])) {
            $this->implodedParams['plainParams'] = '&plain=1';
        }
        if (!isset($this->implodedParams['quickMailCharset'])) {
            $this->implodedParams['quickMailCharset'] = 'utf-8';
        }
        if (!isset($this->implodedParams['charset'])) {
            $this->implodedParams['charset'] = 'utf-8';
        }
        if (!isset($this->implodedParams['sendPerCycle'])) {
            $this->implodedParams['sendPerCycle'] = '50';
        }

        $this->view->assignMultiple([
            'configuration' => $this->implodedParams,
            'charsets' => array_unique(array_values(mb_list_encodings())),
            'backendUser' => [
                'name' => BackendUserUtility::getBackendUser()->user['realName'] ?? '',
                'email' => BackendUserUtility::getBackendUser()->user['email'] ?? '',
                'uid' => BackendUserUtility::getBackendUser()->user['uid'] ?? '',
            ],
        ]);

        $this->moduleTemplate->setContent($this->view->render());
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/Tooltip');
        $this->configureOverViewDocHeader();

        return $this->htmlResponse($this->moduleTemplate->renderContent());
    }

    /**
     * @param array $pageTS
     * @return void
     * @throws StopActionException
     */
    public function updateAction(array $pageTS): void
    {
        if (!BackendUserUtility::getBackendUser()->doesUserHaveAccess(BackendUtility::getRecord('pages', $this->id), Permission::PAGE_EDIT)) {
            ViewUtility::addNotificationError(
                sprintf(LanguageUtility::getLL('configuration.notification.permissionError.message'), $this->id),
                LanguageUtility::getLL('general.notification.severity.error.title')
            );

            $this->redirect('index');
        }
        if ($pageTS) {
            $success = TypoScriptUtility::updatePagesTSConfig($this->id, $pageTS, $this->tsConfigPrefix);
            if ($success) {
                ViewUtility::addNotificationSuccess(
                    sprintf(LanguageUtility::getLL('configuration.notification.savedOnPage.message'), $this->id),
                    LanguageUtility::getLL('general.notification.severity.success.title')
                );

                $this->redirect('index');
            }
            ViewUtility::addNotificationInfo(
                sprintf(LanguageUtility::getLL('configuration.notification.noChanges.message'), $this->id),
                LanguageUtility::getLL('queue.notification.nothingToDo.title')
            );

        }
        $this->redirect('index');
    }

    /**
     * Create document header buttons of "overview" action
     */
    protected function configureOverViewDocHeader(): void
    {
        $buttonBar = $this->moduleTemplate->getDocHeaderComponent()->getButtonBar();
        $saveButton = $buttonBar->makeInputButton()
            ->setName('save')
            ->setValue('1')
            ->setIcon($this->iconFactory->getIcon('actions-document-save', Icon::SIZE_SMALL))
            ->setForm('mailConfigurationForm')->setShowLabelText(true)
            ->setTitle(LanguageUtility::getLL('general.button.save'));
        $buttonBar->addButton($saveButton);

        $shortCutButton = $buttonBar->makeShortcutButton()->setRouteIdentifier('MailMail_MailConfiguration');
        $arguments = [
            'id' => $this->id,
        ];
        $displayName = 'Mail Configuration [' . $this->id . ']';
        $shortCutButton->setArguments($arguments);
        $shortCutButton->setDisplayName($displayName);
        $buttonBar->addButton($shortCutButton, ButtonBar::BUTTON_POSITION_RIGHT);
    }
}
