<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Controller;

use Doctrine\DBAL\Driver\Exception;
use MEDIAESSENZ\Mail\Constants;
use MEDIAESSENZ\Mail\Domain\Model\Group;
use MEDIAESSENZ\Mail\Domain\Model\RecipientInterface;
use MEDIAESSENZ\Mail\Domain\Repository\RecipientRepositoryInterface;
use MEDIAESSENZ\Mail\Service\ImportService;
use MEDIAESSENZ\Mail\Type\Enumeration\RecipientGroupType;
use MEDIAESSENZ\Mail\Utility\BackendDataUtility;
use MEDIAESSENZ\Mail\Utility\BackendUserUtility;
use MEDIAESSENZ\Mail\Utility\CsvUtility;
use MEDIAESSENZ\Mail\Utility\LanguageUtility;
use MEDIAESSENZ\Mail\Utility\ViewUtility;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Utility\BackendUtility;
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
     * @throws IllegalObjectTypeException
     * @throws InvalidQueryException
     * @throws UnknownObjectException
     * @throws \Doctrine\DBAL\Exception
     * @throws RouteNotFoundException
     */
    public function indexAction(): ResponseInterface
    {
        if ($this->pageInfo['module'] !== Constants::MAIL_MODULE_NAME) {
            // current selected page has no mail module configuration -> redirect to closest mail module page
            $mailModulePageId = BackendDataUtility::getClosestMailModulePageId($this->id);
            if ($mailModulePageId) {
                if ($this->typo3MajorVersion < 12) {
                    // Hack, because redirect to pid would not work otherwise (see extbase/Classes/Mvc/Web/Routing/UriBuilder.php line 646)
                    $_GET['id'] = $mailModulePageId;
                }
                return $this->redirect('index', null, null, ['id' => $mailModulePageId]);
            }
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

        $this->view->assign('pid', $this->id);
        $this->view->assign('data', $data);

        $this->moduleTemplate->setContent($this->view->render());
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/Tooltip');
        $this->addLeftDocheaderButtons($this->id, $this->request->getRequestTarget());
        $this->addDocheaderButtons();

        return $this->htmlResponse($this->moduleTemplate->renderContent());
    }

    /**
     * @param Group $group
     * @return ResponseInterface
     * @throws Exception
     * @throws IllegalObjectTypeException
     * @throws InvalidQueryException
     * @throws UnknownObjectException
     * @throws \Doctrine\DBAL\Exception
     * @throws RouteNotFoundException
     */
    public function showAction(Group $group): ResponseInterface
    {
        $recipientSources = [];
        $idLists = $this->recipientService->getRecipientsUidListGroupedByRecipientSource($group, true);

        foreach ($idLists as $recipientSourceIdentifier => $idList) {
            $recipientSourceConfiguration = $this->recipientSources[$recipientSourceIdentifier] ?? false;
            $isCsv = str_starts_with($recipientSourceIdentifier, 'tx_mail_domain_model_group');
            if (!$idList || (!$recipientSourceConfiguration && !$isCsv)) {
                continue;
            }
            $recipients = [];
            $editCsvList = 0;
            if ($isCsv) {
                [$recipientSourceIdentifier, $groupUid] = explode(':', $recipientSourceIdentifier);
                $recipients = $idList;
                $table = $recipientSourceIdentifier;
                $recipientSourceConfiguration['icon'] = 'actions-user';
                $recipientSourceConfiguration['title'] = 'CSV List';
                $editCsvList = $groupUid;
            } else {
                $table = $recipientSourceIdentifier;
                if ($recipientSourceConfiguration['model'] ?? false) {
                    $model = $recipientSourceConfiguration['model'];
                    if (class_exists($model) && is_subclass_of($model, RecipientInterface::class)) {
                        $repositoryName = ClassNamingUtility::translateModelNameToRepositoryName($model);
                        /** @var Repository $repository */
                        $repository = GeneralUtility::makeInstance($repositoryName);
                        if ($repository instanceof RecipientRepositoryInterface) {
                            $recipients = $repository->findByUidListAndCategories($idList);
                        }
                    }
                } else {
                    $recipients = $this->recipientService->getRecipientsDataByUidListAndTable($idList, $table);
                }
            }

            $recipientSources[$recipientSourceIdentifier] = [
                'title' => $recipientSourceConfiguration['title'],
                'table' => $table,
                'icon' => $recipientSourceConfiguration['icon'] ?? 'actions-user',
                'recipients' => $recipients,
                'numberOfRecipients' => count($recipients),
                'show' => $table && BackendUserUtility::getBackendUser()->check('tables_select', $table),
                'edit' => $table && BackendUserUtility::getBackendUser()->check('tables_modify', $table),
                'editCsvList' => $table && BackendUserUtility::getBackendUser()->check('tables_modify', $table) ? $editCsvList : 0,
            ];
        }

        $this->view->assignMultiple([
            'group' => $group,
            'recipientSources' => $recipientSources
        ]);

        $this->moduleTemplate->setContent($this->view->render());
        $this->addLeftDocheaderBackEditButtons($group->getUid(), $this->request->getRequestTarget());
        $this->addDocheaderButtons($group->getTitle());

        return $this->htmlResponse($this->moduleTemplate->renderContent());
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
     */
    public function csvDownloadAction(Group $group, string $recipientSourceIdentifier): ResponseInterface
    {
        $recipientSourceConfiguration = $this->recipientSources[$recipientSourceIdentifier] ?? false;
        if (!$recipientSourceConfiguration && !($recipientSourceIdentifier === 'tx_mail_domain_model_group')) {
            ViewUtility::addFlashMessageError('', LanguageUtility::getLL('recipient.notification.noRecipientSourceConfigurationFound.message'), true);
            return $this->redirect('show');
        }
        if (!BackendUserUtility::getBackendUser()->check('tables_select', $recipientSourceIdentifier)) {
            ViewUtility::addFlashMessageError('', LanguageUtility::getLL('recipient.notification.disallowedCsvExport.message'), true);
            return $this->redirect('show');
        }
        $idLists = $this->recipientService->getRecipientsUidListGroupedByRecipientSource($group);
        if (!array_key_exists($recipientSourceIdentifier, $idLists) || count($idLists[$recipientSourceIdentifier]) === 0) {
            ViewUtility::addFlashMessageError('', LanguageUtility::getLL('recipient.notification.noRecipientsFound.message'), true);
            return $this->redirect('show');
        }

        $idList = $idLists[$recipientSourceIdentifier];
        if (is_array(current($idList))) {
            // the list already contain recipient data and not only an array of uids
            $rows = $idList;
        } else {
            if ($recipientSourceConfiguration['model'] ?? false) {
                $rows = $this->recipientService->getRecipientsDataByUidListAndModelName($idList, $recipientSourceConfiguration['model'], []);
            } else {
                $csvExportFields = $recipientSourceConfiguration['csvExportFields'] ?? GeneralUtility::trimExplode(',', $this->defaultCsvExportFields, true);
                $rows = $this->recipientService->getRecipientsDataByUidListAndTable($idList, $recipientSourceIdentifier, $csvExportFields, true);
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

        $this->view->assign('data', $importService->getCsvImportUploadData());
        $this->view->assign('navigation', ['steps' => [1,2,3,4], 'currentStep' => 1]);

        $this->moduleTemplate->setContent($this->view->render());

        return $this->htmlResponse($this->moduleTemplate->renderContent());
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
     * @throws Exception
     * @throws \Exception
     */
    public function csvImportWizardStepConfigurationAction(array $configuration = []): ResponseInterface
    {
        /* @var $importService ImportService */
        $importService = GeneralUtility::makeInstance(ImportService::class);
        $importService->init($this->id, $this->request, $configuration);

        $this->view->assign('data', $importService->getCsvImportConfigurationData());
        $this->view->assign('navigation', ['steps' => [1,2,3,4], 'currentStep' => 2]);

        $this->moduleTemplate->setContent($this->view->render());

        return $this->htmlResponse($this->moduleTemplate->renderContent());
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

        $this->view->assign('data', $importService->getCsvImportMappingData());
        $this->view->assign('navigation', ['steps' => [1,2,3,4], 'currentStep' => 3]);

        $this->moduleTemplate->setContent($this->view->render());

        return $this->htmlResponse($this->moduleTemplate->renderContent());
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

        $this->view->assign('data', $importService->startCsvImport());
        $this->view->assign('navigation', ['steps' => [1,2,3,4], 'currentStep' => 4]);

        $this->moduleTemplate->setContent($this->view->render());

        return $this->htmlResponse($this->moduleTemplate->renderContent());
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
        $addCsvImportButton = $buttonBar
            ->makeLinkButton()
            ->setIcon($this->iconFactory->getIcon('content-csv', Icon::SIZE_SMALL, 'actions-arrow-up-alt'))
            ->setClasses('btn btn-default text-uppercase')
            ->setTitle(LanguageUtility::getLL('recipient.button.importCsv'))
            ->setHref($this->uriBuilder->uriFor('csvImportWizard'));
        $buttonBar->addButton($addCsvImportButton, ButtonBar::BUTTON_POSITION_LEFT, 2);
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
