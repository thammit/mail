<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Controller;

use Doctrine\DBAL\Exception;
use FriendsOfTYPO3\TtAddress\Domain\Model\Dto\Demand;
use FriendsOfTYPO3\TtAddress\Domain\Repository\AddressRepository;
use MEDIAESSENZ\Mail\Constants;
use MEDIAESSENZ\Mail\Domain\Model\Group;
use MEDIAESSENZ\Mail\Domain\Model\Mail;
use MEDIAESSENZ\Mail\Domain\Model\MailFactory;
use MEDIAESSENZ\Mail\Domain\Repository\FrontendUserRepository;
use MEDIAESSENZ\Mail\Domain\Repository\SysCategoryMmRepository;
use MEDIAESSENZ\Mail\Domain\Repository\TtContentRepository;
use MEDIAESSENZ\Mail\Exception\HtmlContentFetchFailedException;
use MEDIAESSENZ\Mail\Exception\PlainTextContentFetchFailedException;
use MEDIAESSENZ\Mail\Property\TypeConverter\DateTimeImmutableConverter;
use MEDIAESSENZ\Mail\Type\Bitmask\SendFormat;
use MEDIAESSENZ\Mail\Utility\BackendDataUtility;
use MEDIAESSENZ\Mail\Utility\BackendUserUtility;
use MEDIAESSENZ\Mail\Utility\ConfigurationUtility;
use MEDIAESSENZ\Mail\Utility\LanguageUtility;
use MEDIAESSENZ\Mail\Utility\MailerUtility;
use MEDIAESSENZ\Mail\Utility\RecipientUtility;
use MEDIAESSENZ\Mail\Utility\TcaUtility;
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
use TYPO3\CMS\Core\Routing\UnableToLinkToPageException;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Extbase\Mvc\Exception\NoSuchArgumentException;
use TYPO3\CMS\Extbase\Mvc\Exception\StopActionException;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapFactory;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Reflection\ClassSchema\Exception\NoSuchPropertyException;
use TYPO3\CMS\Extbase\Reflection\Exception\UnknownClassException;
use TYPO3\CMS\Extbase\Reflection\ReflectionService;

class MailController extends AbstractController
{
    public function noPageSelectedAction(): ResponseInterface
    {
        ViewUtility::addFlashMessageWarning(LanguageUtility::getLL('mail.wizard.notification.noPageSelected.message'),
            LanguageUtility::getLL('mail.wizard.notification.noPageSelected.title'));
        $this->moduleTemplate->setContent($this->view->render());

        return $this->htmlResponse($this->moduleTemplate->renderContent());
    }

