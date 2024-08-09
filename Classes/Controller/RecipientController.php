<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Controller;

use Doctrine\DBAL\Driver\Exception;
use MEDIAESSENZ\Mail\Constants;
use MEDIAESSENZ\Mail\Domain\Model\Dto\RecipientSourceConfigurationDTO;
use MEDIAESSENZ\Mail\Domain\Model\Group;
use MEDIAESSENZ\Mail\Domain\Model\RecipientInterface;
use MEDIAESSENZ\Mail\Domain\Repository\RecipientRepositoryInterface;
use MEDIAESSENZ\Mail\Service\ImportService;
use MEDIAESSENZ\Mail\Type\Enumeration\RecipientGroupType;
use MEDIAESSENZ\Mail\Utility\BackendUserUtility;
use MEDIAESSENZ\Mail\Utility\CsvUtility;
use MEDIAESSENZ\Mail\Utility\LanguageUtility;
use MEDIAESSENZ\Mail\Utility\RecipientUtility;
use MEDIAESSENZ\Mail\Utility\ViewUtility;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Utility\ClassNamingUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;
use TYPO3\CMS\Extbase\Persistence\Repository;

class RecipientController extends AbstractController
{
    protected string $defaultCsvExportFields = 'uid,name,first_name,middle_name,last_name,title,email,phone,www,address,company,city,zip,country,fax,categories,mail_salutation,mail_html,mail_active,tstamp';

    /**
     * @return ResponseInterface
     * @throws Exception
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws IllegalObjectTypeException
     * @throws InvalidQueryException
     * @throws RouteNotFoundException
     * @throws UnknownObjectException
     * @throws \Doctrine\DBAL\Exception
     */
    public function indexAction(): ResponseInterface
    {
        if ($this->id === 0 || $this->pageInfo['module'] !== Constants::MAIL_MODULE_NAME) {
            return $this->handleNoMailModulePageRedirect();
        }

        $data = [];

        $groups = $this->groupRepository->findByPid($this->id);

        /** @var Group $group */
        foreach ($groups as $group) {
            $typeProcessed = BackendUtility::getProcessedValue('tx_mail_domain_model_group', 'type', $group->getType());
            switch ($group->getType()) {
                case RecipientGroupType::PAGES:
                    $typeProcessed .= ' (' . implode(', ', $group->getRecipientSources()) . ')';
                    break;
                case RecipientGroupType::STATIC:
                    $typeProcessed .= ' (' . $group->getStaticList() . ' Records)';
                    break;
            }

            $data[] = [
                'group' => $group,
                'typeProcessed' => $typeProcessed,
                'categories' => $group->getCategories(),
                'count' => $this->recipientService->getNumberOfRecipientsByGroup($group),
            ];
        }

        $this->addDocheaderButtons();
        $this->addLeftDocheaderButtons($this->id, $this->request->getRequestTarget());

        $assignments = [
            'pid' => $this->id,
            'data' => $data,
            'ttAddressIsLoaded' => $this->ttAddressIsLoaded
        ];

        if ($this->typo3MajorVersion < 12) {
            $this->view->assignMultiple($assignments + ['layoutSuffix' => 'V11']);
            $this->moduleTemplate->setContent($this->view->render());
            return $this->htmlResponse($this->moduleTemplate->renderContent());
        }

        $this->moduleTemplate->assignMultiple($assignments);
        return $this->moduleTemplate->renderResponse('Backend/Recipient/Index');
    }

