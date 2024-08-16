<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Controller;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Exception;
use FriendsOfTYPO3\TtAddress\Domain\Model\Dto\Demand;
use FriendsOfTYPO3\TtAddress\Domain\Repository\AddressRepository;
use JsonException;
use MEDIAESSENZ\Mail\Constants;
use MEDIAESSENZ\Mail\Domain\Model\Group;
use MEDIAESSENZ\Mail\Domain\Model\Mail;
use MEDIAESSENZ\Mail\Domain\Model\MailFactory;
use MEDIAESSENZ\Mail\Domain\Repository\FrontendUserRepository;
use MEDIAESSENZ\Mail\Domain\Repository\SysCategoryMmRepository;
use MEDIAESSENZ\Mail\Domain\Repository\TtContentRepository;
use MEDIAESSENZ\Mail\Events\AddTestRecipientsEvent;
use MEDIAESSENZ\Mail\Exception\HtmlContentFetchFailedException;
use MEDIAESSENZ\Mail\Exception\PlainTextContentFetchFailedException;
use MEDIAESSENZ\Mail\Property\TypeConverter\DateTimeImmutableConverter;
use MEDIAESSENZ\Mail\Type\Bitmask\SendFormat;
use MEDIAESSENZ\Mail\Type\Enumeration\MailStatus;
use MEDIAESSENZ\Mail\Utility\BackendDataUtility;
use MEDIAESSENZ\Mail\Utility\BackendUserUtility;
use MEDIAESSENZ\Mail\Utility\ConfigurationUtility;
use MEDIAESSENZ\Mail\Utility\LanguageUtility;
use MEDIAESSENZ\Mail\Utility\RecipientUtility;
use MEDIAESSENZ\Mail\Utility\TypoScriptUtility;
use MEDIAESSENZ\Mail\Utility\ViewUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\CssSelector\Exception\ParseException;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Resource\Exception\InvalidFileException;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Extbase\Mvc\Exception\NoSuchArgumentException;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\CMS\Extbase\Reflection\ClassSchema\Exception\NoSuchPropertyException;
use TYPO3\CMS\Extbase\Reflection\Exception\UnknownClassException;

