<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Controller;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use MEDIAESSENZ\Mail\Domain\Model\Group;
use MEDIAESSENZ\Mail\Service\ImportService;
use MEDIAESSENZ\Mail\Utility\BackendUserUtility;
use MEDIAESSENZ\Mail\Utility\CsvUtility;
use MEDIAESSENZ\Mail\Utility\LanguageUtility;
use MEDIAESSENZ\Mail\Utility\RecipientUtility;
use MEDIAESSENZ\Mail\Utility\ViewUtility;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Exception\StopActionException;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;

class RecipientController extends AbstractController
{
    protected string $fieldList = 'uid,name,first_name,middle_name,last_name,title,email,phone,www,address,company,city,zip,country,fax,categories,accepts_html';

    /**
     * @return ResponseInterface
     * @throws DBALException
     * @throws Exception
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
     * @throws \Doctrine\DBAL\Exception
     */
    public function indexAction(): ResponseInterface
    {
        $data = [];

        $groups = $this->groupRepository->findByPid($this->id);

        /** @var Group $group */
        foreach ($groups as $group) {
            $data[] = [
                'group' => $group,
                'uid' => $group->getUid(),
                'title' => $group->getTitle(),
                'recordTypes' => BackendUtility::getProcessedValue('tx_mail_domain_model_group', 'record_types', $group->getRecordTypes()),
                'categories' => $group->getCategories(),
                'description' => BackendUtility::getProcessedValue('tx_mail_domain_model_group', 'description', $group->getDescription()),
                'type' => $group->getType(),
                'typeProcessed' => BackendUtility::getProcessedValue('tx_mail_domain_model_group', 'type', $group->getType()),
                'count' => RecipientUtility::calculateTotalRecipientsOfUidLists($this->recipientService->getRecipientsUidListsGroupedByTable($group), $this->userTable),
            ];
        }

        $this->view->assign('data', $data);

        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setContent($this->view->render());
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/Tooltip');

        return $this->htmlResponse($moduleTemplate->renderContent());
    }

    /**
     * @param Group $group
     * @return ResponseInterface
     * @throws DBALException
     * @throws Exception
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
     * @throws \Doctrine\DBAL\Exception
     */
    public function showAction(Group $group): ResponseInterface
    {
        $idLists = $this->recipientService->getRecipientsUidListsGroupedByTable($group);
        $totalRecipients = RecipientUtility::calculateTotalRecipientsOfUidLists($idLists, $this->userTable);

        $data = [
            'uid' => $group->getUid(),
            'title' => $group->getTitle(),
            'totalRecipients' => $totalRecipients,
            'tables' => [],
            'special' => [],
        ];

        if (is_array($idLists['tt_address'] ?? false)) {
            $rows = $this->recipientService->getRecipientsDataByUidListAndTable($idLists['tt_address'], 'tt_address');
            $data['tables']['tt_address'] = [
                'table' => 'tt_address',
                'recipients' => $rows,
                'numberOfRecipients' => count($rows),
                'show' => BackendUserUtility::getBackendUser()->check('tables_select', 'tt_address'),
                'edit' => BackendUserUtility::getBackendUser()->check('tables_modify', 'tt_address'),
            ];
        }
        if (is_array($idLists['fe_users'] ?? false)) {
            $rows = $this->recipientService->getRecipientsDataByUidListAndTable($idLists['fe_users'], 'fe_users');
            $data['tables']['fe_users'] = [
                'table' => 'fe_users',
                'recipients' => $rows,
                'numberOfRecipients' => count($rows),
                'show' => BackendUserUtility::getBackendUser()->check('tables_select', 'fe_users'),
                'edit' => BackendUserUtility::getBackendUser()->check('tables_modify', 'fe_users'),
            ];
        }
        if (is_array($idLists['tx_mail_domain_model_group'] ?? false)) {
            $data['tables']['tx_mail_domain_model_group'] = [
                'table' => 'tx_mail_domain_model_group',
                'recipients' => $idLists['tx_mail_domain_model_group'],
                'numberOfRecipients' => count($idLists['tx_mail_domain_model_group']),
                'show' => BackendUserUtility::getBackendUser()->check('tables_select', 'tx_mail_domain_model_group'),
                'edit' => BackendUserUtility::getBackendUser()->check('tables_modify', 'tx_mail_domain_model_group'),
            ];
        }
        if (is_array($idLists[$this->userTable] ?? false)) {
            $rows = $this->recipientService->getRecipientsDataByUidListAndTable($idLists[$this->userTable], $this->userTable);
            $data['tables'][$this->userTable] = [
                'table' => $this->userTable,
                'recipients' => $rows,
                'numberOfRecipients' => count($rows),
                'show' => BackendUserUtility::getBackendUser()->check('tables_select', $this->userTable),
                'edit' => BackendUserUtility::getBackendUser()->check('tables_modify', $this->userTable),
            ];
        }

        $this->view->assign('data', $data);

        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setContent($this->view->render());
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/Tooltip');

        return $this->htmlResponse($moduleTemplate->renderContent());
    }

    /**
     * @param Group $group
     * @param string $table
     * @return void
     * @throws DBALException
     * @throws Exception
     * @throws StopActionException
     * @throws \Doctrine\DBAL\Exception
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
     */
    public function csvDownloadAction(Group $group, string $table): void
    {
        $idLists = $this->recipientService->getRecipientsUidListsGroupedByTable($group);

        if ($table === 'tx_mail_domain_model_group') {
            CsvUtility::downloadCSV($idLists['tx_mail_domain_model_group']);
        } else {
            if (GeneralUtility::inList('tt_address,fe_users,' . $this->userTable, $table)) {
                if (BackendUserUtility::getBackendUser()->check('tables_select', $table)) {
                    $fields = $table === 'fe_users' ? str_replace('phone', 'telephone', $this->fieldList) : $this->fieldList;
                    $fields .= ',tstamp';
                    $rows = $this->recipientService->getRecipientsDataByUidListAndTable($idLists[$table], $table,
                        GeneralUtility::trimExplode(',', $fields, true));
                    CsvUtility::downloadCSV($rows);
                } else {
                    ViewUtility::addErrorToFlashMessageQueue('', LanguageUtility::getLL('mailgroup_table_disallowed_csv'), true);
                    $this->redirect('show');
                }
            }
        }
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

        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setContent($this->view->render());

        return $this->htmlResponse($moduleTemplate->renderContent());
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
            ViewUtility::addErrorToFlashMessageQueue('An error occurred during csv import', 'Error');
            $this->redirect('csvImportWizard');
        }

        $this->view->assign('data', $importService->getCsvImportConfigurationData());

        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setContent($this->view->render());

        return $this->htmlResponse($moduleTemplate->renderContent());
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

        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setContent($this->view->render());

        return $this->htmlResponse($moduleTemplate->renderContent());
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

        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setContent($this->view->render());

        return $this->htmlResponse($moduleTemplate->renderContent());
    }
}
