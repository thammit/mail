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
use MEDIAESSENZ\Mail\Type\Bitmask\SendFormat;
use MEDIAESSENZ\Mail\Utility\BackendUserUtility;
use MEDIAESSENZ\Mail\Utility\LanguageUtility;
use MEDIAESSENZ\Mail\Utility\RecipientUtility;
use MEDIAESSENZ\Mail\Utility\TcaUtility;
use MEDIAESSENZ\Mail\Utility\TypoScriptUtility;
use MEDIAESSENZ\Mail\Utility\ViewUtility;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
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
    private string $cshKey = '_MOD_Mail_Mail';

    public function noPageSelectedAction(): ResponseInterface
    {
        ViewUtility::addWarningToFlashMessageQueue('Please select a mail page in the page tree.', 'No valid page selected');
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setContent($this->view->render());

        return $this->htmlResponse($moduleTemplate->renderContent());
    }

    /**
     * @return ResponseInterface
     * @throws DBALException
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws StopActionException
     */
    public function indexAction(): ResponseInterface
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
        if (isset($userTSConfig['tx_directmail.']['hideTabs'])) {
            $hidePanel = GeneralUtility::trimExplode(',', $userTSConfig['tx_directmail.']['hideTabs']);
            foreach ($hidePanel as $hideTab) {
                $panels = ArrayUtility::removeArrayEntryByValue($panels, $hideTab);
            }
        }
        if (!isset($userTSConfig['tx_directmail.']['defaultTab'])) {
            $userTSConfig['tx_directmail.']['defaultTab'] = Constants::PANEL_OPEN;
        }

        $panelData = [];
        foreach ($panels as $panel) {
            $open = $userTSConfig['tx_directmail.']['defaultTab'] == $panel;
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
        $newMail = $mailFactory->fromExternalUrls($subject, $htmlUrl, $plainTextUrl);
        if ($newMail instanceof Mail) {
            $persistenceManager = GeneralUtility::makeInstance(PersistenceManager::class);
            $persistenceManager->add($newMail);
            $persistenceManager->persistAll();
            $this->redirect('settings', null, null, ['mail' => $newMail->getUid()]);
        }

        ViewUtility::addErrorToFlashMessageQueue(LanguageUtility::getLL('dmail_external_html_uri_is_invalid') . ' Requested URLs: ' . $htmlUrl . ' / ' . $plainTextUrl,
            LanguageUtility::getLL('dmail_error'), true);
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
     * @throws IllegalObjectTypeException
     * @throws StopActionException
     * @throws UnknownObjectException
     */
    public function openMailAction(Mail $mail): void
    {
        $mailFactory = MailFactory::forStorageFolder($this->id);

        if ($mail->isExternal()) {
            // it's a quick/external mail
            if (str_starts_with($mail->getHtmlParams(), 'http') || str_starts_with($mail->getPlainParams(), 'http')) {
                // it's an external mail -> fetch content again
                $newMail = $mailFactory->fromExternalUrls($mail->getSubject(), $mail->getHtmlParams(), $mail->getPlainParams());
                if ($newMail instanceof Mail) {
                    // copy new fetch content and charset to current mail record
                    $mail->setMailContent($newMail->getMailContent());
                    $mail->setRenderedSize($newMail->getRenderedSize());
                    $mail->setCharset($newMail->getCharset());
                    $this->mailRepository->update($mail);
                }
            }
            $this->redirect('settings', null, null, ['mail' => $mail->getUid()]);
        } else {
            $newMail = $mailFactory->fromInternalPage($mail->getPage(), $mail->getSysLanguageUid());
            if ($newMail instanceof Mail) {
                // copy new fetch content and charset to current mail record
                $mail->setMailContent($newMail->getMailContent());
                $mail->setRenderedSize($newMail->getRenderedSize());
                $this->mailRepository->update($mail);
                $this->redirect('settings', null, null, ['mail' => $mail->getUid()]);
            }
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
        ViewUtility::addOkToFlashMessageQueue('', LanguageUtility::getLL('dmail_wiz2_fetch_success'));
        $data = [];
        $table = 'tx_mail_domain_model_mail';
        $groups = [
            'composition' => ['type', 'sysLanguageUid', 'page', 'plainParams', 'htmlParams', 'attachment', 'renderedSize'],
            'headers' => ['subject', 'fromEmail', 'fromName', 'replyToEmail', 'replyToName', 'returnPath', 'organisation', 'priority', 'encoding'],
            'sending' => ['sendOptions', 'includeMedia', 'flowedFormat', 'redirect', 'redirectAll', 'authCodeFields'],
        ];

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
                    $data[$groupName][] = [
                        'title' => TcaUtility::getTranslatedLabelOfTcaField('attachment', $table),
                        'value' => $value,
                    ];
                } else {
                    if (method_exists($mail, $getter)) {
                        $rawValue = $mail->$getter();
                        if ($rawValue instanceof SendFormat) {
                            $rawValue = (string)$rawValue;
                        }
                        $data[$groupName][] = [
                            'title' => TcaUtility::getTranslatedLabelOfTcaField($columnName, $table),
                            'value' => htmlspecialchars((string)BackendUtility::getProcessedValue($tableName, $columnName, $rawValue)),
                        ];
                    }
                }
            }
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
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setContent($this->view->render());

        return $this->htmlResponse($moduleTemplate->renderContent());
    }

    /**
     * @param Mail $mail
     * @return ResponseInterface
     * @throws DBALException
     * @throws InvalidQueryException
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function categoriesAction(Mail $mail): ResponseInterface
    {
        $data = [];
        $rows = GeneralUtility::makeInstance(TtContentRepository::class)->findByPidAndSysLanguageUid($mail->getPage(), $mail->getSysLanguageUid());

        if ($rows) {
            $data = [
                'subtitle' => BackendUtility::cshItem($this->cshKey, 'assign_categories'),
                'rows' => [],
            ];

            $colPos = 9999;
//            $ttContentCategoryMmRepository = GeneralUtility::makeInstance(OldTtContentCategoryMmRepository::class);
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
            'navigation' => $this->getNavigation(3, $this->hideCategoryStep($mail))
        ]);
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setContent($this->view->render());
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
//                $data['tt_content'][$recUid]['module_sys_dmail_category'] = implode(',', $enabled);
                $data['tt_content'][$recUid]['categories'] = implode(',', $enabled);
            }

            $dataHandler = $this->getDataHandler();
            $dataHandler->start($data, []);
            $dataHandler->process_datamap();

            // remove cache
            $dataHandler->clear_cacheCmd($mail->getPage());
        }

        $this->redirect('categories', null, null, ['mail' => $mail->getUid()]);
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
            'backendUser' => [
                'name' => BackendUserUtility::getBackendUser()->user['realName'] ?? '',
                'email' => BackendUserUtility::getBackendUser()->user['email'] ?? '',
                'uid' => BackendUserUtility::getBackendUser()->user['uid'] ?? '',
            ]
        ]);
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setContent($this->view->render());
        return $this->htmlResponse($moduleTemplate->renderContent());
    }

    /**
     * @param Mail $mail
     * @param string $recipients
     * @throws StopActionException
     * @throws TransportExceptionInterface
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
            ViewUtility::addOkToFlashMessageQueue(
                LanguageUtility::getLL('send_recipients') . ' ' . htmlspecialchars($addressList),
                LanguageUtility::getLL('testMailSent'),
                true
            );
        }
        $this->redirect('testMail', null, null, ['mail' => $mail->getUid()]);
    }

    /**
     * @param Mail $mail
     * @return ResponseInterface
     * @throws DBALException
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public function scheduleSendingAction(Mail $mail): ResponseInterface
    {
        $hideCategoryStep = $this->hideCategoryStep($mail);
        $this->view->assignMultiple([
            'data' => $this->recipientService->getFinalSendingGroups($this->id, $this->userTable),
            'navigation' => $this->getNavigation($hideCategoryStep ? 4 : 5, $hideCategoryStep),
            'mailUid' => $mail->getUid(),
        ]);
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setContent($this->view->render());
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/Tooltip');
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/DateTimePicker');

        return $this->htmlResponse($moduleTemplate->renderContent());
    }

    /**
     * @param Mail $mail
     * @param array $groups
     * @param string $distributionTime
     * @throws DBALException
     * @throws StopActionException
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws \Doctrine\DBAL\Exception
     * @throws \Exception
     */
    public function finishAction(Mail $mail, array $groups, string $distributionTime): void
    {
        $groups = array_keys(array_filter($groups));
        $distributionTime = new DateTimeImmutable($distributionTime);
        $queryInfo['id_lists'] = $this->recipientService->getRecipientsUidListsGroupedByTables($this->groupRepository->findByUidList($groups)->toArray());

        // Update the record:
        $mail->setRecipientGroups(implode(',', $groups))
            ->setScheduled($distributionTime)
            ->setQueryInfo(serialize($queryInfo))
            ->setSent(true);

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

        ViewUtility::addOkToFlashMessageQueue(
            sprintf(LanguageUtility::getLL('send_was_scheduled_for'), $mail->getSubject(), BackendUtility::datetime($mail->getScheduled()->getTimestamp())),
            LanguageUtility::getLL('send_was_scheduled'), true
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
        $this->redirect('index');
    }

    protected function hideCategoryStep(Mail $mail = null): bool
    {
        $userTSConfig = TypoScriptUtility::getUserTSConfig();
        return (($mail ?? false) && $mail->isExternal()) || (isset($userTSConfig['tx_directmail.']['hideSteps']) && $userTSConfig['tx_directmail.']['hideSteps'] === 'cat');
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
