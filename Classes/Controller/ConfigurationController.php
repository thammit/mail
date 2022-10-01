<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Controller;

use MEDIAESSENZ\Mail\Constants;
use MEDIAESSENZ\Mail\Utility\BackendUserUtility;
use MEDIAESSENZ\Mail\Utility\LanguageUtility;
use MEDIAESSENZ\Mail\Utility\TypoScriptUtility;
use MEDIAESSENZ\Mail\Utility\ViewUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ConfigurationController extends AbstractController
{
    protected string $tsConfigPrefix = 'mod.web_modules.dmail.';
    protected array $pageTS = [];

    protected string $requestUri = '';

    public function indexAction(ServerRequestInterface $request): ResponseInterface
    {
        $currentModule = 'Configuration';
        $this->view->setTemplate($currentModule);

        $this->init($request);

        if ($this->backendUserHasModuleAccess() === false) {
            $this->view->setTemplate('NoAccess');
            ViewUtility::addWarningToFlashMessageQueue('If no access or if ID == zero', 'No Access');
            $this->moduleTemplate->setContent($this->view->render());
            return new HtmlResponse($this->moduleTemplate->renderContent());
        }

        $this->initConfiguration($request);
        $this->updatePageTS();

        if ($this->getModulName() === Constants::MAIL_MODULE_NAME) {
            // Direct mail module
            if (($this->pageInfo['doktype'] ?? 0) == 254) {
                $this->setDefaultValues();
                $this->view->assignMultiple([
                    'implodedParams' => $this->implodedParams,
                ]);
            } else {
                if ($this->id != 0) {
                    ViewUtility::addWarningToFlashMessageQueue(LanguageUtility::getLL('dmail_noRegular'),
                        LanguageUtility::getLL('dmail_newsletters'));
                }
            }
        } else {
            ViewUtility::addWarningToFlashMessageQueue(LanguageUtility::getLL('select_folder'), LanguageUtility::getLL('header_conf'));
        }

        // Render template and return html content
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/Tooltip');
//        $this->moduleTemplate->addJavaScriptCode($this->getJS($this->mailUid));
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
    protected function updatePageTS(): void
    {
        if (BackendUserUtility::getBackendUser()->doesUserHaveAccess(BackendUtility::getRecord('pages', $this->id), 2)) {
            if (count($this->pageTS)) {
                TypoScriptUtility::updatePagesTSConfig($this->id, $this->pageTS, $this->tsConfigPrefix);
                header('Location: ' . GeneralUtility::locationHeaderUrl($this->requestUri));
            }
        }
    }
}