class MailController extends AbstractController
{
    /**
     * @return ResponseInterface
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    public function indexAction(): ResponseInterface
    {
        if ($this->id === 0 || ($this->pageInfo['doktype'] !== (int)ConfigurationUtility::getExtensionConfiguration('mailPageTypeNumber') && $this->pageInfo['module'] !== Constants::MAIL_MODULE_NAME)) {
            return $this->handleNoMailModulePageRedirect();
        }

        if (!array_key_exists('module', $this->pageInfo) || $this->pageInfo['module'] !== Constants::MAIL_MODULE_NAME) {
            // the currently selected page is not a mail module sys folder
            $draftMails = $this->mailRepository->findOpenByPidAndPage($this->pageInfo['pid'], $this->id);
            if ($this->typo3MajorVersion < 12) {
                // Hack, because redirect to pid would not work otherwise (see extbase/Classes/Mvc/Web/Routing/UriBuilder.php line 646)
                $_GET['id'] = $this->pageInfo['pid'];
            }
            if ($draftMails->count() > 0) {
                // there is already a draft mail of this page -> use it
                return $this->redirect('draftMail', null, null,
                    ['mail' => $draftMails->getFirst()->getUid(), 'id' => $this->pageInfo['pid']]);
            }
            if ($this->pageInfo['hidden']) {
                return $this->handleNoMailModulePageRedirect();
            }
            // create a new mail of the page
            return $this->redirect('createMailFromInternalPage', null, null,
                ['page' => $this->id, 'id' => $this->pageInfo['pid']]);
        }

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

        $assignments = [
            'configuration' => $this->implodedParams,
            'ttAddressIsLoaded' => $this->ttAddressIsLoaded,
            'jumpurlIsLoaded' => $this->jumpurlIsLoaded,
            'charsets' => array_unique(array_values(mb_list_encodings())),
            'backendUser' => [
                'name' => BackendUserUtility::getBackendUser()->user['realName'] ?? '',
                'email' => BackendUserUtility::getBackendUser()->user['email'] ?? '',
                'uid' => BackendUserUtility::getBackendUser()->user['uid'] ?? '',
            ],
        ];

        $panels = [
            Constants::PANEL_DRAFT,
            Constants::PANEL_INTERNAL,
            Constants::PANEL_EXTERNAL,
            Constants::PANEL_QUICK_MAIL
        ];
        if ($this->userTSConfiguration['hideTabs'] ?? false) {
            $hidePanel = GeneralUtility::trimExplode(',', $this->userTSConfiguration['hideTabs']);
            foreach ($hidePanel as $hideTab) {
                $panels = ArrayUtility::removeArrayEntryByValue($panels, $hideTab);
            }
        }

        $draftMails = $this->mailRepository->findOpenByPid($this->id);

        $defaultTab = $draftMails->count() > 0 ? Constants::PANEL_DRAFT : Constants::PANEL_INTERNAL;
        if ($this->userTSConfiguration['defaultTab'] ?? false) {
            if (in_array($this->userTSConfiguration['defaultTab'], $panels)) {
                $defaultTab = $this->userTSConfiguration['defaultTab'];
            }
        }

        $panelData = [];
        foreach ($panels as $panel) {
            $open = $defaultTab === $panel;
            switch ($panel) {
                case Constants::PANEL_DRAFT:
                    $panelData['draft'] = [
                        'open' => $open,
                        'data' => $draftMails,
                    ];
                    break;
                case Constants::PANEL_INTERNAL:
                    $panelData['internal'] = [
                        'open' => $open,
                        'data' => BackendDataUtility::addToolTipData(
                            $this->pageRepository->getMenu(
                                $this->id,
                                'uid,pid,title,fe_group,doktype,shortcut,shortcut_mode,mount_pid,nav_hide,hidden,starttime,endtime,t3ver_state',
                                'sorting',
                                'AND hidden = 0',
                                false
                            )
                        ),
                    ];
                    break;
                case Constants::PANEL_EXTERNAL:
                    $panelData['external'] = ['open' => $open];
                    break;
                case Constants::PANEL_QUICK_MAIL:
                    $panelData['quickMail'] = [
                        'open' => $open,
                        'fromName' => BackendUserUtility::getBackendUser()->user['realName'],
                        'fromEmail' => BackendUserUtility::getBackendUser()->user['email'],
                    ];
                    break;
                default:
            }
        }

        $assignments += [
            'panel' => $panelData,
            'pageInfo' => $this->pageInfo,
            'hideCategoryStep' => $this->userTSConfiguration['hideCategoryStep'] ?? false,
            'navigation' => $this->getNavigation(1, $this->hideCategoryStep()),
            'mailSysFolderUid' => $this->id,
            'backendUser' => [
                'name' => BackendUserUtility::getBackendUser()->user['realName'] ?? '',
                'email' => BackendUserUtility::getBackendUser()->user['email'] ?? '',
                'uid' => BackendUserUtility::getBackendUser()->user['uid'] ?? '',
            ],
        ];

        $this->addIndexDocHeaderButtons();
        if ($this->typo3MajorVersion < 12) {
            $this->view->assignMultiple($assignments + ['layoutSuffix' => 'V11']);
            $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Mail/PreviewModal');
            $this->moduleTemplate->setContent($this->view->render());
            return $this->htmlResponse($this->moduleTemplate->renderContent());
        }

        $this->pageRenderer->loadJavaScriptModule('@mediaessenz/mail/preview-modal.js');
        $this->moduleTemplate->assignMultiple($assignments);

        return $this->moduleTemplate->renderResponse('Backend/Mail/Index');
    }

    /**
     * @param array $pageTS
     * @return ResponseInterface
     */
    public function updateConfigurationAction(array $pageTS): ResponseInterface
    {
        if (!BackendUserUtility::getBackendUser()->doesUserHaveAccess(BackendUtility::getRecord('pages', $this->id),
            Permission::PAGE_EDIT)) {
            ViewUtility::addNotificationError(
                sprintf(LanguageUtility::getLL('configuration.notification.permissionError.message'), $this->id),
                LanguageUtility::getLL('general.notification.severity.error.title')
            );

            return $this->redirect('index');
        }
        if ($pageTS) {
            $success = TypoScriptUtility::updatePagesTSConfig($this->id, $pageTS, 'mod.web_modules.mail.');
            if ($success) {
                ViewUtility::addNotificationSuccess(
                    sprintf(LanguageUtility::getLL('configuration.notification.savedOnPage.message'), $this->id),
                    LanguageUtility::getLL('general.notification.severity.success.title')
                );
                if ($this->mailRepository->findOpenByPid($this->id)->count() > 0) {
                    ViewUtility::addNotificationWarning(
                        LanguageUtility::getLL('configuration.notification.draftMailsNotAffected.message'),
                        LanguageUtility::getLL('general.notification.severity.warning.title')
                    );
                }

                return $this->redirect('index');
            }
            ViewUtility::addNotificationInfo(
                sprintf(LanguageUtility::getLL('configuration.notification.noChanges.message'), $this->id),
                LanguageUtility::getLL('queue.notification.nothingToDo.title')
            );

        }
        return $this->redirect('index');
    }

    /**
     * @param int $page
     * @return ResponseInterface
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    public function createMailFromInternalPageAction(int $page): ResponseInterface
    {
        $mailFactory = MailFactory::forStorageFolder($this->id);
        // todo add multi language support
        $newMail = $mailFactory->fromInternalPage($page);
        if ($newMail instanceof Mail) {
            return $this->addNewMailAndRedirectToSettings($newMail);
        }

        ViewUtility::addNotificationError(
            'Could not generate mail from internal page.',
            LanguageUtility::getLL('general.notification.severity.error.title')
        );

        return $this->redirect('index');
    }

    /**
     * @param string $subject
     * @param string $htmlUrl
     * @param string $plainTextUrl
     * @return ResponseInterface
     */
    public function createMailFromExternalUrlsAction(
        string $subject,
        string $htmlUrl,
        string $plainTextUrl
    ): ResponseInterface {
        $mailFactory = MailFactory::forStorageFolder($this->id);
        try {
            $newMail = $mailFactory->fromExternalUrls($subject, $htmlUrl, $plainTextUrl);
            if ($newMail instanceof Mail) {
                return $this->addNewMailAndRedirectToSettings($newMail);
            }
        } catch (\Exception) {
        }

        return $this->redirect('index');
    }

    /**
     * @param string $subject
     * @param string $message
     * @param string $fromName
     * @param string $fromEmail
     * @param bool $breakLines
     * @return ResponseInterface
     */
    public function createQuickMailAction(
        string $subject,
        string $message,
        string $fromName,
        string $fromEmail,
        bool $breakLines
    ): ResponseInterface {
        $mailFactory = MailFactory::forStorageFolder($this->id);
        $newMail = $mailFactory->fromText($subject, $message, $fromName, $fromEmail, $breakLines);
        if ($newMail instanceof Mail) {
            return $this->addNewMailAndRedirectToSettings($newMail);
        }
        return $this->redirect('index');
    }