    /**
     * @param Group $group
     * @return ResponseInterface
     * @throws Exception
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws IllegalObjectTypeException
     * @throws InvalidQueryException
     * @throws RouteNotFoundException
     * @throws UnknownObjectException
     * @throws \Doctrine\DBAL\Exception
     */
    public function showAction(Group $group): ResponseInterface
    {
        $recipientSources = [];
        $idLists = $this->recipientService->getRecipientsUidListGroupedByRecipientSource($group, true);

        foreach ($idLists as $recipientSourceIdentifier => $idList) {
            $isCsv = str_starts_with($recipientSourceIdentifier, 'tx_mail_domain_model_group');
            if (!$idList || (!($this->recipientSources[$recipientSourceIdentifier] ?? false) && !$isCsv)) {
                // skip id lists without recipient source configuration if not plain or csv
                continue;
            }
            $recipients = [];
            $editCsvList = 0;
            if ($isCsv) {
                $title = '';
                [$recipientSourceIdentifierWithoutGroupId, $groupUid] = explode(':', $recipientSourceIdentifier);
                $groupOfRecipientSource = $this->groupRepository->findByUid((int)$groupUid);
                if ($groupOfRecipientSource instanceof Group) {
                    $title = $groupOfRecipientSource->getTitle();
                    $title .= $groupOfRecipientSource->getType() === RecipientGroupType::CSV ? ' [CSV]' : ' [Plain list]';
                }
                $table = $recipientSourceIdentifierWithoutGroupId;
                $icon = 'actions-user';
                $recipients = $idList;
                $editCsvList = (int)$groupUid;
            } else {
                /** @var RecipientSourceConfigurationDTO $recipientSourceConfiguration */
                $recipientSourceConfiguration = $this->recipientSources[$recipientSourceIdentifier];
                $title = $recipientSourceConfiguration->title;
                $table = $recipientSourceConfiguration->table;
                $icon = $recipientSourceConfiguration->icon;
                if ($recipientSourceConfiguration->model) {
                    if (class_exists($recipientSourceConfiguration->model) && is_subclass_of($recipientSourceConfiguration->model, RecipientInterface::class)) {
                        $repositoryName = ClassNamingUtility::translateModelNameToRepositoryName($recipientSourceConfiguration->model);
                        /** @var Repository $repository */
                        $repository = GeneralUtility::makeInstance($repositoryName);
                        if ($repository instanceof RecipientRepositoryInterface) {
                            $recipients = $repository->findByUidListAndCategories($idList);
                        }
                    }
                } else {
                    $recipients = $this->recipientService->getRecipientsDataByUidListAndTable($idList, $recipientSourceConfiguration->table);
                }
            }

            $recipientSources[$recipientSourceIdentifier] = [
                'title' => $title,
                'table' => $table,
                'icon' => $icon,
                'recipients' => $recipients,
                'numberOfRecipients' => count($recipients),
                'show' => $table && BackendUserUtility::getBackendUser()->check('tables_select', $table),
                'edit' => $table && BackendUserUtility::getBackendUser()->check('tables_modify', $table),
                'editCsvList' => $table && BackendUserUtility::getBackendUser()->check('tables_modify',
                    $table) ? $editCsvList : 0,
            ];
        }

        $this->addLeftDocheaderBackEditButtons($group->getUid(), $this->request->getRequestTarget());
        $this->addDocheaderButtons($group->getTitle());
        $assignments = [
            'group' => $group,
            'recipientSources' => $recipientSources
        ];

        if ($this->typo3MajorVersion < 12) {
            $this->view->assignMultiple($assignments + ['layoutSuffix' => 'V11']);
            $this->moduleTemplate->setContent($this->view->render());
            return $this->htmlResponse($this->moduleTemplate->renderContent());
        }

        $this->moduleTemplate->assignMultiple($assignments);
        return $this->moduleTemplate->renderResponse('Backend/Recipient/Show');
    }

