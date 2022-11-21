<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Controller;

use DateTimeImmutable;
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
use MEDIAESSENZ\Mail\Utility\LanguageUtility;
use MEDIAESSENZ\Mail\Utility\RecipientUtility;
use MEDIAESSENZ\Mail\Utility\TcaUtility;
use MEDIAESSENZ\Mail\Utility\ViewUtility;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Routing\UnableToLinkToPageException;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Exception\StopActionException;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapFactory;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\CMS\Extbase\Reflection\ClassSchema\Exception\NoSuchPropertyException;
use TYPO3\CMS\Extbase\Reflection\Exception\UnknownClassException;
use TYPO3\CMS\Extbase\Reflection\ReflectionService;

class MailController extends AbstractController
{
    public function noPageSelectedAction(): ResponseInterface
    {
        ViewUtility::addWarningToFlashMessageQueue('Please select a mail page in the page tree.', 'No valid page selected');
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setContent($this->view->render());

        return $this->htmlResponse($moduleTemplate->renderContent());
    }

    /**
     * @param array $notification
     * @return ResponseInterface
     * @throws StopActionException
     */
    public function indexAction(array $notification = []): ResponseInterface
    {
        if ($this->id === 0) {
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

        $panels = [Constants::PANEL_INTERNAL, Constants::PANEL_EXTERNAL, Constants::PANEL_QUICK_MAIL, Constants::PANEL_OPEN];
        if ($this->userTSConfiguration['hideTabs'] ?? false) {
            $hidePanel = GeneralUtility::trimExplode(',', $this->userTSConfiguration['hideTabs']);
            foreach ($hidePanel as $hideTab) {
                $panels = ArrayUtility::removeArrayEntryByValue($panels, $hideTab);
            }
        }
        $defaultTab = Constants::PANEL_OPEN;
        if ($this->userTSConfiguration['defaultTab'] ?? false) {
            if (in_array($this->userTSConfiguration['defaultTab'], $panels)) {
                $defaultTab = $this->userTSConfiguration['defaultTab'];
            }
        }

        $panelData = [];
        foreach ($panels as $panel) {
            $open = $defaultTab == $panel;
            switch ($panel) {
                case Constants::PANEL_OPEN:
                    $panelData['open'] = [
                        'open' => $open,
                        'data' => $this->mailRepository->findOpenByPid($this->id)
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
            'navigation' => $this->getNavigation(1, $this->hideCategoryStep()),
            'mailSysFolderUid' => $this->id,
            'backendUser' => [
                'name' => BackendUserUtility::getBackendUser()->user['realName'] ?? '',
                'email' => BackendUserUtility::getBackendUser()->user['email'] ?? '',
                'uid' => BackendUserUtility::getBackendUser()->user['uid'] ?? '',
            ],
        ]);

        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setContent($this->view->render());

        if ($notification) {
            $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/Notification');
            $this->pageRenderer->addJsInlineCode('mail-notifications', 'top.TYPO3.Notification.' . ($notification['severity'] ?? 'success') . '(\'' . ($notification['title'] ?? '') . '\', \'' . ($notification['message'] ?? '') . '\');');
        }

        return $this->htmlResponse($moduleTemplate->renderContent());
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
            $persistenceManager = GeneralUtility::makeInstance(PersistenceManager::class);
            $persistenceManager->add($newMail);
            $persistenceManager->persistAll();
            $this->redirect('settings', null, null, ['mail' => $newMail->getUid()]);
        }
        ViewUtility::addErrorToFlashMessageQueue('Could not generate mail from internal page.', LanguageUtility::getLL('dmail_error'), true);
        $this->redirect('index');
    }

    /**
     * @param string $subject
     * @param string $htmlUrl
     * @param string $plainTextUrl
     * @return void
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws StopActionException
     */
    public function createMailFromExternalUrlsAction(string $subject, string $htmlUrl, string $plainTextUrl): void
    {
        $mailFactory = MailFactory::forStorageFolder($this->id);
        try {
            $newMail = $mailFactory->fromExternalUrls($subject, $htmlUrl, $plainTextUrl);
            if ($newMail instanceof Mail) {
                $persistenceManager = GeneralUtility::makeInstance(PersistenceManager::class);
                $persistenceManager->add($newMail);
                $persistenceManager->persistAll();
                $this->redirect('settings', null, null, ['mail' => $newMail->getUid()]);
            }
        } catch (ExtensionConfigurationExtensionNotConfiguredException|ExtensionConfigurationPathDoesNotExistException) {}
        catch (HtmlContentFetchFailedException|PlainTextContentFetchFailedException) {
            $this->redirect('index', null, null, [
                'notification' => [
                    'severity' => 'error',
                    'message' => sprintf(LanguageUtility::getLL('mail.wizard.notification.externalUrlInvalid.message'), $htmlUrl . ' / ' . $plainTextUrl),
                    'title' => LanguageUtility::getLL('mail.wizard.notification.severity.error.title')
                ]
            ]);
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
            $persistenceManager = GeneralUtility::makeInstance(PersistenceManager::class);
            $persistenceManager->add($newMail);
            $persistenceManager->persistAll();
            $this->redirect('settings', null, null, ['mail' => $newMail->getUid()]);
        }
        $this->redirect('index');
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
            $mail->setSubject($newMail->getSubject());
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
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws NoSuchPropertyException
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
            'data' => $data,
            'allowEdit' => BackendUserUtility::getBackendUser()->check('tables_modify', $tableName),
            'isSent' => $mail->isSent(),
            'title' => $mail->getSubject(),
            'mailUid' => $mail->getUid(),
            'table' => $table,
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
                    '&html2canvas=1&L=' . $mail->getSysLanguageUid()
                );
                $this->view->assign('htmlToCanvasIframeSrc', $targetUrl);
            } catch (UnableToLinkToPageException $e) {
            }
        }

        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setContent($this->view->render());
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/Notification');

        if ($mail->isInternal()) {
            $message = sprintf(LanguageUtility::getLL('mail.wizard.notification.fetchSuccessfully.message'), $data['general']['page']['value']);
            $this->pageRenderer->addJsInlineCode('mail-notifications', 'top.TYPO3.Notification.success(\'\', \'' . $message . '\');');
        } elseif (!$mail->isQuickMail()) {
            $message = sprintf(LanguageUtility::getLL('mail.wizard.notification.fetchSuccessfully.message'), trim(($data['general']['plainParams']['value'] ?? '') . ' / ' . ($data['general']['htmlParams']['value'] ?? ''), ' /'));
            $this->pageRenderer->addJsInlineCode('mail-notifications', 'top.TYPO3.Notification.success(\'\', \'' . $message . '\');');
        }

        return $this->htmlResponse($moduleTemplate->renderContent());
    }

    /**
     * @param Mail $mail
     * @param array $notification
     * @return ResponseInterface
     * @throws DBALException
     * @throws InvalidQueryException
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function categoriesAction(Mail $mail, array $notification = []): ResponseInterface
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
                $pageTsConfig = BackendUtility::getTCEFORM_TSconfig('tt_content', $contentElementData);
                if (is_array($pageTsConfig['categories'])) {
                    $pidCsvList = $pageTsConfig['categories']['PAGE_TSCONFIG_IDLIST'] ?? [];
                    if ($pidCsvList) {
                        $pidList = GeneralUtility::intExplode(',', $pidCsvList, true);
                        $ttContentCategories = $this->categoryRepository->findByPidList($pidList)->toArray();
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
            'mailUid' => $mail->getUid(),
            'title' => $mail->getSubject(),
            'navigation' => $this->getNavigation(3, $this->hideCategoryStep($mail))
        ]);
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setContent($this->view->render());

        if ($notification) {
            $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/Notification');
            $this->pageRenderer->addJsInlineCode('mail-notifications', 'top.TYPO3.Notification.' . ($notification['severity'] ?? 'success') . '(\'' . ($notification['title'] ?? '') . '\', \'' . ($notification['message'] ?? '') . '\');');
        }

        return $this->htmlResponse($moduleTemplate->renderContent());
    }

    /**
     * @param Mail $mail
     * @param array $categories
     * @return void
     * @throws StopActionException
     */
    public function updateCategoriesAction(Mail $mail, array $categories = []): void
    {
        if ($categories) {
            $data = [];
            foreach ($categories as $recUid => $recValues) {
                $enabled = [];
                foreach ($recValues as $k => $b) {
                    if ($b) {
                        $enabled[] = $k;
                    }
                }
                $data['tt_content'][$recUid]['categories'] = implode(',', $enabled);
            }

            $dataHandler = $this->getDataHandler();
            $dataHandler->start($data, []);
            $dataHandler->process_datamap();

            // remove cache
            $dataHandler->clear_cacheCmd($mail->getPage());
        }

        $this->redirect('categories', null, null, ['mail' => $mail->getUid(),
            'notification' => [
                'severity' => 'success',
                'message' => LanguageUtility::getLL('mail.wizard.notification.categoriesUpdated.message'),
                'title' => LanguageUtility::getLL('mail.wizard.notification.categoriesUpdated.title')
            ]
        ]);
    }

    /**
     * @param Mail $mail
     * @param array $notification
     * @return ResponseInterface
     * @throws DBALException
     * @throws IllegalObjectTypeException
     * @throws InvalidQueryException
     * @throws UnknownObjectException
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public function testMailAction(Mail $mail, array $notification = []): ResponseInterface
    {
        $data = [];
        $ttAddressRepository = GeneralUtility::makeInstance(AddressRepository::class);
        $frontendUsersRepository = GeneralUtility::makeInstance(FrontendUserRepository::class);

        if ($this->pageTSConfiguration['test_tt_address_uids'] ?? false) {
            $demand = new Demand();
            $demand->setSingleRecords($this->pageTSConfiguration['test_tt_address_uids']);
            $data['ttAddress'] = $ttAddressRepository->getAddressesByCustomSorting($demand);
        }

        if ($this->pageTSConfiguration['test_mail_group_uids'] ?? false) {
            $mailGroupUids = GeneralUtility::intExplode(',', $this->pageTSConfiguration['test_mail_group_uids']);
            $data['mailGroups'] = [];
            foreach ($mailGroupUids as $mailGroupUid) {
                /** @var Group $testMailGroup */
                $testMailGroup = $this->groupRepository->findByUid($mailGroupUid);
                $data['mailGroups'][$testMailGroup->getUid()]['title'] = $testMailGroup->getTitle();
                $recipientGroups = $this->recipientService->getRecipientsUidListsGroupedByTable($testMailGroup);
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
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setContent($this->view->render());

        if ($notification) {
            $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/Notification');
            $this->pageRenderer->addJsInlineCode('mail-notifications', 'top.TYPO3.Notification.' . ($notification['severity'] ?? 'success') . '(\'' . ($notification['title'] ?? '') . '\', \'' . ($notification['message'] ?? '') . '\');');
        }

        return $this->htmlResponse($moduleTemplate->renderContent());
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
            $this->mailerService->setTestMail(true);
            $this->mailerService->sendSimpleMail($addressList);
        }
        $this->redirect('testMail', null, null, [
            'mail' => $mail->getUid(),
            'notification' => [
                'severity' => 'success',
                'message' => sprintf(LanguageUtility::getLL('mail.wizard.notification.testMailSent.message'), $addressList),
                'title' => LanguageUtility::getLL('mail.wizard.notification.testMailSent.title')
            ]
        ]);
    }

    /**
     * @param Mail $mail
     * @param array $notification
     * @return ResponseInterface
     * @throws DBALException
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public function scheduleSendingAction(Mail $mail, array $notification = []): ResponseInterface
    {
        $hideCategoryStep = $this->hideCategoryStep($mail);
        $this->view->assignMultiple([
            'groups' => $this->recipientService->getFinalSendingGroups($this->id, $this->userTable),
            'navigation' => $this->getNavigation($hideCategoryStep ? 4 : 5, $hideCategoryStep),
            'mail' => $mail,
            'mailUid' => $mail->getUid(),
            'title' => $mail->getSubject(),
        ]);
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setContent($this->view->render());
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/Tooltip');
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/DateTimePicker');
        if ($notification) {
            $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/Notification');
            $this->pageRenderer->addJsInlineCode('mail-notifications', 'top.TYPO3.Notification.' . ($notification['severity'] ?? 'success') . '(\'' . ($notification['title'] ?? '') . '\', \'' . ($notification['message'] ?? '') . '\');');
        }

        return $this->htmlResponse($moduleTemplate->renderContent());
    }

    public function initializeFinishAction(): void
    {
        $this->arguments['mail']
        ->getPropertyMappingConfiguration()
        ->forProperty('scheduled')
        ->setTypeConverterOption(DateTimeImmutableConverter::class, DateTimeImmutableConverter::CONFIGURATION_DATE_FORMAT, 'H:i d-m-Y');
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
            $this->redirect('scheduleSending', null, null, [
                'mail' => $mail,
                'notification' => [
                    'severity' => 'warning',
                    'message' => LanguageUtility::getLL('mail.wizard.notification.missingRecipientGroup.message'),
                    'title' => LanguageUtility::getLL('mail.wizard.notification.severity.warning.title')
                ]
            ]);
        }

        $mail->setRecipients($this->recipientService->getRecipientsUidListsGroupedByTables($mail->getRecipientGroups()->toArray()));

        if ($mail->getNumberOfRecipients() === 0) {
            $this->redirect('scheduleSending', null, null, [
                'mail' => $mail,
                'notification' => [
                    'severity' => 'warning',
                    'message' => LanguageUtility::getLL('mail.wizard.notification.noRecipients.message'),
                    'title' => LanguageUtility::getLL('mail.wizard.notification.severity.warning.title')
                ]
            ]);
        }

        // Update the record:
        $mail->setSent(true);

//        if (false && $this->isTestMail) {
//            $updateFields['subject'] = ($this->pageTSConfiguration['testmail'] ?? '') . ' ' . $row['subject'];
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
//                sprintf(LanguageUtility::getLL('send_draft_scheduler'), $row['subject'], BackendUtility::datetime($this->distributionTimeStamp)),
//                LanguageUtility::getLL('send_draft_saved'), true
//            );
//        } else {
//            ViewUtility::addOkToFlashMessageQueue(
//                sprintf(LanguageUtility::getLL('send_was_scheduled_for'), $row['subject'], BackendUtility::datetime($this->distributionTimeStamp)),
//                LanguageUtility::getLL('send_was_scheduled'), true
//            );
//        }

        $this->mailRepository->update($mail);

        $this->redirect('index', null, null, [
            'notification' => [
                'severity' => 'success',
                'message' => sprintf(LanguageUtility::getLL('mail.wizard.notification.finished.message'), $mail->getSubject(), BackendUtility::datetime($mail->getScheduled()->getTimestamp())),
                'title' => LanguageUtility::getLL('mail.wizard.notification.finished.title')
            ]
        ]);
    }

    /**
     * @param Mail $mail
     * @throws StopActionException
     * @throws IllegalObjectTypeException
     */
    public function deleteAction(Mail $mail): void
    {
        $this->mailRepository->remove($mail);
        $this->redirect('index', null, null, [
            'notification' => [
                'severity' => 'success',
                'message' => sprintf(LanguageUtility::getLL('mail.wizard.notification.deleted.message'), $mail->getSubject()),
                'title' => LanguageUtility::getLL('mail.wizard.notification.deleted.title')
            ]
        ]);
    }

    protected function hideCategoryStep(Mail $mail = null): bool
    {
        return (($mail ?? false) && $mail->isExternal()) || (isset($this->userTSConfiguration['hideSteps']) && $this->userTSConfiguration['hideSteps'] === 'categories');
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
}
