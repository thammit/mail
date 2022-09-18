<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Controller;

use MEDIAESSENZ\Mail\Utility\MailerUtility;
use MEDIAESSENZ\Mail\Utility\TypoScriptUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ConfigurationController extends AbstractController
{
    protected string $TSconfPrefix = 'mod.web_modules.dmail.';
    protected array $pageTS = [];

    protected $requestUri = '';

    public function indexAction(ServerRequestInterface $request): ResponseInterface
    {
        $currentModule = 'Configuration';
        $this->view->setTemplate($currentModule);

        $this->init($request);
        $this->initConfiguration($request);
        $this->updatePageTS();

        if (($this->id && $this->access) || (MailerUtility::isAdmin() && !$this->id)) {
            $this->moduleTemplate->addJavaScriptCode($this->getJS($this->sys_dmail_uid));

            $module = $this->getModulName();

            if ($module == 'dmail') {
                // Direct mail module
                if (($this->pageinfo['doktype'] ?? 0) == 254) {
                    $this->setDefaultValues();
                    $this->view->assignMultiple([
                        'implodedParams' => $this->implodedParams,
                    ]);
                } else if ($this->id != 0) {
                    $this->messageQueue->addMessage(MailerUtility::getFlashMessage(MailerUtility::getLL('dmail_noRegular'), MailerUtility::getLL('dmail_newsletters'), AbstractMessage::WARNING));
                }
            } else {
                $this->messageQueue->addMessage(MailerUtility::getFlashMessage(MailerUtility::getLL('select_folder'), MailerUtility::getLL('header_conf'), AbstractMessage::WARNING));
            }
        } else {
            // If no access or if ID == zero
            $this->view->setTemplate('NoAccess');
            $this->messageQueue->addMessage(MailerUtility::getFlashMessage('If no access or if ID == zero', 'No Access', AbstractMessage::WARNING));
        }

        /**
         * Render template and return html content
         */
        $this->moduleTemplate->setContent($this->view->render());
        return new HtmlResponse($this->moduleTemplate->renderContent());
    }

    protected function initConfiguration(ServerRequestInterface $request): void
    {
        $queryParams = $request->getQueryParams();
        $parsedBody = $request->getParsedBody();

        $normalizedParams = $request->getAttribute('normalizedParams');
        $this->requestUri = $normalizedParams->getRequestUri();

        $this->pageTS = $parsedBody['pageTS'] ?? $queryParams['pageTS'] ?? [];
    }

    protected function setDefaultValues()
    {
        if (!isset($this->implodedParams['plainParams'])) {
            $this->implodedParams['plainParams'] = '&type=99';
        }
        if (!isset($this->implodedParams['quick_mail_charset'])) {
            $this->implodedParams['quick_mail_charset'] = 'utf-8';
        }
        if (!isset($this->implodedParams['direct_mail_charset'])) {
            $this->implodedParams['direct_mail_charset'] = 'iso-8859-1';
        }
    }

    /**
     * Update the pageTS
     * No return value: sent header to the same page
     *
     * @return void
     */
    protected function updatePageTS()
    {
        if (MailerUtility::getBackendUser()->doesUserHaveAccess(BackendUtility::getRecord('pages', $this->id), 2)) {
            if (is_array($this->pageTS) && count($this->pageTS)) {
                TypoScriptUtility::updatePagesTSConfig($this->id, $this->pageTS, $this->TSconfPrefix);
                header('Location: ' . GeneralUtility::locationHeaderUrl($this->requestUri));
            }
        }
    }
}