    /**
     * @param Group $group
     * @param string $recipientSourceIdentifier
     * @return ResponseInterface
     * @throws Exception
     * @throws IllegalObjectTypeException
     * @throws InvalidQueryException
     * @throws UnknownObjectException
     * @throws \Doctrine\DBAL\Exception
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    public function csvDownloadAction(Group $group, string $recipientSourceIdentifier): ResponseInterface
    {
        if (str_starts_with($recipientSourceIdentifier, 'tx_mail_domain_model_group')) {
            [, $groupUid] = explode(':', $recipientSourceIdentifier);
            $groupOfRecipientSource = $this->groupRepository->findByUid((int)$groupUid);
            $rows = [];
            if ($groupOfRecipientSource instanceof Group) {
                if ($groupOfRecipientSource->getType() === RecipientGroupType::CSV) {
                    $rows = CsvUtility::rearrangeCsvValues($groupOfRecipientSource->getCsvData(), $groupOfRecipientSource->getCsvSeparatorString(), RecipientUtility::getAllRecipientFields());
                } else {
                    $rows = RecipientUtility::reArrangePlainMails(array_unique(preg_split('|[[:space:],;]+|', trim($groupOfRecipientSource->getList()))));
                    $rows = array_map(fn($element) => array_merge(array_slice($element, 0, 1), array_slice($element, 2)), $rows);
                }
            }
            return CsvUtility::downloadCSV($rows, $recipientSourceIdentifier);
        }

        if (!($this->recipientSources[$recipientSourceIdentifier] ?? false) && !($recipientSourceIdentifier === 'tx_mail_domain_model_group')) {
            ViewUtility::addFlashMessageError('',
                LanguageUtility::getLL('recipient.notification.noRecipientSourceConfigurationFound.message'), true);
            return $this->redirect('show');
        }
        /** @var RecipientSourceConfigurationDTO $recipientSourceConfiguration */
        $recipientSourceConfiguration = $this->recipientSources[$recipientSourceIdentifier];
        if (!BackendUserUtility::getBackendUser()->check('tables_select', $recipientSourceConfiguration->table)) {
            ViewUtility::addFlashMessageError('',
                LanguageUtility::getLL('recipient.notification.disallowedCsvExport.message'), true);
            return $this->redirect('show');
        }
        $idLists = $this->recipientService->getRecipientsUidListGroupedByRecipientSource($group);
        if (!array_key_exists($recipientSourceIdentifier, $idLists) || count($idLists[$recipientSourceIdentifier]) === 0) {
            ViewUtility::addFlashMessageError('',
                LanguageUtility::getLL('recipient.notification.noRecipientsFound.message'), true);
            return $this->redirect('show');
        }