    /**
     * @param Mail $mail
     * @return ResponseInterface
     */
    protected function addNewMailAndRedirectToSettings(Mail $mail): ResponseInterface
    {
        $persistenceManager = GeneralUtility::makeInstance(PersistenceManager::class);
        $persistenceManager->add($mail);
        $persistenceManager->persistAll();
        return $this->redirect('settings', null, null, ['mail' => $mail->getUid()]);
    }

    /**
     * @param Mail $mail
     * @return ResponseInterface
     */
    public function draftMailAction(Mail $mail): ResponseInterface
    {
        if ($mail->getStep() > 1) {
            $navigation = $this->getNavigation($mail->getStep() - 1, $this->hideCategoryStep($mail));
            return $this->redirect($navigation['nextAction'], null, null, ['mail' => $mail->getUid()]);
        }
        return $this->redirect('index');
    }

    /**
     * @param Mail $mail
     * @param string $tabId
     * @return ResponseInterface
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws HtmlContentFetchFailedException
     * @throws IllegalObjectTypeException
     * @throws ParseException
     * @throws PlainTextContentFetchFailedException
     * @throws UnknownObjectException
     * @throws JsonException
     */
    public function updateContentAction(Mail $mail, string $tabId = ''): ResponseInterface
    {
        $dataHandler = $this->getDataHandler();
        $dataHandler->start([], []);
        $dataHandler->clear_cacheCmd($mail->getPage());
        $mailFactory = MailFactory::forStorageFolder($this->id);
        if ($mail->isExternal()) {
            // it's a quick/external mail
            if (str_starts_with($mail->getHtmlParams(), 'http') || str_starts_with($mail->getPlainParams(), 'http')) {
                // it's an external mail -> fetch content again
                $newMail = $mailFactory->fromExternalUrls($mail->getSubject(), $mail->getHtmlParams(),
                    $mail->getPlainParams());
            } else {
                return $this->redirect('settings', null, null, ['mail' => $mail->getUid()]);
            }
        } else {
            $newMail = $mailFactory->fromInternalPage($mail->getPage(), $mail->getSysLanguageUid());
        }
        if ($newMail instanceof Mail) {
            // copy new fetch content and charset to current mail record
            // $mail->setSubject($newMail->getSubject());
            $mail->setMessageId($newMail->getMessageId());
            $mail->setPlainContent($newMail->getPlainContent());
            $mail->setHtmlContent($newMail->getHtmlContent());
            $mail->setCharset($newMail->getCharset());
            $mail->setHtmlLinks($newMail->getHtmlLinks());

            $this->mailRepository->update($mail);
            return $this->redirect('settings', null, null,
                ['mail' => $mail->getUid(), 'updated' => 1, 'tabId' => $tabId]);
        }
        return $this->redirect('index');
    }