    /**
     * @return ResponseInterface
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    public function indexAction(): ResponseInterface
    {
        if ($this->id === 0 || ($this->pageInfo['doktype'] !== (int)ConfigurationUtility::getExtensionConfiguration('mailPageTypeNumber') && $this->pageInfo['module'] !== Constants::MAIL_MODULE_NAME)) {
            return $this->redirect('noPageSelected');
        }
        if ($this->pageInfo['module'] !== Constants::MAIL_MODULE_NAME) {
            // the currently selected page is not a mail module sys folder
            $draftMails = $this->mailRepository->findOpenByPidAndPage($this->pageInfo['pid'], $this->id);
            if ($this->typo3MajorVersion < 12) {
                // Hack, because redirect to pid would not work otherwise (see extbase/Classes/Mvc/Web/Routing/UriBuilder.php line 646)
                $_GET['id'] = $this->pageInfo['pid'];
            }
            if ($draftMails->count() > 0) {
                // there is already a draft mail of this page -> use it
                return $this->redirect('draftMail', null, null, ['mail' => $draftMails->getFirst()->getUid(), 'id' => $this->pageInfo['pid']]);
            }
            // create a new mail of the page
            return $this->redirect('createMailFromInternalPage', null, null, ['page' => $this->id, 'id' => $this->pageInfo['pid']]);
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

        $this->view->assignMultiple([
            'configuration' => $this->implodedParams,
            'charsets' => array_unique(array_values(mb_list_encodings())),
            'backendUser' => [
                'name' => BackendUserUtility::getBackendUser()->user['realName'] ?? '',
                'email' => BackendUserUtility::getBackendUser()->user['email'] ?? '',
                'uid' => BackendUserUtility::getBackendUser()->user['uid'] ?? '',
            ],
        ]);

        $panels = [Constants::PANEL_DRAFT, Constants::PANEL_INTERNAL, Constants::PANEL_EXTERNAL, Constants::PANEL_QUICK_MAIL];
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
                    $this->pageRepository->where_groupAccess = '';
                    $panelData['internal'] = [
                        'open' => $open,
                        'data' => BackendDataUtility::addToolTipData($this->pageRepository->getMenu($this->id,
                            'uid,pid,title,fe_group,doktype,shortcut,shortcut_mode,mount_pid,nav_hide,hidden,starttime,endtime,t3ver_state')),
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

        $this->view->assignMultiple([
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
        ]);

        $this->moduleTemplate->setContent($this->view->render());
        $this->addIndexDocHeaderButtons();
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/Tooltip');
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Mail/PreviewModal');

        return $this->htmlResponse($this->moduleTemplate->renderContent());
    }

    /**
     * @param array $pageTS
     * @return ResponseInterface
     */
    public function updateConfigurationAction(array $pageTS): ResponseInterface
    {
        if (!BackendUserUtility::getBackendUser()->doesUserHaveAccess(BackendUtility::getRecord('pages', $this->id), Permission::PAGE_EDIT)) {
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
    public function createMailFromExternalUrlsAction(string $subject, string $htmlUrl, string $plainTextUrl): ResponseInterface
    {
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
    public function createQuickMailAction(string $subject, string $message, string $fromName, string $fromEmail, bool $breakLines): ResponseInterface
    {
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
     */
    public function updateContentAction(Mail $mail, string $tabId = ''): ResponseInterface
    {
        $mailFactory = MailFactory::forStorageFolder($this->id);
        $newMail = null;
        if ($mail->isExternal()) {
            // it's a quick/external mail
            if (str_starts_with($mail->getHtmlParams(), 'http') || str_starts_with($mail->getPlainParams(), 'http')) {
                // it's an external mail -> fetch content again
                $newMail = $mailFactory->fromExternalUrls($mail->getSubject(), $mail->getHtmlParams(), $mail->getPlainParams());
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

            $this->mailRepository->update($mail);
            return $this->redirect('settings', null, null, ['mail' => $mail->getUid(), 'updated' => 1, 'tabId' => $tabId]);
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
     * @throws RouteNotFoundException
     * @throws UnknownClassException
     * @throws UnknownObjectException
     * @throws StopActionException
     */
    public function settingsAction(Mail $mail, bool $updated = false, string $tabId = ''): ResponseInterface
    {
        $updatePreview = $mail->getStep() === 1 || $updated;

        if (!$mail->getSendOptions()->hasFormat(SendFormat::HTML)) {
            $mail->setHtmlContent('');
        } elseif (!$mail->getHtmlContent()) {
            return $this->redirect('updateContent', null, null, [
                'mail' => $mail->getUid(),
                'tabId' => $tabId
            ]);
        }
        if (!$mail->getSendOptions()->hasFormat(SendFormat::PLAIN)) {
            $mail->setPlainContent('');
        } elseif (!$mail->getPlainContent()) {
            return $this->redirect('updateContent', null, null, [
                'mail' => $mail->getUid(),
                'tabId' => $tabId
            ]);
        }

        $mail->setStep(2);
        $this->mailRepository->update($mail);
        $this->mailRepository->persist();

        $fieldGroups = [];
        $groups = [
            'general' => ['subject', 'fromEmail', 'fromName', 'organisation', 'attachment'],
            'headers' => ['replyToEmail', 'replyToName', 'returnPath', 'priority'],
            'content' => ['sendOptions', 'includeMedia', 'redirect', 'redirectAll', 'authCodeFields'],
            'source' => ['type', 'renderedSize', 'page', 'sysLanguageUid', 'plainParams', 'htmlParams'],
        ];

        if (isset($this->userTSConfiguration['settings.'])) {
            foreach ($this->userTSConfiguration['settings.'] as $groupName => $fields) {
                $fieldsArray = GeneralUtility::trimExplode(',', $fields, true);
                if ($fieldsArray) {
                    $groups[$groupName] = $fieldsArray;
                } else {
                    unset($groups[$groupName]);
                }
            }
        }

        if ($this->userTSConfiguration['settingsWithoutTabs'] ?? count($groups) === 1) {
            $this->view->assign('settingsWithoutTabs', true);
        }

        if (isset($this->userTSConfiguration['hideEditAllSettingsButton'])) {
            $this->view->assign('hideEditAllSettingsButton', (bool)$this->userTSConfiguration['hideEditAllSettingsButton']);
        }

        $readOnly = ['type', 'renderedSize'];
        if (isset($this->userTSConfiguration['readOnlySettings'])) {
            $readOnly = GeneralUtility::trimExplode(',', $this->userTSConfiguration['readOnlySettings'], true);
        }

        if ($mail->isExternal()) {
            $groups = ArrayUtility::removeArrayEntryByValue($groups, 'sysLanguageUid');
            $groups = ArrayUtility::removeArrayEntryByValue($groups, 'page');
            if (!$mail->getHtmlParams() || $mail->isQuickMail()) {
                $groups = ArrayUtility::removeArrayEntryByValue($groups, 'htmlParams');
            }
            if (!$mail->getPlainParams() || $mail->isQuickMail()) {
                $groups = ArrayUtility::removeArrayEntryByValue($groups, 'plainParams');
            }
            if ($mail->isQuickMail()) {
                $groups = ArrayUtility::removeArrayEntryByValue($groups, 'includeMedia');
            }
        }

        $className = get_class($mail);
        $dataMapFactory = GeneralUtility::makeInstance(DataMapFactory::class);
        $dataMap = $dataMapFactory->buildDataMap($className);
        $tableName = $dataMap->getTableName();
        $reflectionService = GeneralUtility::makeInstance(ReflectionService::class);
        $classSchema = $reflectionService->getClassSchema($className);
        $backendUserIsAllowedToEditMailSettingsRecord = BackendUserUtility::getBackendUser()->check('tables_modify', $tableName);

        foreach ($groups as $groupName => $properties) {
            foreach ($properties as $property) {
                $getter = 'get' . ucfirst($property);
                if (!method_exists($mail, $getter)) {
                    $getter = 'is' . ucfirst($property);
                }
                if (method_exists($mail, $getter)) {
                    $columnName = $dataMap->getColumnMap($classSchema->getProperty($property)->getName())->getColumnName();
                    $rawValue = $mail->$getter();
                    if ($rawValue instanceof SendFormat) {
                        $rawValue = (string)$rawValue;
                    }
                    $value = match ($property) {
                        'type' => ($mail->isQuickMail() ? LanguageUtility::getLL('mail.type.quickMail') : (BackendUtility::getProcessedValue($tableName,
                            $columnName, $rawValue) ?: '')),
                        'sysLanguageUid' => $this->site->getLanguageById((int)$rawValue)->getTitle(),
                        'renderedSize' => GeneralUtility::formatSize((int)$rawValue, 'si') . 'B',
                        'attachment' => $mail->getAttachmentCsv(),
                        default => BackendUtility::getProcessedValue($tableName, $columnName, $rawValue) ?: '',
                    };
                    $fieldGroups[$groupName][$property] = [
                        'title' => TcaUtility::getTranslatedLabelOfTcaField($columnName, $tableName),
                        'value' => $value,
                        'edit' => ($backendUserIsAllowedToEditMailSettingsRecord && in_array($property,
                                $readOnly)) ? false : GeneralUtility::camelCaseToLowerCaseUnderscored($property),
                    ];
                }
            }
        }

        $this->view->assignMultiple([
            'id' => $this->id,
            'activeTabId' => $tabId,
            'mail' => $mail,
            'fieldGroups' => $fieldGroups,
            'navigation' => $this->getNavigation(2, $this->hideCategoryStep($mail)),
        ]);

        if ($mail->isInternal() || $mail->isExternal()) {
            if ($updatePreview && ConfigurationUtility::getExtensionConfiguration('createMailThumbnails')) {
                // add html2canvas stuff
                $this->pageRenderer->addRequireJsConfiguration([
                    'paths' => [
                        'html2canvas' => PathUtility::getPublicResourceWebPath('EXT:mail/Resources/Public/') . 'JavaScript/Contrib/html2canvas.min',
                    ],
                ]);
                $this->pageRenderer->loadRequireJsModule('html2canvas');
                $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Mail/PreviewImage');
                $savePreviewImageAjaxUri = $this->backendUriBuilder->buildUriFromRoute('ajax_mail_save-preview-image', ['mail' => $mail->getUid()]);
                $this->pageRenderer->addJsInlineCode('mail-configuration', 'var savePreviewImageAjaxUri = \'' . $savePreviewImageAjaxUri . '\'');
            }
        }

        $this->moduleTemplate->setContent($this->view->render());

        if ($updatePreview && !$mail->isQuickMail()) {
            if ($mail->isInternal()) {
                $messageValue = BackendUtility::getProcessedValue($tableName, 'page', $mail->getPage());
            } else {
                $messageValue = trim(BackendUtility::getProcessedValue($tableName, 'plainParams',
                        $mail->getPlainParams()) . ' / ' . BackendUtility::getProcessedValue($tableName, 'htmlParams', $mail->getHtmlParams()), ' /');
            }
            $this->addJsNotification(
                sprintf(LanguageUtility::getLL('mail.wizard.notification.fetchSuccessfully.message'),
                    $messageValue),
                LanguageUtility::getLL('general.notification.severity.success.title'));
        }

        return $this->htmlResponse($this->moduleTemplate->renderContent());
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
     */
    public function savePreviewImageAction(ServerRequestInterface $request): ResponseInterface
    {
        // language service has to be set here, because method is called by ajax route, which doesn't call initializeAction
        LanguageUtility::getLanguageService()->includeLLFile('EXT:mail/Resources/Private/Language/Modules.xlf');
        $dataUrl = $request->getBody()->getContents() ?? null;
        $mailUid = (int)($request->getQueryParams()['mail'] ?? 0);

        if ($dataUrl && $mailUid) {
            $mail = $this->mailRepository->findByUid($mailUid);
            $mail->setPreviewImage($dataUrl);
            $this->mailRepository->update($mail);
            $this->mailRepository->persist();
            return $this->jsonResponse(json_encode([
                'title' => LanguageUtility::getLL('general.notification.severity.success.title'),
                'message' => LanguageUtility::getLL('mail.wizard.notification.previewImageSaved.message'),
            ]));
        }

        return $this->responseFactory->createResponse(400)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withBody($this->streamFactory->createStream(json_encode([
                'title' => LanguageUtility::getLL('general.notification.severity.error.title'),
                'message' => LanguageUtility::getLL('mail.wizard.notification.previewImageCreationFailed.message'),
            ])));
    }

    /**
     * @param Mail $mail
     * @return ResponseInterface
     * @throws IllegalObjectTypeException
     * @throws RouteNotFoundException
     * @throws UnknownObjectException
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function categoriesAction(Mail $mail): ResponseInterface
    {
        $mail->setStep(3);
        $this->mailRepository->update($mail);
        $this->mailRepository->persist();

        $data = [];
        $rows = GeneralUtility::makeInstance(TtContentRepository::class)->findByPidAndSysLanguageUid($mail->getPage(), $mail->getSysLanguageUid());


        if ($rows) {
            $data = [
                'rows' => [],
            ];

            $colPos = 9999;
            $sysCategoryMmRepository = GeneralUtility::makeInstance(SysCategoryMmRepository::class);
            foreach ($rows as $contentElementData) {
                $categoriesRow = [];
                $contentElementCategories = $sysCategoryMmRepository->findByUidForeignTableNameFieldName($contentElementData['uid'], 'tt_content');

                foreach ($contentElementCategories as $contentElementCategory) {
                    $categoriesRow[] = (int)$contentElementCategory['uid_local'];
                }

                if ($colPos !== (int)$contentElementData['colPos']) {
                    $data['rows'][] = [
                        'colPos' => BackendUtility::getProcessedValue('tt_content', 'colPos', $contentElementData['colPos']),
                    ];
                    $colPos = (int)$contentElementData['colPos'];
                }

                $categories = [];
                $ttContentPageTsConfig = BackendUtility::getTCEFORM_TSconfig('tt_content', $contentElementData);
                if (is_array($ttContentPageTsConfig['categories'] ?? false)) {
                    $configTreeStartingPoints = $ttContentPageTsConfig['categories']['config.']['treeConfig.']['startingPoints'] ?? false;
                    if ($configTreeStartingPoints !== false) {
                        $configTreeStartingPointsArray = GeneralUtility::intExplode(',', $configTreeStartingPoints, true);
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
                }

                $data['rows'][] = [
                    'uid' => $contentElementData['uid'],
                    'header' => $contentElementData['header'],
                    'CType' => $contentElementData['CType'],
                    'list_type' => $contentElementData['list_type'],
                    'bodytext' => empty($contentElementData['bodytext']) ? '' : GeneralUtility::fixed_lgd_cs(strip_tags($contentElementData['bodytext']), 200),
                    'hasCategory' => (bool)$contentElementData['categories'],
                    'categories' => $categories,
                ];
            }
        }
        $this->view->assignMultiple([
            'data' => $data,
            'mail' => $mail,
            'navigation' => $this->getNavigation(3, $this->hideCategoryStep($mail)),
        ]);
        $this->moduleTemplate->setContent($this->view->render());
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Mail/HighlightContent');
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Mail/UpdateCategoryRestrictions');
        $saveCategoryRestrictionsAjaxUri = $this->backendUriBuilder->buildUriFromRoute('ajax_mail_save-category-restrictions', ['mail' => $mail->getUid()]);
        $this->pageRenderer->addJsInlineCode('mail-configuration', 'var saveCategoryRestrictionsAjaxUri = \'' . $saveCategoryRestrictionsAjaxUri . '\'');
        $this->addDocHeaderHelpButton();

        return $this->htmlResponse($this->moduleTemplate->renderContent());
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function updateCategoryRestrictionsAction(ServerRequestInterface $request): ResponseInterface
    {
        // language service has to be set here, because method is called by ajax route, which doesn't call initializeAction
        LanguageUtility::getLanguageService()->includeLLFile('EXT:mail/Resources/Private/Language/Modules.xlf');
        $mail = $this->mailRepository->findByUid((int)($request->getQueryParams()['mail'] ?? 0));
        $contentCategories = json_decode($request->getBody()->getContents() ?? null, true);
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
                return $this->responseFactory->createResponse(400)
                    ->withHeader('Content-Type', 'application/json; charset=utf-8')
                    ->withBody($this->streamFactory->createStream(json_encode([
                        'title' => LanguageUtility::getLL('general.notification.severity.error.title'),
                        'message' => LanguageUtility::getLL('mail.wizard.notification.updateContentFailed.message'),
                    ])));
            }

            return $this->jsonResponse(json_encode([
                'title' => LanguageUtility::getLL('mail.wizard.notification.categoriesUpdated.title'),
                'message' => LanguageUtility::getLL('mail.wizard.notification.categoriesUpdated.message'),
            ]));
        }

        return $this->responseFactory->createResponse(400)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withBody($this->streamFactory->createStream(json_encode([
                'title' => LanguageUtility::getLL('general.notification.severity.error.title'),
                'message' => LanguageUtility::getLL('mail.wizard.notification.categoryRestrictionSaveFailed.message'),
            ])));
    }

    /**
     * @param Mail $mail
     * @return ResponseInterface
     * @throws IllegalObjectTypeException
     * @throws InvalidQueryException
     * @throws UnknownObjectException
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws Exception
     */
    public function testMailAction(Mail $mail): ResponseInterface
    {
        $mail->setStep($this->hideCategoryStep($mail) ? 3 : 4);
        $this->mailRepository->update($mail);
        $this->mailRepository->persist();

        $data = [];
        $ttAddressRepository = GeneralUtility::makeInstance(AddressRepository::class);
        $frontendUsersRepository = GeneralUtility::makeInstance(FrontendUserRepository::class);

        if ($this->pageTSConfiguration['testTtAddressUids'] ?? false) {
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
                                foreach ($recipients as $recipient) {
                                    $data['mailGroups'][$testMailGroup->getUid()]['groups'][$recipientGroup][] = $ttAddressRepository->findByUid($recipient);
                                }
                                break;
                        }
                    }
                }
            }
        }

        $hideCategoryStep = $this->hideCategoryStep($mail);

        $this->view->assignMultiple([
            'data' => $data,
            'navigation' => $this->getNavigation($hideCategoryStep ? 3 : 4, $hideCategoryStep),
            'mailUid' => $mail->getUid(),
            'title' => $mail->getSubject(),
            'backendUser' => [
                'name' => BackendUserUtility::getBackendUser()->user['realName'] ?? '',
                'email' => BackendUserUtility::getBackendUser()->user['email'] ?? '',
                'uid' => BackendUserUtility::getBackendUser()->user['uid'] ?? '',
            ],
        ]);
        $this->moduleTemplate->setContent($this->view->render());
        $this->addDocHeaderHelpButton();

        return $this->htmlResponse($this->moduleTemplate->renderContent());
    }

    /**
     * @param Mail $mail
     * @param string $recipients
     * @return ResponseInterface
     */
    public function sendTestMailAction(Mail $mail, string $recipients = ''): ResponseInterface
    {
        // normalize addresses:
        $addressList = RecipientUtility::normalizeListOfEmailAddresses($recipients);

        if ($addressList) {
            $this->mailerService->start();
            $this->mailerService->prepare($mail->getUid());
            $this->mailerService->setSubjectPrefix($this->pageTSConfiguration['testMailSubjectPrefix'] ?? '');
            $this->mailerService->sendSimpleMail($addressList);
        }

        ViewUtility::addNotificationSuccess(
            sprintf(LanguageUtility::getLL('mail.wizard.notification.testMailSent.message'), $addressList),
            LanguageUtility::getLL('mail.wizard.notification.testMailSent.title')
        );
        return $this->redirect('testMail', null, null, ['mail' => $mail->getUid()]);
    }

    /**
     * @param Mail $mail
     * @return ResponseInterface
     * @throws IllegalObjectTypeException
     * @throws InvalidQueryException
     * @throws UnknownObjectException
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws Exception
     */
    public function scheduleSendingAction(Mail $mail): ResponseInterface
    {
        $mail->setStep($this->hideCategoryStep($mail) ? 4 : 5);
        $this->mailRepository->update($mail);
        $this->mailRepository->persist();

        $hideCategoryStep = $this->hideCategoryStep($mail);
        $this->view->assignMultiple([
            'groups' => $this->recipientService->getFinalSendingGroups($this->id),
            'navigation' => $this->getNavigation($hideCategoryStep ? 4 : 5, $hideCategoryStep),
            'mail' => $mail,
            'mailUid' => $mail->getUid(),
            'title' => $mail->getSubject(),
            'useTypo3DateTimeWebComponent' => $this->typo3MajorVersion > 11,
        ]);
        $this->moduleTemplate->setContent($this->view->render());

        if ($this->typo3MajorVersion < 12) {
            $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/DateTimePicker');
        } else {
            $this->pageRenderer->loadJavaScriptModule('@typo3/backend/form-engine/element/datetime-element.js');
        }
        $this->addDocHeaderHelpButton();

        return $this->htmlResponse($this->moduleTemplate->renderContent());
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
                ->setTypeConverterOption(DateTimeImmutableConverter::class, DateTimeImmutableConverter::CONFIGURATION_DATE_FORMAT, $format);
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

        $mail->setRecipients($this->recipientService->getRecipientsUidListsGroupedByRecipientSource($mail->getRecipientGroups()));

        if ($mail->getNumberOfRecipients() === 0) {
            ViewUtility::addNotificationWarning(
                LanguageUtility::getLL('mail.wizard.notification.noRecipients.message'),
                LanguageUtility::getLL('general.notification.severity.warning.title')
            );

            return $this->redirect('scheduleSending', null, null, ['mail' => $mail]);
        }

        if ($this->typo3MajorVersion > 11) {
            // scheduled timezone is utc and must be converted to server time zone
            $scheduled = $mail->getScheduled();
            $serverTimeZone = @date_default_timezone_get();
            $scheduledWithCorrectTimeZone = $scheduled->setTimezone(new \DateTimeZone($serverTimeZone))->setTime((int)$scheduled->format('H'),
                (int)$scheduled->format('i'));
            $mail->setScheduled($scheduledWithCorrectTimeZone);
        }

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
            $buttonBar->addButton($configurationButton, ButtonBar::BUTTON_POSITION_RIGHT, 1);
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