        $idList = $idLists[$recipientSourceIdentifier];
        if (is_array(current($idList))) {
            // the list already contain recipient data and not only an array of uids
            $rows = $idList;
        } else {
            if ($recipientSourceConfiguration->model) {
                $rows = $this->recipientService->getRecipientsDataByUidListAndModelName($idList,
                    $recipientSourceConfiguration->model, []);
            } else {
                $csvExportFields = $recipientSourceConfiguration->csvExportFields ?? GeneralUtility::trimExplode(',',
                    $this->defaultCsvExportFields, true);
                $rows = $this->recipientService->getRecipientsDataByUidListAndTable($idList, $recipientSourceConfiguration->table,
                    $csvExportFields, true);
            }
        }
        return CsvUtility::downloadCSV($rows, $recipientSourceIdentifier);
    }

    /**
     * @return ResponseInterface
     * @throws \Exception
     */
    public function csvImportWizardAction(): ResponseInterface
    {
        /* @var $importService ImportService */
        $importService = GeneralUtility::makeInstance(ImportService::class);
        $importService->init($this->id, $this->request);
        $assignments = [
            'data' => $importService->getCsvImportUploadData(),
            'navigation' => ['steps' => [1, 2, 3, 4], 'currentStep' => 1]
        ];

        if ($this->typo3MajorVersion < 12) {
            $this->view->assignMultiple($assignments + ['layoutSuffix' => 'V11']);
            $this->moduleTemplate->setContent($this->view->render());
            return $this->htmlResponse($this->moduleTemplate->renderContent());
        }

        $this->moduleTemplate->assignMultiple($assignments);
        return $this->moduleTemplate->renderResponse('Backend/Recipient/CsvImportWizard');
    }

    /**
     * @throws \Exception
     */
    public function csvImportWizardUploadCsvAction(): ResponseInterface
    {
        /* @var $importService ImportService */
        $importService = GeneralUtility::makeInstance(ImportService::class);
        $importService->init($this->id, $this->request);

        if (!$importService->uploadCsv()) {
            ViewUtility::addFlashMessageError('An error occurred during csv import', 'Error');
            return $this->redirect('csvImportWizard');
        }
        return $this->redirect('csvImportWizardStepConfiguration');
    }

    /**
     * @param array $configuration
     * @return ResponseInterface
     * @throws AspectNotFoundException
     * @throws \TYPO3\CMS\Core\Resource\Exception
     * @throws \Exception
     */
    public function csvImportWizardImportCsvAction(array $configuration = []): ResponseInterface
    {
        /* @var $importService ImportService */
        $importService = GeneralUtility::makeInstance(ImportService::class);
        $importService->init($this->id, $this->request, $configuration);

        if (!$importService->importCsv()) {
            ViewUtility::addFlashMessageError('An error occurred during csv import', 'Error');
            return $this->redirect('csvImportWizard');
        }
        return $this->redirect('csvImportWizardStepConfiguration');
    }

    /**
     * @param array $configuration
     * @return ResponseInterface
     * @throws \Exception
     */
    public function csvImportWizardStepConfigurationAction(array $configuration = []): ResponseInterface
    {
        /* @var $importService ImportService */
        $importService = GeneralUtility::makeInstance(ImportService::class);
        $importService->init($this->id, $this->request, $configuration);
        $assignments = [
            'data' => $importService->getCsvImportConfigurationData(),
            'navigation' => ['steps' => [1, 2, 3, 4], 'currentStep' => 2]
        ];

        if ($this->typo3MajorVersion < 12) {
            $this->view->assignMultiple($assignments + ['layoutSuffix' => 'V11']);
            $this->moduleTemplate->setContent($this->view->render());
            return $this->htmlResponse($this->moduleTemplate->renderContent());
        }

        $this->moduleTemplate->assignMultiple($assignments);
        return $this->moduleTemplate->renderResponse('Backend/Recipient/CsvImportWizardStepConfiguration');
    }

    /**
     * @param array $configuration
     * @return ResponseInterface
     * @throws \Exception
     */
    public function csvImportWizardStepMappingAction(array $configuration = []): ResponseInterface
    {
        /* @var $importService ImportService */
        $importService = GeneralUtility::makeInstance(ImportService::class);
        $importService->init($this->id, $this->request, $configuration);
        $assignments = [
            'data' => $importService->getCsvImportMappingData(),
            'navigation' => ['steps' => [1, 2, 3, 4], 'currentStep' => 3]
        ];

        if ($this->typo3MajorVersion < 12) {
            $this->view->assignMultiple($assignments + ['layoutSuffix' => 'V11']);
            $this->moduleTemplate->setContent($this->view->render());
            return $this->htmlResponse($this->moduleTemplate->renderContent());
        }

        $this->moduleTemplate->assignMultiple($assignments);
        return $this->moduleTemplate->renderResponse('Backend/Recipient/CsvImportWizardStepMapping');
    }

    /**
     * @param array $configuration
     * @return ResponseInterface
     * @throws Exception
     * @throws \Exception
     */
    public function csvImportWizardStepStartImportAction(array $configuration = []): ResponseInterface
    {
        /* @var $importService ImportService */
        $importService = GeneralUtility::makeInstance(ImportService::class);
        $importService->init($this->id, $this->request, $configuration);

        if (!$importService->validateMapping()) {
            return $this->redirect('csvImportWizardStepMapping', null, null, ['configuration' => $configuration]);
        }
        $assignments = [
            'data' => $importService->startCsvImport(),
            'navigation' => ['steps' => [1, 2, 3, 4], 'currentStep' => 4]
        ];

        if ($this->typo3MajorVersion < 12) {
            $this->view->assignMultiple($assignments + ['layoutSuffix' => 'V11']);
            $this->moduleTemplate->setContent($this->view->render());
            return $this->htmlResponse($this->moduleTemplate->renderContent());
        }

        $this->moduleTemplate->assignMultiple($assignments);
        return $this->moduleTemplate->renderResponse('Backend/Recipient/CsvImportWizardStepStartImport');
    }

    /**
     * Create document header buttons of "overview" action
     * @param int $pid
     * @param string $requestUri
     * @throws RouteNotFoundException
     */
    protected function addLeftDocheaderButtons(int $pid, string $requestUri): void
    {
        $buttonBar = $this->moduleTemplate->getDocHeaderComponent()->getButtonBar();
        $addNewButton = $buttonBar
            ->makeLinkButton()
            ->setIcon($this->iconFactory->getIcon('actions-add', Icon::SIZE_SMALL))
            ->setClasses('btn btn-default text-uppercase')
            ->setTitle(LanguageUtility::getLL('recipient.createMailGroup.message'))
            ->setHref((string)$this->backendUriBuilder->buildUriFromRoute('record_edit', [
                'edit' => ['tx_mail_domain_model_group' => [$pid => 'new']],
                'returnUrl' => $requestUri
            ]));
        $buttonBar->addButton($addNewButton);
        if ($this->ttAddressIsLoaded) {
            $addCsvImportButton = $buttonBar
                ->makeLinkButton()
                ->setIcon($this->iconFactory->getIcon('content-csv', Icon::SIZE_SMALL, 'actions-arrow-up-alt'))
                ->setClasses('btn btn-default text-uppercase')
                ->setTitle(LanguageUtility::getLL('recipient.button.importCsv'))
                ->setHref($this->uriBuilder->uriFor('csvImportWizard'));
            $buttonBar->addButton($addCsvImportButton, ButtonBar::BUTTON_POSITION_LEFT, 2);
        }
    }

    /**
     * Create document header buttons of "show" action
     * @param int $uid
     * @param string $requestUri
     * @throws RouteNotFoundException
     */
    protected function addLeftDocheaderBackEditButtons(int $uid, string $requestUri): void
    {
        $buttonBar = $this->moduleTemplate->getDocHeaderComponent()->getButtonBar();
        $addBackButton = $buttonBar
            ->makeLinkButton()
            ->setIcon($this->iconFactory->getIcon('actions-view-go-back', Icon::SIZE_SMALL))
            ->setClasses('btn btn-default text-uppercase')
            ->setTitle(LanguageUtility::getLL('general.button.back'))
            ->setHref($this->uriBuilder->uriFor('index'));
        $buttonBar->addButton($addBackButton);
        $addEditGroupButton = $buttonBar
            ->makeLinkButton()
            ->setIcon($this->iconFactory->getIcon('mail-group-edit', Icon::SIZE_SMALL))
            ->setClasses('btn btn-default text-uppercase')
            ->setTitle(LanguageUtility::getLL('general.button.edit'))
            ->setHref((string)$this->backendUriBuilder->buildUriFromRoute('record_edit', [
                'edit' => ['tx_mail_domain_model_group' => [$uid => 'edit']],
                'returnUrl' => $requestUri
            ]));
        $buttonBar->addButton($addEditGroupButton, ButtonBar::BUTTON_POSITION_LEFT, 2);
    }

    /**
     * Create document header buttons of "overview" action
     * @param string $groupName
     */
    protected function addDocheaderButtons(string $groupName = ''): void
    {
        $buttonBar = $this->moduleTemplate->getDocHeaderComponent()->getButtonBar();
        $routeIdentifier = $this->typo3MajorVersion < 12 ? 'MailMail_MailRecipient' : 'mail_recipient';
        $shortCutButton = $buttonBar->makeShortcutButton()
            ->setRouteIdentifier($routeIdentifier);
        $arguments = [
            'id' => $this->id,
        ];
        $potentialArguments = [
            'tx_mail_mailmail_mailrecipient' => ['group', 'action', 'controller'],
        ];
        $displayName = LanguageUtility::getLL('shortcut.recipientGroups') . ' [' . $this->id . ']';
        foreach ($potentialArguments as $argument => $subArguments) {
            if (!empty($this->request->getQueryParams()[$argument])) {
                foreach ($subArguments as $subArgument) {
                    if ($this->request->getQueryParams()[$argument][$subArgument] ?? false) {
                        $arguments[$argument][$subArgument] = $this->request->getQueryParams()[$argument][$subArgument];
                    }
                }
                if ($arguments['tx_mail_mailmail_mailrecipient']['group'] ?? false) {
                    $displayName = LanguageUtility::getLL('shortcut.recipientGroup') . ': ' . $groupName . ' [' . $arguments['tx_mail_mailmail_mailrecipient']['group'] . ']';
                }
            }
        }
        $shortCutButton->setArguments($arguments);
        $shortCutButton->setDisplayName($displayName);
        $buttonBar->addButton($shortCutButton, ButtonBar::BUTTON_POSITION_RIGHT);
    }
}
