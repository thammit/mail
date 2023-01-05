<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Controller;

use Doctrine\DBAL\DBALException;
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
use MEDIAESSENZ\Mail\Utility\BackendUserUtility;
use MEDIAESSENZ\Mail\Utility\ConfigurationUtility;
use MEDIAESSENZ\Mail\Utility\LanguageUtility;
use MEDIAESSENZ\Mail\Utility\MailerUtility;
use MEDIAESSENZ\Mail\Utility\RecipientUtility;
use MEDIAESSENZ\Mail\Utility\TcaUtility;
use MEDIAESSENZ\Mail\Utility\ViewUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Resource\Exception\InvalidFileException;
use TYPO3\CMS\Core\Routing\UnableToLinkToPageException;
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
        ViewUtility::addFlashMessageWarning(LanguageUtility::getLL('mail.wizard.notification.noPageSelected.message'), LanguageUtility::getLL('mail.wizard.notification.noPageSelected.title'));
        $this->moduleTemplate->setContent($this->view->render());

        return $this->htmlResponse($this->moduleTemplate->renderContent());
    }

    /**
     * @return ResponseInterface
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws StopActionException
     */
    public function indexAction(): ResponseInterface
    {
        if ($this->id === 0 || ($this->pageInfo['doktype'] !== (int)ConfigurationUtility::getExtensionConfiguration('mailPageTypeNumber') && $this->pageInfo['module'] !== Constants::MAIL_MODULE_NAME)) {
            $this->redirect('noPageSelected');
        }
        if ($this->pageInfo['module'] !== Constants::MAIL_MODULE_NAME) {
            // the currently selected page is not a mail module sys folder
            $openMails = $this->mailRepository->findOpenByPidAndPage($this->pageInfo['pid'], $this->id);
            if ($openMails->count() > 0) {
                // there is already an open mail of this page -> use it
                // Hack, because redirect to pid would not work otherwise (see extbase/Classes/Mvc/Web/Routing/UriBuilder.php line 646)
                $_GET['id'] = $this->pageInfo['pid'];
                $this->redirect('openMail', null, null, ['mail' => $openMails->getFirst()->getUid()], $this->pageInfo['pid']);
            }
            // create a new mail of the page
            // Hack, because redirect to pid would not work otherwise (see extbase/Classes/Mvc/Web/Routing/UriBuilder.php line 646)
            $_GET['id'] = $this->pageInfo['pid'];
            $this->redirect('createMailFromInternalPage', null, null, ['page' => $this->id], $this->pageInfo['pid']);
        }

        $panels = [Constants::PANEL_OPEN, Constants::PANEL_INTERNAL, Constants::PANEL_EXTERNAL, Constants::PANEL_QUICK_MAIL];
        if ($this->userTSConfiguration['hideTabs'] ?? false) {
            $hidePanel = GeneralUtility::trimExplode(',', $this->userTSConfiguration['hideTabs']);
            foreach ($hidePanel as $hideTab) {
                $panels = ArrayUtility::removeArrayEntryByValue($panels, $hideTab);
            }
        }

        $openMails = $this->mailRepository->findOpenByPid($this->id);

        $defaultTab = $openMails->count() > 0 ? Constants::PANEL_OPEN : Constants::PANEL_INTERNAL;
        if ($this->userTSConfiguration['defaultTab'] ?? false) {
            if (in_array($this->userTSConfiguration['defaultTab'], $panels)) {
                $defaultTab = $this->userTSConfiguration['defaultTab'];
            }
        }

        $panelData = [];
        foreach ($panels as $panel) {
            $open = $defaultTab === $panel;
            switch ($panel) {
                case Constants::PANEL_OPEN:
                    $panelData['open'] = [
                        'open' => $open,
                        'data' => $openMails
                    ];
                    break;
                case Constants::PANEL_INTERNAL:
                    $panelData['internal'] = [
                        'open' => $open,
                        'data' => $this->pageRepository->getMenu($this->id)
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
            'navigation' => $this->getNavigation(1, $this->hideCategoryStep()),
            'mailSysFolderUid' => $this->id,
            'backendUser' => [
                'name' => BackendUserUtility::getBackendUser()->user['realName'] ?? '',
                'email' => BackendUserUtility::getBackendUser()->user['email'] ?? '',
                'uid' => BackendUserUtility::getBackendUser()->user['uid'] ?? '',
            ],
        ]);

        $this->moduleTemplate->setContent($this->view->render());
        $this->addDocheaderButtons();
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Mail/PreviewModal');

        return $this->htmlResponse($this->moduleTemplate->renderContent());
    }

    /**
     * @param int $page
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws StopActionException
     */
    public function createMailFromInternalPageAction(int $page): void
    {
        $mailFactory = MailFactory::forStorageFolder($this->id);
        // todo add multi language support
        $newMail = $mailFactory->fromInternalPage($page);
        if ($newMail instanceof Mail) {
            $this->addNewMailAndRedirectToSettings($newMail);
        }

        ViewUtility::addNotificationError(
            'Could not generate mail from internal page.',
            LanguageUtility::getLL('general.notification.severity.error.title')
        );

        $this->redirect('index');
    }

    /**
     * @param string $subject
     * @param string $htmlUrl
     * @param string $plainTextUrl
     * @return void
     * @throws StopActionException
     */
    public function createMailFromExternalUrlsAction(string $subject, string $htmlUrl, string $plainTextUrl): void
    {
        $mailFactory = MailFactory::forStorageFolder($this->id);
        try {
            $newMail = $mailFactory->fromExternalUrls($subject, $htmlUrl, $plainTextUrl);
            if ($newMail instanceof Mail) {
                $this->addNewMailAndRedirectToSettings($newMail);
            }
        } catch (ExtensionConfigurationExtensionNotConfiguredException|ExtensionConfigurationPathDoesNotExistException) {
        } catch (HtmlContentFetchFailedException|PlainTextContentFetchFailedException) {
            ViewUtility::addNotificationError(
                sprintf(LanguageUtility::getLL('mail.wizard.notification.externalUrlInvalid.message'), trim($htmlUrl . ' / ' . $plainTextUrl, ' /')),
                LanguageUtility::getLL('general.notification.severity.error.title')
            );

            $this->redirect('index');
        }

        $this->redirect('index');
    }

    /**
     * @param string $subject
     * @param string $message
     * @param string $fromName
     * @param string $fromEmail
     * @param bool $breakLines
     * @return void
     * @throws StopActionException
     */
    public function createQuickMailAction(string $subject, string $message, string $fromName, string $fromEmail, bool $breakLines): void
    {
        $mailFactory = MailFactory::forStorageFolder($this->id);
        $newMail = $mailFactory->fromText($subject, $message, $fromName, $fromEmail, $breakLines);
        if ($newMail instanceof Mail) {
            $this->addNewMailAndRedirectToSettings($newMail);
        }
        $this->redirect('index');
    }

    /**
     * @throws StopActionException
     */
    protected function addNewMailAndRedirectToSettings(Mail $mail): void
    {
        $persistenceManager = GeneralUtility::makeInstance(PersistenceManager::class);
        $persistenceManager->add($mail);
        $persistenceManager->persistAll();
        $this->redirect('settings', null, null, ['mail' => $mail->getUid()]);
    }

    /**
     * @param Mail $mail
     * @return void
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws HtmlContentFetchFailedException
     * @throws IllegalObjectTypeException
     * @throws PlainTextContentFetchFailedException
     * @throws StopActionException
     * @throws UnknownObjectException
     */
    public function openMailAction(Mail $mail): void
    {
        $mailFactory = MailFactory::forStorageFolder($this->id);
        $newMail = null;
        if ($mail->isExternal()) {
            // it's a quick/external mail
            if (str_starts_with($mail->getHtmlParams(), 'http') || str_starts_with($mail->getPlainParams(), 'http')) {
                // it's an external mail -> fetch content again
                $newMail = $mailFactory->fromExternalUrls($mail->getSubject(), $mail->getHtmlParams(), $mail->getPlainParams());
            } else {
                $this->redirect('settings', null, null, ['mail' => $mail->getUid()]);
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
            $this->redirect('settings', null, null, ['mail' => $mail->getUid()]);
        }
        $this->redirect('index');
    }

    /**
     * @param Mail $mail
     * @return ResponseInterface
     * @throws InvalidFileException
     * @throws NoSuchPropertyException
     * @throws RouteNotFoundException
     * @throws UnknownClassException
     */
    public function settingsAction(Mail $mail): ResponseInterface
    {
        $data = [];
        $table = 'tx_mail_domain_model_mail';
        $groups = [
            'general' => ['type', 'sysLanguageUid', 'page', 'sendOptions', 'plainParams', 'htmlParams', 'attachment', 'renderedSize'],
            'headers' => ['subject', 'fromEmail', 'fromName', 'replyToEmail', 'replyToName', 'returnPath', 'organisation', 'priority'],
            'content' => ['includeMedia', 'encoding', 'redirect', 'redirectAll', 'authCodeFields'],
        ];

        if ($mail->isExternal()) {
            unset($groups['general'][array_search('sysLanguageUid', $groups['general'])]);
            unset($groups['general'][array_search('page', $groups['general'])]);
            if (!$mail->getHtmlParams() || $mail->isQuickMail()) {
                unset($groups['general'][array_search('htmlParams', $groups['general'])]);
            }
            if (!$mail->getPlainParams() || $mail->isQuickMail()) {
                unset($groups['general'][array_search('plainParams', $groups['general'])]);
            }
            if ($mail->isQuickMail()) {
                unset($groups['content'][array_search('includeMedia', $groups['content'])]);
            }
        }

        $className = get_class($mail);
        $dataMapFactory = GeneralUtility::makeInstance(DataMapFactory::class);
        $dataMap = $dataMapFactory->buildDataMap($className);
        $tableName = $dataMap->getTableName();
        $reflectionService = GeneralUtility::makeInstance(ReflectionService::class);
        $classSchema = $reflectionService->getClassSchema($className);

        foreach ($groups as $groupName => $properties) {
            foreach ($properties as $property) {
                $getter = 'get' . ucfirst($property);
                if (!method_exists($mail, $getter)) {
                    $getter = 'is' . ucfirst($property);
                }
                $columnName = $dataMap->getColumnMap($classSchema->getProperty($property)->getName())->getColumnName();
                if ($property === 'attachment') {
                    $value = '';
                    if ($mail->getAttachment()->count() > 0) {
                        $attachments = [];
                        foreach ($mail->getAttachment() as $attachment) {
                            $attachments[] = $attachment->getOriginalResource()->getName();
                        }
                        $value = implode(', ', $attachments);
                    }
                    $data[$groupName][$property] = [
                        'title' => TcaUtility::getTranslatedLabelOfTcaField('attachment', $table),
                        'value' => $value,
                    ];
                } else {
                    if (method_exists($mail, $getter)) {
                        $rawValue = $mail->$getter();
                        if ($rawValue instanceof SendFormat) {
                            $rawValue = (string)$rawValue;
                        }
                        $data[$groupName][$property] = [
                            'title' => TcaUtility::getTranslatedLabelOfTcaField($columnName, $table),
                            'value' => BackendUtility::getProcessedValue($tableName, $columnName, $rawValue),
                        ];
                    }
                }
            }
        }

        if ($mail->isQuickMail()) {
            $data['general']['type']['value'] = LanguageUtility::getLL('mail.type.quickMail');
        }

        $this->view->assignMultiple([
            'mail' => $mail,
            'table' => $table,
            'data' => $data,
            'allowEdit' => BackendUserUtility::getBackendUser()->check('tables_modify', $tableName),
            'navigation' => $this->getNavigation(2, $this->hideCategoryStep($mail))
        ]);

        // Html2canvas stuff
        if ($mail->isInternal()) {
            try {
                $targetUrl = BackendUtility::getPreviewUrl(
                    $mail->getPage(),
                    '',
                    null,
                    '',
                    '',
                    '&mailUid=' . $mail->getUid() . '&L=' . $mail->getSysLanguageUid()
                );
                $this->view->assign('htmlToCanvasIframeSrc', $targetUrl);
            } catch (UnableToLinkToPageException $e) {
            }
            $this->view->assign('mailBody', MailerUtility::getMailBody($mail->getHtmlContent()));
            $this->pageRenderer->addRequireJsConfiguration([
                'paths' => [
                    'html2canvas' => PathUtility::getPublicResourceWebPath('EXT:mail/Resources/Public/') . 'JavaScript/Contrib/html2canvas.min',
                ],
            ]);
            $this->pageRenderer->loadRequireJsModule('html2canvas');
            $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Mail/PreviewImage');
            $backendUriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
            $savePreviewImageAjaxUri = $backendUriBuilder->buildUriFromRoute('ajax_mail_save-preview-image', ['mail' => $mail->getUid()]);
            $this->pageRenderer->addJsInlineCode('mail-configuration', 'var savePreviewImageAjaxUri = \'' . $savePreviewImageAjaxUri . '\'');
        }

        $this->moduleTemplate->setContent($this->view->render());

        if (!$mail->isQuickMail()) {
            $this->addJsNotification(
                sprintf(LanguageUtility::getLL('mail.wizard.notification.fetchSuccessfully.message'),
                    $mail->isInternal() ? $data['general']['page']['value'] :
                        trim(($data['general']['plainParams']['value'] ?? '') . ' / ' . ($data['general']['htmlParams']['value'] ?? ''), ' /')),
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
        $dataUrl = $request->getBody()->getContents() ?? null;
        $mailUid = (int)($request->getQueryParams()['mail'] ?? 0);

        if ($dataUrl && $mailUid) {
            $mail = $this->mailRepository->findByUid($mailUid);
            $mail->setPreviewImage($dataUrl);
            $this->mailRepository->update($mail);
            $this->mailRepository->persist();
            return $this->jsonResponse(json_encode([
                'title' => LanguageUtility::getLL('general.notification.severity.success.title'),
                'message' => LanguageUtility::getLL('mail.wizard.notification.previewImageSaved.message')
            ]));
        }

        return $this->responseFactory->createResponse(400)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withBody($this->streamFactory->createStream(json_encode([
                'title' => LanguageUtility::getLL('general.notification.severity.error.title'),
                'message' => LanguageUtility::getLL('mail.wizard.notification.previewImageCreationFailed.message')
            ])));
    }

    /**
     * @param Mail $mail
     * @return ResponseInterface
     * @throws DBALException
     * @throws InvalidQueryException
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws RouteNotFoundException
     */
    public function categoriesAction(Mail $mail): ResponseInterface
    {
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
            'mailBody' => MailerUtility::getMailBody($mail->getHtmlContent()),
            'navigation' => $this->getNavigation(3, $this->hideCategoryStep($mail))
        ]);
        $this->moduleTemplate->setContent($this->view->render());
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Mail/HighlightContent');
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Mail/UpdateCategoryRestrictions');
        $backendUriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        $saveCategoryRestrictionsAjaxUri = $backendUriBuilder->buildUriFromRoute('ajax_mail_save-category-restrictions', ['mail' => $mail->getUid()]);
        $this->pageRenderer->addJsInlineCode('mail-configuration', 'var saveCategoryRestrictionsAjaxUri = \'' . $saveCategoryRestrictionsAjaxUri . '\'');

        return $this->htmlResponse($this->moduleTemplate->renderContent());
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function updateCategoryRestrictionsAction(ServerRequestInterface $request): ResponseInterface
    {
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
                        'message' => LanguageUtility::getLL('mail.wizard.notification.updateContentFailed.message')
                    ])));
            }

            return $this->jsonResponse(json_encode([
                'title' => LanguageUtility::getLL('mail.wizard.notification.categoriesUpdated.title'),
                'message' => LanguageUtility::getLL('mail.wizard.notification.categoriesUpdated.message')
            ]));
        }

        return $this->responseFactory->createResponse(400)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withBody($this->streamFactory->createStream(json_encode([
                'title' => LanguageUtility::getLL('general.notification.severity.error.title'),
                'message' => LanguageUtility::getLL('mail.wizard.notification.categoryRestrictionSaveFailed.message')
            ])));
    }

    /**
     * @param Mail $mail
     * @return ResponseInterface
     * @throws DBALException
     * @throws IllegalObjectTypeException
     * @throws InvalidQueryException
     * @throws UnknownObjectException
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public function testMailAction(Mail $mail): ResponseInterface
    {
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
            ]
        ]);
        $this->moduleTemplate->setContent($this->view->render());

        return $this->htmlResponse($this->moduleTemplate->renderContent());
    }

    /**
     * @param Mail $mail
     * @param string $recipients
     * @throws StopActionException
     * @throws Exception
     */
    public function sendTestMailAction(Mail $mail, string $recipients = ''): void
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
        $this->redirect('testMail', null, null, ['mail' => $mail->getUid()]);
    }

    /**
     * @param Mail $mail
     * @return ResponseInterface
     * @throws DBALException
     * @throws IllegalObjectTypeException
     * @throws InvalidQueryException
     * @throws UnknownObjectException
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public function scheduleSendingAction(Mail $mail): ResponseInterface
    {
        $hideCategoryStep = $this->hideCategoryStep($mail);
        $this->view->assignMultiple([
            'groups' => $this->recipientService->getFinalSendingGroups($this->id),
            'navigation' => $this->getNavigation($hideCategoryStep ? 4 : 5, $hideCategoryStep),
            'mail' => $mail,
            'mailUid' => $mail->getUid(),
            'title' => $mail->getSubject(),
        ]);
        $this->moduleTemplate->setContent($this->view->render());
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/Tooltip');
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/DateTimePicker');

        return $this->htmlResponse($this->moduleTemplate->renderContent());
    }

    /**
     * @throws NoSuchArgumentException
     */
    public function initializeFinishAction(): void
    {
        if ($this->arguments->hasArgument('mail')) {
            $this->arguments->getArgument('mail')
                ->getPropertyMappingConfiguration()
                ->forProperty('scheduled')
                ->setTypeConverterOption(DateTimeImmutableConverter::class, DateTimeImmutableConverter::CONFIGURATION_DATE_FORMAT, 'H:i d-m-Y');
        }
    }

    /**
     * @param Mail $mail
     * @throws DBALException
     * @throws StopActionException
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws \Doctrine\DBAL\Exception
     * @throws \Exception
     */
    public function finishAction(Mail $mail): void
    {
        if ($mail->getRecipientGroups()->count() === 0) {
            ViewUtility::addNotificationWarning(LanguageUtility::getLL('mail.wizard.notification.missingRecipientGroup.message'), LanguageUtility::getLL('general.notification.severity.warning.title'));
            $this->redirect('scheduleSending', null, null, [
                'mail' => $mail]);
        }

        $mail->setRecipients($this->recipientService->getRecipientsUidListsGroupedByRecipientSource($mail->getRecipientGroups()));

        if ($mail->getNumberOfRecipients() === 0) {
            ViewUtility::addNotificationWarning(
                LanguageUtility::getLL('mail.wizard.notification.noRecipients.message'),
                LanguageUtility::getLL('general.notification.severity.warning.title')
            );

            $this->redirect('scheduleSending', null, null, ['mail' => $mail]);
        }

        // Update the record:
        $mail->setSent(true);

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

        $this->mailRepository->update($mail);

        ViewUtility::addNotificationSuccess(
            sprintf(LanguageUtility::getLL('mail.wizard.notification.finished.message'), $mail->getSubject(), BackendUtility::datetime($mail->getScheduled()->getTimestamp())),
            LanguageUtility::getLL('mail.wizard.notification.finished.title')
        );

        $this->redirect('index');
    }

    /**
     * @param Mail $mail
     * @throws StopActionException
     * @throws IllegalObjectTypeException
     */
    public function deleteAction(Mail $mail): void
    {
        $this->mailRepository->remove($mail);

        ViewUtility::addNotificationSuccess(
            sprintf(LanguageUtility::getLL('mail.wizard.notification.deleted.message'), $mail->getSubject()),
            LanguageUtility::getLL('mail.wizard.notification.deleted.title')
        );

        $this->redirect('index');
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

    protected function addDocheaderButtons(): void
    {
        $buttonBar = $this->moduleTemplate->getDocHeaderComponent()->getButtonBar();
        $shortCutButton = $buttonBar->makeShortcutButton()->setRouteIdentifier('MailMail_MailMail');
        $arguments = [
            'id' => $this->id,
        ];
        $shortCutButton->setArguments($arguments);
        $shortCutButton->setDisplayName('Mail Wizard [' . $this->id . ']');
        $buttonBar->addButton($shortCutButton, ButtonBar::BUTTON_POSITION_RIGHT);
    }
}
