<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Controller;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use MEDIAESSENZ\Mail\Domain\Model\Group;
use MEDIAESSENZ\Mail\Domain\Model\RecipientInterface;
use MEDIAESSENZ\Mail\Domain\Repository\RecipientRepositoryInterface;
use MEDIAESSENZ\Mail\Service\ImportService;
use MEDIAESSENZ\Mail\Type\Enumeration\RecipientGroupType;
use MEDIAESSENZ\Mail\Utility\BackendUserUtility;
use MEDIAESSENZ\Mail\Utility\CsvUtility;
use MEDIAESSENZ\Mail\Utility\LanguageUtility;
use MEDIAESSENZ\Mail\Utility\ViewUtility;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Utility\ClassNamingUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Exception\StopActionException;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapper;
use TYPO3\CMS\Extbase\Persistence\Repository;

class RecipientController extends AbstractController
{
    protected string $defaultCsvExportFields = 'uid,name,first_name,middle_name,last_name,title,email,phone,www,address,company,city,zip,country,fax,categories,mail_html,tstamp';

    /**
     * @return ResponseInterface
     * @throws DBALException
     * @throws Exception
     * @throws IllegalObjectTypeException
     * @throws InvalidQueryException
     * @throws UnknownObjectException
     * @throws \Doctrine\DBAL\Exception
     */
    public function indexAction(): ResponseInterface
    {
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
                'categories' => $group->getType() === RecipientGroupType::PAGES ? $group->getCategories() : [],
                'count' => $this->recipientService->getNumberOfRecipientsByGroup($group),
            ];
        }

        $this->view->assign('pid', $this->id);
        $this->view->assign('data', $data);

        $this->moduleTemplate->setContent($this->view->render());
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/Tooltip');
        $this->addDocheaderButtons();

        return $this->htmlResponse($this->moduleTemplate->renderContent());
    }

    /**
     * @param Group $group
     * @return ResponseInterface
     * @throws DBALException
     * @throws Exception
     * @throws IllegalObjectTypeException
     * @throws InvalidQueryException
     * @throws UnknownObjectException
     * @throws \Doctrine\DBAL\Exception
     * @throws \TYPO3\CMS\Extbase\Persistence\Generic\Exception
     */
    public function showAction(Group $group): ResponseInterface
    {
        $recipientSources = [];
        $idLists = $this->recipientService->getRecipientsUidListGroupedByRecipientSource($group, true);

        foreach ($idLists as $recipientSourceIdentifier => $idList) {
            $recipientSourceConfiguration = $this->siteConfiguration['RecipientSources'][$recipientSourceIdentifier] ?? false;
            $isCsv = str_starts_with($recipientSourceIdentifier, 'tx_mail_domain_model_group');
            if (!$idList || (!$recipientSourceConfiguration && !$isCsv)) {
                continue;
            }
            $recipients = [];
            $categoryColumn = true;
            $htmlColumn = true;
            $table = false;
            $editCsvList = 0;
            if ($isCsv) {
                [$recipientSourceIdentifier, $groupUid] = explode(':', $recipientSourceIdentifier);
                $recipients = $idList;
                $table = $recipientSourceConfiguration['table'] ?? $recipientSourceIdentifier;
                $categoryColumn = false;
                $htmlColumn = false;
                $recipientSourceConfiguration['icon'] = 'actions-user';
                $recipientSourceConfiguration['title'] = 'CSV List';
                $editCsvList = $groupUid;
            } else {
                $type = $recipientSourceConfiguration['type'] ?? 'Table';
                switch ($type) {
                    case 'Extbase':
                        $model = $recipientSourceConfiguration['model'] ?? false;
                        if (class_exists($model) && is_subclass_of($model, RecipientInterface::class)) {
                            if ($recipientSourceConfiguration['table'] ?? false) {
                                $table = $recipientSourceConfiguration['table'];
                            } else {
                                $dataMapper = GeneralUtility::makeInstance(DataMapper::class);
                                $table = $dataMapper->getDataMap($model)->getTableName();
                            }
                            $repositoryName = ClassNamingUtility::translateModelNameToRepositoryName($model);
                            /** @var Repository $repository */
                            $repository = GeneralUtility::makeInstance($repositoryName);
                            if ($repository instanceof RecipientRepositoryInterface) {
                                $recipients = $repository->findByUidListAndCategories($idList);
                            }
                        }
                        break;
                    case 'Table':
                        $table = $recipientSourceConfiguration['table'] ?? $recipientSourceIdentifier;
                        $recipients = $this->recipientService->getRecipientsDataByUidListAndTable($idList, $table);
                        break;
                }
            }

            $recipientSources[$recipientSourceIdentifier] = [
                'title' => $recipientSourceConfiguration['title'],
                'table' => $table,
                'icon' => $recipientSourceConfiguration['icon'] ?? 'actions-user',
                'recipients' => $recipients,
                'numberOfRecipients' => count($recipients),
                'categoryColumn' => $categoryColumn,
                'htmlColumn' => $htmlColumn,
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
        $this->addDocheaderButtons($group->getTitle());

        return $this->htmlResponse($this->moduleTemplate->renderContent());
    }

    /**
     * @param Group $group
     * @param string $recipientSourceIdentifier
     * @return void
     * @throws DBALException
     * @throws Exception
     * @throws IllegalObjectTypeException
     * @throws InvalidQueryException
     * @throws StopActionException
     * @throws UnknownObjectException
     * @throws \Doctrine\DBAL\Exception
     */
    public function csvDownloadAction(Group $group, string $recipientSourceIdentifier): void
    {
        $recipientSourceConfiguration = $this->siteConfiguration['RecipientSources'][$recipientSourceIdentifier] ?? false;
        if (!$recipientSourceConfiguration) {
            ViewUtility::addFlashMessageError('', LanguageUtility::getLL('recipient.notification.noRecipientSourceConfigurationFound.message'), true);
            $this->redirect('show');
        }
        if (!BackendUserUtility::getBackendUser()->check('tables_select', $recipientSourceConfiguration['table'])) {
            ViewUtility::addFlashMessageError('', LanguageUtility::getLL('recipient.notification.disallowedCsvExport.message'), true);
            $this->redirect('show');
        }
        $idLists = $this->recipientService->getRecipientsUidListGroupedByRecipientSource($group);
        if (!array_key_exists($recipientSourceIdentifier, $idLists) || count($idLists[$recipientSourceIdentifier]) === 0) {
            ViewUtility::addFlashMessageError('', LanguageUtility::getLL('recipient.notification.noRecipientsFound.message'), true);
            $this->redirect('show');
        }

        $idList = $idLists[$recipientSourceIdentifier];
        $rows = [];
        if (is_array(current($idList))) {
            // the list already contain recipient data and not only an array of uids
            $rows = $idList;
        } else {
            $type = $recipientSourceConfiguration['type'] ?? 'Table';
            switch ($type) {
                case 'Extbase':
                    $model = $recipientSourceConfiguration['model'] ?? false;
                    if ($model) {
                        $rows = $this->recipientService->getRecipientsDataByUidListAndModelName($idList, $model, []);
                    }
                    break;
                case 'Table':
                    $csvExportFields = $recipientSourceConfiguration['csvExportFields'] ?? GeneralUtility::trimExplode(',', $this->defaultCsvExportFields, true);
                    $table = $recipientSourceConfiguration['table'] ?? $recipientSourceIdentifier;
                    $rows = $this->recipientService->getRecipientsDataByUidListAndTable($idList, $table, $csvExportFields);
                    break;
            }
        }
        CsvUtility::downloadCSV($rows);
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

        $this->moduleTemplate->setContent($this->view->render());

        return $this->htmlResponse($this->moduleTemplate->renderContent());
    }

    /**
     * @param array $configuration
     * @return ResponseInterface
     * @throws AspectNotFoundException
     * @throws DBALException
     * @throws Exception
     * @throws \TYPO3\CMS\Core\Resource\Exception
     * @throws StopActionException
     * @throws \Exception
     */
    public function csvImportWizardStepConfigurationAction(array $configuration = []): ResponseInterface
    {
        /* @var $importService ImportService */
        $importService = GeneralUtility::makeInstance(ImportService::class);
        $importService->init($this->id, $this->request, $configuration);

        if (!$importService->uploadOrImportCsv()) {
            ViewUtility::addFlashMessageError('An error occurred during csv import', 'Error');
            $this->redirect('csvImportWizard');
        }

        $this->view->assign('data', $importService->getCsvImportConfigurationData());

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

        $this->moduleTemplate->setContent($this->view->render());

        return $this->htmlResponse($this->moduleTemplate->renderContent());
    }

    /**
     * @param array $configuration
     * @return ResponseInterface
     * @throws DBALException
     * @throws Exception
     * @throws StopActionException
     * @throws \Exception
     */
    public function csvImportWizardStepStartImportAction(array $configuration = []): ResponseInterface
    {
        /* @var $importService ImportService */
        $importService = GeneralUtility::makeInstance(ImportService::class);
        $importService->init($this->id, $this->request, $configuration);

        if (!$importService->validateMapping()) {
            $this->redirect('csvImportWizardStepMapping', null, null, ['configuration' => $configuration]);
        }

        $this->view->assign('data', $importService->startCsvImport());

        $this->moduleTemplate->setContent($this->view->render());

        return $this->htmlResponse($this->moduleTemplate->renderContent());
    }

    /**
     * Create document header buttons of "overview" action
     * @param string $groupName
     */
    protected function addDocheaderButtons(string $groupName = ''): void
    {
        $buttonBar = $this->moduleTemplate->getDocHeaderComponent()->getButtonBar();
        $shortCutButton = $buttonBar->makeShortcutButton()->setRouteIdentifier('MailMail_MailRecipient');
        $arguments = [
            'id' => $this->id,
        ];
        $potentialArguments = [
            'tx_mail_mailmail_mailrecipient' => ['group', 'action', 'controller'],
        ];
        $displayName = 'Mail Groups [' . $this->id . ']';
        foreach ($potentialArguments as $argument => $subArguments) {
            if (!empty($this->request->getQueryParams()[$argument])) {
                foreach ($subArguments as $subArgument) {
                    $arguments[$argument][$subArgument] = $this->request->getQueryParams()[$argument][$subArgument];
                }
                $displayName = 'Mail Group: ' . $groupName . ' [' . $arguments['tx_mail_mailmail_mailrecipient']['group'] . ']';
            }
        }
        $shortCutButton->setArguments($arguments);
        $shortCutButton->setDisplayName($displayName);
        $buttonBar->addButton($shortCutButton, ButtonBar::BUTTON_POSITION_RIGHT);
    }
}