    /**
     * @param Mail $mail
     * @param ?bool $updated
     * @param string $tabId
     * @return ResponseInterface
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws IllegalObjectTypeException
     * @throws InvalidFileException
     * @throws NoSuchPropertyException
     * @throws UnknownClassException
     * @throws UnknownObjectException
     */
    public function settingsAction(Mail $mail, bool $updated = false, string $tabId = ''): ResponseInterface
    {
        $updatePreview = $mail->getStep() === 1 || $updated;

        if (!$mail->getSendOptions()->hasFormat(SendFormat::HTML)) {
            $mail->setHtmlContent('');
        } elseif (!$mail->getHtmlContent()) {
            return $this->redirect('updateContent', null, null, [
                'mail' => $mail->getUid(),
                'tabId' => $tabId,
            ]);
        }
        if (!$mail->getSendOptions()->hasFormat(SendFormat::PLAIN)) {
            $mail->setPlainContent('');
        } elseif (!$mail->getPlainContent()) {
            return $this->redirect('updateContent', null, null, [
                'mail' => $mail->getUid(),
                'tabId' => $tabId,
            ]);
        }

        $mail->setStep(2);
        $this->mailRepository->update($mail);
        $this->mailRepository->persist();

        $this->assignFieldGroups($mail);

        $assignments = [
            'activeTabId' => $tabId,
            'mail' => $mail,
            'navigation' => $this->getNavigation(2, $this->hideCategoryStep($mail)),
        ];

        if ($mail->isInternal() || $mail->isExternal()) {
            if ($updatePreview && ConfigurationUtility::getExtensionConfiguration('createMailThumbnails')) {
                // add html2canvas stuff
                $this->pageRenderer->addInlineSetting('Mail', 'mailUid', $mail->getUid());
                if ($this->typo3MajorVersion < 12) {
                    $this->pageRenderer->addRequireJsConfiguration([
                        'paths' => [
                            'html2canvas' => PathUtility::getPublicResourceWebPath('EXT:mail/Resources/Public/') . 'JavaScript/Contrib/html2canvas.min',
                        ],
                    ]);
                    $this->pageRenderer->loadRequireJsModule('html2canvas');
                    $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Mail/PreviewImage');
                } else {
                    $this->pageRenderer->loadJavaScriptModule('@mediaessenz/mail/preview-image.js');
                }
            }
        }

        if ($updatePreview && !$mail->isQuickMail()) {
            if ($mail->isInternal()) {
                $messageValue = BackendUtility::getProcessedValue('tx_mail_domain_model_mail', 'page',
                    $mail->getPage());
            } else {
                $messageValue = trim(BackendUtility::getProcessedValue('tx_mail_domain_model_mail', 'plainParams',
                        $mail->getPlainParams()) . ' / ' . BackendUtility::getProcessedValue('tx_mail_domain_model_mail',
                        'htmlParams', $mail->getHtmlParams()),
                    ' /');
            }
            $this->addJsNotification(
                sprintf(LanguageUtility::getLL('mail.wizard.notification.fetchSuccessfully.message'),
                    $messageValue),
                LanguageUtility::getLL('general.notification.severity.success.title'));
        }

        if ($this->typo3MajorVersion < 12) {
            $this->view->assignMultiple($assignments + ['layoutSuffix' => 'V11']);
            $this->moduleTemplate->setContent($this->view->render());
            return $this->htmlResponse($this->moduleTemplate->renderContent());
        }

        $this->moduleTemplate->assignMultiple($assignments);
        return $this->moduleTemplate->renderResponse('Backend/Mail/Settings');
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws IllegalObjectTypeException
     * @throws JsonException
     * @throws UnknownObjectException
     */
    public function savePreviewImageAction(ServerRequestInterface $request): ResponseInterface
    {
        // language service has to be set here, because method is called by ajax route, which doesn't call initializeAction
        LanguageUtility::getLanguageService()->includeLLFile('EXT:mail/Resources/Private/Language/Modules.xlf');
        $bodyContents = json_decode($request->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
        $mailUid = $bodyContents['mailUid'] ?? 0;
        $dataUrl = $bodyContents['dataUrl'] ?? 0;

        if ($dataUrl && $mailUid) {
            $mail = $this->mailRepository->findByUid($mailUid);
            if ($mail instanceof Mail) {
                $mail->setPreviewImage($dataUrl);
                $this->mailRepository->update($mail);
                $this->mailRepository->persist();
                return $this->jsonResponse(json_encode([
                    'title' => LanguageUtility::getLL('general.notification.severity.success.title'),
                    'message' => LanguageUtility::getLL('mail.wizard.notification.previewImageSaved.message'),
                ], JSON_THROW_ON_ERROR));
            }
        }

        return $this->jsonErrorResponse(json_encode([
            'title' => LanguageUtility::getLL('general.notification.severity.error.title'),
            'message' => LanguageUtility::getLL('mail.wizard.notification.previewImageCreationFailed.message'),
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param Mail $mail
     * @return ResponseInterface
     * @throws IllegalObjectTypeException
     * @throws RouteNotFoundException
     * @throws UnknownObjectException
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws Exception
     */
    public function categoriesAction(Mail $mail): ResponseInterface
    {
        $mail->setStep(3);
        $this->mailRepository->update($mail);
        $this->mailRepository->persist();

        $data = [];
        $rows = GeneralUtility::makeInstance(TtContentRepository::class)->findByPidAndSysLanguageUid($mail->getPage(),
            $mail->getSysLanguageUid());

        $configTreeStartingPoints = false;

        if ($rows) {
            $data = [
                'rows' => [],
            ];

            $colPos = 9999;
            $sysCategoryMmRepository = GeneralUtility::makeInstance(SysCategoryMmRepository::class);
            foreach ($rows as $contentElementData) {
                $categoriesRow = [];
                $contentElementCategories = $sysCategoryMmRepository->findByUidForeignTableNameFieldName($contentElementData['uid'],
                    'tt_content');

                foreach ($contentElementCategories as $contentElementCategory) {
                    $categoriesRow[] = (int)$contentElementCategory['uid_local'];
                }

                if ($colPos !== (int)$contentElementData['colPos']) {
                    $data['rows'][] = [
                        'colPos' => BackendUtility::getProcessedValue('tt_content', 'colPos',
                            $contentElementData['colPos']),
                    ];
                    $colPos = (int)$contentElementData['colPos'];
                }

                $categories = [];
                $ttContentPageTsConfig = BackendUtility::getTCEFORM_TSconfig('tt_content', $contentElementData);
                $configTreeStartingPoints = $ttContentPageTsConfig['categories']['config.']['treeConfig.']['startingPoints'] ?? false;
                if ($configTreeStartingPoints) {
                    $configTreeStartingPointsArray = GeneralUtility::intExplode(',', $configTreeStartingPoints,
                        true);
                    foreach ($configTreeStartingPointsArray as $startingPoint) {
                        $ttContentCategories = $this->categoryRepository->findByParent($startingPoint);
                        foreach ($ttContentCategories as $category) {
                            $categories[] = [
                                'uid' => $category->getUid(),
                                'title' => $category->getTitle(),
                                'checked' => in_array($category->getUid(), $categoriesRow),
                            ];
                        }
                    }
                } else {
                    $ttContentCategories = $this->categoryRepository->findAll();
                    foreach ($ttContentCategories as $category) {
                        $categories[] = [
                            'uid' => $category->getUid(),
                            'title' => $category->getTitle(),
                            'checked' => in_array($category->getUid(), $categoriesRow),
                        ];
                    }
                }

                $data['rows'][] = [
                    'uid' => $contentElementData['uid'],
                    'header' => $contentElementData['header'],
                    'CType' => $contentElementData['CType'],
                    'list_type' => $contentElementData['list_type'],
                    'bodytext' => empty($contentElementData['bodytext']) ? '' : GeneralUtility::fixed_lgd_cs(strip_tags($contentElementData['bodytext']),
                        200),
                    'hasCategory' => (bool)$contentElementData['categories'],
                    'categories' => $categories,
                ];
            }
        }

        $assignments = [
            'data' => $data,
            'mail' => $mail,
            'configTreeStartingPoints' => $configTreeStartingPoints,
            'navigation' => $this->getNavigation(3, $this->hideCategoryStep($mail)),
        ];

        $this->pageRenderer->addInlineSetting('Mail', 'mailUid', $mail->getUid());
        $this->addDocHeaderHelpButton();

        if ($this->typo3MajorVersion < 12) {
            $this->view->assignMultiple($assignments + ['layoutSuffix' => 'V11']);
            $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Mail/Categories');
            $this->moduleTemplate->setContent($this->view->render());
            return $this->htmlResponse($this->moduleTemplate->renderContent());
        }

        $this->pageRenderer->loadJavaScriptModule('@mediaessenz/mail/categories.js');
        $this->moduleTemplate->assignMultiple($assignments);
        return $this->moduleTemplate->renderResponse('Backend/Mail/Categories');
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws JsonException
     */
    public function updateCategoryRestrictionsAction(ServerRequestInterface $request): ResponseInterface
    {
        // language service has to be set here, because method is called by ajax route, which doesn't call initializeAction
        LanguageUtility::getLanguageService()->includeLLFile('EXT:mail/Resources/Private/Language/Modules.xlf');

        $contentCategories = json_decode($request->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
        $mailUid = $contentCategories['mailUid'] ?? 0;
        $mail = $this->mailRepository->findByUid((int)$mailUid);
        $contentElementUid = $contentCategories['content'] ?? 0;
        $categories = $contentCategories['categories'] ?? [];

        if ($mail instanceof Mail && $contentElementUid && $categories) {

            // build array with all checked content element categories
            $newCategories = [];
            foreach ($categories as $category) {
                if ($category['checked'] ?? false) {
                    $newCategories[] = $category['category'];
                }
            }

            // use data handler to store content categories
            $dataHandler = $this->getDataHandler();
            $data['tt_content'][$contentElementUid]['categories'] = implode(',', $newCategories);
            $dataHandler->start($data, []);
            $dataHandler->process_datamap();

            // remove cache
            $dataHandler->clear_cacheCmd($mail->getPage());

            // update content in mail, because category boundaries changed
            $mailFactory = MailFactory::forStorageFolder($mail->getPage());
            try {
                $newMail = $mailFactory->fromInternalPage($mail->getPage(), $mail->getSysLanguageUid());
                if ($newMail instanceof Mail) {
                    // copy new fetch content and charset to current mail record
                    $mail->setMessageId($newMail->getMessageId());
                    $mail->setPlainContent($newMail->getPlainContent());
                    $mail->setHtmlContent($newMail->getHtmlContent());
                    $mail->setCharset($newMail->getCharset());

                    $this->mailRepository->update($mail);
                    $this->mailRepository->persist();
                }
            } catch (ExtensionConfigurationExtensionNotConfiguredException|ExtensionConfigurationPathDoesNotExistException|IllegalObjectTypeException|UnknownObjectException $e) {
                return $this->jsonErrorResponse(json_encode([
                    'title' => LanguageUtility::getLL('general.notification.severity.error.title'),
                    'message' => LanguageUtility::getLL('mail.wizard.notification.updateContentFailed.message'),
                ], JSON_THROW_ON_ERROR));
            }

            return $this->jsonResponse(json_encode([
                'title' => LanguageUtility::getLL('mail.wizard.notification.categoriesUpdated.title'),
                'message' => LanguageUtility::getLL('mail.wizard.notification.categoriesUpdated.message'),
            ], JSON_THROW_ON_ERROR));
        }

        return $this->jsonErrorResponse(json_encode([
            'title' => LanguageUtility::getLL('general.notification.severity.error.title'),
            'message' => LanguageUtility::getLL('mail.wizard.notification.categoryRestrictionSaveFailed.message'),
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param Mail $mail
     * @return ResponseInterface
     * @throws Exception
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws IllegalObjectTypeException
     * @throws InvalidQueryException
     * @throws UnknownObjectException
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function testMailAction(Mail $mail): ResponseInterface
    {
        $mail->setStep($this->hideCategoryStep($mail) ? 3 : 4);
        $this->mailRepository->update($mail);
        $this->mailRepository->persist();

        $data = [];
        $ttAddressRepository = $this->ttAddressIsLoaded ? GeneralUtility::makeInstance(AddressRepository::class) : null;
        $frontendUsersRepository = GeneralUtility::makeInstance(FrontendUserRepository::class);

        if ($ttAddressRepository && ($this->pageTSConfiguration['testTtAddressUids'] ?? false)) {
            $demand = new Demand();
            $demand->setSingleRecords($this->pageTSConfiguration['testTtAddressUids']);
            $data['ttAddress'] = $ttAddressRepository->getAddressesByCustomSorting($demand);
        }

        if ($this->pageTSConfiguration['testMailGroupUids'] ?? false) {
            $mailGroupUids = GeneralUtility::intExplode(',', $this->pageTSConfiguration['testMailGroupUids']);
            $data['mailGroups'] = [];
            foreach ($mailGroupUids as $mailGroupUid) {
                /** @var Group $testMailGroup */
                $testMailGroup = $this->groupRepository->findByUid($mailGroupUid);
                if ($testMailGroup instanceof Group) {
                    $data['mailGroups'][$testMailGroup->getUid()]['title'] = $testMailGroup->getTitle();
                    $recipientGroups = $this->recipientService->getRecipientsUidListGroupedByRecipientSource($testMailGroup);
                    foreach ($recipientGroups as $recipientGroup => $recipients) {
                        switch ($recipientGroup) {
                            case 'fe_users':
                                foreach ($recipients as $recipient) {
                                    $data['mailGroups'][$testMailGroup->getUid()]['groups'][$recipientGroup][] = $frontendUsersRepository->findByUid($recipient);
                                }
                                break;
                            case 'tt_address':
                                if ($ttAddressRepository) {
                                    foreach ($recipients as $recipient) {
                                        $data['mailGroups'][$testMailGroup->getUid()]['groups'][$recipientGroup][] = $ttAddressRepository->findByUid($recipient);
                                    }
                                }
                                break;
                            default:
                                // handle other recipient groups with PSR-14 event
                                $data['mailGroups'][$testMailGroup->getUid()]['groups'][$recipientGroup] = $this->eventDispatcher->dispatch(new AddTestRecipientsEvent($recipientGroup))->getRecipients();
                        }
                    }
                }
            }
        }

        $hideCategoryStep = $this->hideCategoryStep($mail);

        $assignments = [
            'data' => $data,
            'navigation' => $this->getNavigation($hideCategoryStep ? 3 : 4, $hideCategoryStep),
            'mailUid' => $mail->getUid(),
            'title' => $mail->getSubject(),
            'backendUser' => [
                'name' => BackendUserUtility::getBackendUser()->user['realName'] ?? '',
                'email' => BackendUserUtility::getBackendUser()->user['email'] ?? '',
                'uid' => BackendUserUtility::getBackendUser()->user['uid'] ?? '',
            ],
        ];

        $this->addDocHeaderHelpButton();

        if ($this->typo3MajorVersion < 12) {
            $this->view->assignMultiple($assignments + ['layoutSuffix' => 'V11']);
            $this->moduleTemplate->setContent($this->view->render());
            return $this->htmlResponse($this->moduleTemplate->renderContent());
        }

        $this->moduleTemplate->assignMultiple($assignments);
        return $this->moduleTemplate->renderResponse('Backend/Mail/TestMail');
    }

    /**
     * @param Mail $mail
     * @param string $recipients
     * @return ResponseInterface
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    public function sendTestMailAction(Mail $mail, string $recipients = ''): ResponseInterface
    {
        $mailUid = $mail->getUid();
        // normalize addresses:
        $addressList = RecipientUtility::normalizeListOfEmailAddresses($recipients);

        if ($addressList) {
            if (!$this->jumpurlIsLoaded || (($this->pageTSConfiguration['clickTracking'] || $this->pageTSConfiguration['clickTrackingMailTo']) && $mail->isInternal())) {
                // no click tracking for internal test mails or if jumpurl is not loaded
                $mail = MailFactory::forStorageFolder($this->id)->fromInternalPage($mail->getPage(),
                    $mail->getSysLanguageUid(), true);
            }
            $this->mailerService->start();
            $this->mailerService->prepare($mail);
            $this->mailerService->setSubjectPrefix($this->pageTSConfiguration['testMailSubjectPrefix'] ?? '');
            $this->mailerService->sendSimpleMail($mail, $addressList);
        }

        ViewUtility::addNotificationSuccess(
            sprintf(LanguageUtility::getLL('mail.wizard.notification.testMailSent.message'), $addressList),
            LanguageUtility::getLL('mail.wizard.notification.testMailSent.title')
        );
        return $this->redirect('testMail', null, null, ['mail' => $mailUid]);
    }

    /**
     * @param Mail $mail
     * @return ResponseInterface
     * @throws Exception
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws IllegalObjectTypeException
     * @throws InvalidQueryException
     * @throws UnknownObjectException
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws \Exception
     */
    public function scheduleSendingAction(Mail $mail): ResponseInterface
    {
        $mail->setStep($this->hideCategoryStep($mail) ? 4 : 5);

        $groups = $this->recipientService->getFinalSendingGroups($this->id);
        if (count($groups) === 1) {
            $presetGroup = new ObjectStorage();
            $presetGroup->attach($this->groupRepository->findOneByPid($this->id));
            $mail->setRecipientGroups($presetGroup);
        }

        if (!$mail->getScheduled()) {
            $now = new DateTimeImmutable('now');
            if ($this->typo3MajorVersion > 11) {
                // timezone is utc and must be converted to server time zone
                $serverTimeZone = new DateTimeZone(@date_default_timezone_get());
                $offset = $serverTimeZone->getOffset($now);
                if ($offset) {
                    $interval = new DateInterval('PT' . abs($offset) . 'S');
                    $now = $offset >= 0 ? $now->add($interval) : $now->sub($interval);
                }
            }
            $mail->setScheduled($now);
        }

        $this->mailRepository->update($mail);
        $this->mailRepository->persist();

        $hideCategoryStep = $this->hideCategoryStep($mail);
        $assignments = [
            'groups' => $groups,
            'navigation' => $this->getNavigation($hideCategoryStep ? 4 : 5, $hideCategoryStep),
            'hideExcludeRecipientGroups' => $this->userTSConfiguration['hideExcludeRecipientGroups'] ?? false,
            'mail' => $mail,
            'mailUid' => $mail->getUid(),
            'title' => $mail->getSubject(),
            'v12' => $this->typo3MajorVersion >= 12,
        ];
        $this->addDocHeaderHelpButton();

        if ($this->typo3MajorVersion < 12) {
            $this->view->assignMultiple($assignments + ['layoutSuffix' => 'V11']);
            $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/DateTimePicker');
            $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Mail/ScheduleSending');
            $this->moduleTemplate->setContent($this->view->render());
            return $this->htmlResponse($this->moduleTemplate->renderContent());
        }

        $this->pageRenderer->loadJavaScriptModule('@mediaessenz/mail/schedule-sending.js');
        $this->moduleTemplate->assignMultiple($assignments);
        return $this->moduleTemplate->renderResponse('Backend/Mail/ScheduleSending');
    }

    /**
     * @throws NoSuchArgumentException
     */
    public function initializeFinishAction(): void
    {
        $format = $this->typo3MajorVersion < 12 ? 'H:i d-m-Y' : null;
        if ($this->arguments->hasArgument('mail')) {
            $this->arguments->getArgument('mail')
                ->getPropertyMappingConfiguration()
                ->forProperty('scheduled')
                ->setTypeConverterOption(DateTimeImmutableConverter::class,
                    DateTimeImmutableConverter::CONFIGURATION_DATE_FORMAT, $format);
        }
    }

    /**
     * @param Mail $mail
     * @return ResponseInterface
     * @throws IllegalObjectTypeException
     * @throws InvalidQueryException
     * @throws UnknownObjectException
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws Exception
     * @throws JsonException
     * @throws \Exception
     */
    public function finishAction(Mail $mail): ResponseInterface
    {
        if ($mail->getRecipientGroups()->count() === 0) {
            ViewUtility::addNotificationWarning(LanguageUtility::getLL('mail.wizard.notification.missingRecipientGroup.message'),
                LanguageUtility::getLL('general.notification.severity.warning.title'));
            return $this->redirect('scheduleSending', null, null, [
                'mail' => $mail,
            ]);
        }

        $recipients = $this->recipientService->getRecipientsUidListsGroupedByRecipientSource($mail->getRecipientGroups());

        if ($mail->getExcludeRecipientGroups()->count() > 0) {
            // filter recipients by exclude recipient groups emails
            $excludeRecipientGroups = $mail->getExcludeRecipientGroups();
            $excludeRecipients = $this->recipientService->getRecipientsUidListsGroupedByRecipientSource($excludeRecipientGroups);
            $emailsToExclude = $this->recipientService->getEmailAddressesByRecipientsUidListGroupedByRecipientSource($excludeRecipients);
            $filteredRecipients = $this->recipientService->removeFromRecipientListIfInExcludeEmailsList($recipients, $emailsToExclude);
            $numberOfRecipients = RecipientUtility::calculateTotalRecipientsOfUidLists($filteredRecipients);
            if ($numberOfRecipients === 0) {
                $mail->setRecipients([], true);
//                $mail->setScheduled(null);
                $this->mailRepository->update($mail);
                $this->mailRepository->persist();
                ViewUtility::addNotificationWarning(
                    LanguageUtility::getLL('mail.wizard.notification.noRecipientsDueToExcludes.message'),
                    LanguageUtility::getLL('general.notification.severity.warning.title')
                );

                return $this->redirect('scheduleSending', null, null, ['mail' => $mail]);
            }
            $recipients = $filteredRecipients;
        } else {
            $numberOfRecipients = RecipientUtility::calculateTotalRecipientsOfUidLists($recipients);
        }

        if ($numberOfRecipients === 0) {
            $mail->setRecipients([], true);
//            $mail->setScheduled(null);
            $this->mailRepository->update($mail);
            $this->mailRepository->persist();
            ViewUtility::addNotificationWarning(
                LanguageUtility::getLL('mail.wizard.notification.noRecipients.message'),
                LanguageUtility::getLL('general.notification.severity.warning.title')
            );

            return $this->redirect('scheduleSending', null, null, ['mail' => $mail]);
        }

        $mail->setRecipients($recipients,true);

        if ($this->typo3MajorVersion > 11) {
            // scheduled timezone is utc and must be converted to server time zone
            $scheduled = $mail->getScheduled();
            if ($scheduled instanceof DateTimeImmutable) {
                $serverTimeZone = new DateTimeZone(@date_default_timezone_get());
                $offset = $serverTimeZone->getOffset($scheduled);
                if ($offset) {
                    $interval = new DateInterval('PT' . abs($offset) . 'S');
                    $mail->setScheduled($offset >= 0 ? $scheduled->sub($interval) : $scheduled->add($interval));
                }
            }
        }

        $mail->setStatus(MailStatus::SCHEDULED);

//        if (false && $this->isTestMail) {
//            $updateFields['subject'] = ($this->pageTSConfiguration['testMailSubjectPrefix'] ?? '') . ' ' . $row['subject'];
//        }
//
//        // create a draft version of the record
//        if (false && $this->saveDraft) {
//            if ($row['type'] === MailType::INTERNAL) {
//                $updateFields['type'] = MailType::DRAFT_INTERNAL;
//            } else {
//                $updateFields['type'] = MailType::DRAFT_EXTERNAL;
//            }
//            $updateFields['scheduled'] = 0;
//            ViewUtility::addOkToFlashMessageQueue(
//                sprintf(LanguageUtility::getLL('mail.wizard.notification.draftSaved.message'), $row['subject'], BackendUtility::datetime($this->distributionTimeStamp)),
//                LanguageUtility::getLL('mail.wizard.notification.draftSaved.title'), true
//            );
//        } else {
//            ViewUtility::addOkToFlashMessageQueue(
//                sprintf(LanguageUtility::getLL('mail.wizard.notification.scheduledForDistribution.message'), $row['subject'], BackendUtility::datetime($this->distributionTimeStamp)),
//                LanguageUtility::getLL('mail.wizard.notification.scheduledForDistribution.title'), true
//            );
//        }

        // Update the record:
        $this->mailRepository->update($mail);

        ViewUtility::addNotificationSuccess(
            sprintf(LanguageUtility::getLL('mail.wizard.notification.finished.message'), $mail->getSubject(),
                BackendUtility::datetime($mail->getScheduled()->getTimestamp())),
            LanguageUtility::getLL('mail.wizard.notification.finished.title')
        );

        return $this->redirect('index');
    }

    /**
     * @param Mail $mail
     * @return ResponseInterface
     * @throws IllegalObjectTypeException
     */
    public function deleteAction(Mail $mail): ResponseInterface
    {
        $this->mailRepository->remove($mail);

        ViewUtility::addNotificationSuccess(
            sprintf(LanguageUtility::getLL('mail.wizard.notification.deleted.message'), $mail->getSubject()),
            LanguageUtility::getLL('mail.wizard.notification.deleted.title')
        );

        return $this->redirect('index');
    }

    protected function hideCategoryStep(Mail $mail = null): bool
    {
        return (($mail ?? false) && $mail->isExternal()) || ($this->userTSConfiguration['hideCategoryStep'] ?? false);
    }

    protected function getNavigation(int $currentStep, bool $hideCategoryStep): array
    {
        if ($hideCategoryStep) {
            $steps = [
                1 => [
                    'previousAction' => 'index',
                    'nextAction' => 'settings',
                ],
                2 => [
                    'previousAction' => 'index',
                    'nextAction' => 'testMail',
                ],
                3 => [
                    'previousAction' => 'settings',
                    'nextAction' => 'scheduleSending',
                ],
                4 => [
                    'previousAction' => 'testMail',
                    'nextAction' => 'final',
                ],
            ];

        } else {
            $steps = [
                1 => [
                    'previousAction' => 'index',
                    'nextAction' => 'settings',
                ],
                2 => [
                    'previousAction' => 'index',
                    'nextAction' => 'categories',
                ],
                3 => [
                    'previousAction' => 'settings',
                    'nextAction' => 'testMail',
                ],
                4 => [
                    'previousAction' => 'categories',
                    'nextAction' => 'scheduleSending',
                ],
                5 => [
                    'previousAction' => 'testMail',
                    'nextAction' => 'final',
                ],
            ];
        }

        return [
            'previousAction' => $steps[$currentStep]['previousAction'],
            'nextAction' => $steps[$currentStep]['nextAction'],
            'currentStep' => $currentStep,
            'totalSteps' => count($steps),
            'steps' => range(1, count($steps)),
        ];
    }

    protected function addIndexDocHeaderButtons(): void
    {
        $buttonBar = $this->moduleTemplate->getDocHeaderComponent()->getButtonBar();

        if (!($this->userTSConfiguration['hideConfiguration'] ?? false)) {
            $configurationButton = $buttonBar->makeInputButton()
                ->setTitle(LanguageUtility::getLL('general.button.configuration'))
                ->setName('configure')
                ->setDataAttributes([
                    'bs-toggle' => 'modal',
                    'bs-target' => '#mail-configuration-modal',
                    'modal-identifier' => 'mail-configuration-modal',
                    'modal-title' => LanguageUtility::getLL('mail.button.configuration'),
                    'button-ok-text' => LanguageUtility::getLL('general.button.save'),
                    'button-close-text' => LanguageUtility::getLL('general.button.cancel'),
                ])
                ->setClasses('js-mail-queue-configuration-modal')
                ->setValue(1)
                ->setIcon($this->iconFactory->getIcon('actions-cog-alt', Icon::SIZE_SMALL));
            $buttonBar->addButton($configurationButton, ButtonBar::BUTTON_POSITION_RIGHT);
        }

        if ($this->id) {
            $routeIdentifier = $this->typo3MajorVersion < 12 ? 'MailMail_MailMail' : 'mail_mail';
            $shortCutButton = $buttonBar->makeShortcutButton()
                ->setRouteIdentifier($routeIdentifier)
                ->setDisplayName(LanguageUtility::getLL('shortcut.wizard') . ' [' . $this->id . ']')
                ->setArguments([
                    'id' => $this->id,
                ]);
            $buttonBar->addButton($shortCutButton, ButtonBar::BUTTON_POSITION_RIGHT);
        }
    }

    /**
     * Create document header buttons of "overview" action
     *
     * @param string $reloadUri
     */
    protected function addSettingsDocHeaderButtons(string $reloadUri): void
    {
        $buttonBar = $this->moduleTemplate->getDocHeaderComponent()->getButtonBar();

        $reloadButton = $buttonBar->makeLinkButton()
            ->setHref($reloadUri)
            ->setTitle(LanguageUtility::getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.reload'))
            ->setShowLabelText(true)
            ->setIcon($this->iconFactory->getIcon('actions-refresh', Icon::SIZE_SMALL));
        $buttonBar->addButton($reloadButton);
    }

    /**
     * Create document header help button
     */
    protected function addDocHeaderHelpButton(): void
    {
        $buttonBar = $this->moduleTemplate->getDocHeaderComponent()->getButtonBar();

        $helpButton = $buttonBar->makeInputButton()
            ->setTitle('Help')
            ->setName('Help')
            ->setDataAttributes([
                'bs-toggle' => 'modal',
                'bs-target' => '#mail-help-modal',
            ])
            ->setClasses('js-mail-queue-configuration-modal')
            ->setValue(1)
            ->setIcon($this->iconFactory->getIcon('actions-system-help-open', Icon::SIZE_SMALL));
        $buttonBar->addButton($helpButton, ButtonBar::BUTTON_POSITION_RIGHT);
    }
}
