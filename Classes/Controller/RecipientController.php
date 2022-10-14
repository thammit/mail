<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Controller;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use MEDIAESSENZ\Mail\Domain\Model\Group;
use MEDIAESSENZ\Mail\Domain\Repository\TempRepository;
use MEDIAESSENZ\Mail\Service\ImportService;
use MEDIAESSENZ\Mail\Utility\BackendUserUtility;
use MEDIAESSENZ\Mail\Utility\CsvUtility;
use MEDIAESSENZ\Mail\Utility\LanguageUtility;
use MEDIAESSENZ\Mail\Utility\ViewUtility;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Exception\StopActionException;

class RecipientController extends AbstractController
{
    protected string $fieldList = 'uid,name,first_name,middle_name,last_name,title,email,phone,www,address,company,city,zip,country,fax,categories,accepts_html';

    /**
     * @return ResponseInterface
     * @throws RouteNotFoundException
     * @throws DBALException
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public function indexAction(): ResponseInterface
    {
        $data = [
            'rows' => [],
        ];

        $recipientGroups = $this->groupRepository->findByPid($this->id);

        /** @var Group $recipientGroup */
        foreach ($recipientGroups as $recipientGroup) {
            $result = $this->recipientService->compileMailGroup($recipientGroup->getUid());
            $totalRecipients = 0;
            $idLists = $result['queryInfo']['id_lists'];

            if (is_array($idLists['tt_address'] ?? false)) {
                $totalRecipients += count($idLists['tt_address']);
            }
            if (is_array($idLists['fe_users'] ?? false)) {
                $totalRecipients += count($idLists['fe_users']);
            }
            if (is_array($idLists['tx_mail_domain_model_group'] ?? false)) {
                $totalRecipients += count($idLists['tx_mail_domain_model_group']);
            }
            if (is_array($idLists[$this->userTable] ?? false)) {
                $totalRecipients += count($idLists[$this->userTable]);
            }

            $data['rows'][] = [
                'uid' => $recipientGroup->getUid(),
                'title' => $recipientGroup->getTitle(),
                'type' => $recipientGroup->getType(),
                'typeProcessed' => htmlspecialchars(BackendUtility::getProcessedValue('tx_mail_domain_model_group', 'type', $recipientGroup->getType())),
                'description' => BackendUtility::getProcessedValue('tx_mail_domain_model_group', 'description',
                    htmlspecialchars($recipientGroup->getDescription())),
                'count' => $totalRecipients,
            ];
        }

        $this->view->assignMultiple([
            'data' => $data,
        ]);

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
     * @throws \Doctrine\DBAL\Exception
     */
    public function showAction(Group $group): ResponseInterface
    {
        $result = $this->recipientService->compileMailGroup($group->getUid());
        $totalRecipients = 0;
        $idLists = $result['queryInfo']['id_lists'];
        if (is_array($idLists['tt_address'] ?? false)) {
            $totalRecipients += count($idLists['tt_address']);
        }
        if (is_array($idLists['fe_users'] ?? false)) {
            $totalRecipients += count($idLists['fe_users']);
        }
        if (is_array($idLists['tx_mail_domain_model_group'] ?? false)) {
            $totalRecipients += count($idLists['tx_mail_domain_model_group']);
        }
        if (is_array($idLists[$this->userTable] ?? false)) {
            $totalRecipients += count($idLists[$this->userTable]);
        }

        $data = [
            'uid' => $group->getUid(),
            'title' => $group->getTitle(),
            'totalRecipients' => $totalRecipients,
            'tables' => [],
            'special' => [],
        ];

        $tempRepository = GeneralUtility::makeInstance(TempRepository::class);

        if (is_array($idLists['tt_address'] ?? false)) {
            $rows = $tempRepository->fetchRecordsListValues($idLists['tt_address'], 'tt_address');

            $data['tables']['tt_address'] = [
                'table' => 'tt_address',
                'recipients' => $rows,
                'numberOfRecipients' => count($rows),
                'show' => BackendUserUtility::getBackendUser()->check('tables_select', 'tt_address'),
                'edit' => BackendUserUtility::getBackendUser()->check('tables_modify', 'tt_address'),
            ];
        }
        if (is_array($idLists['fe_users'] ?? false)) {
            $rows = $tempRepository->fetchRecordsListValues($idLists['fe_users'], 'fe_users');
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
            $rows = $tempRepository->fetchRecordsListValues($idLists[$this->userTable], $this->userTable);
            $data['tables'][$this->userTable] = [
                'table' => $this->userTable,
                'recipients' => $rows,
                'numberOfRecipients' => count($rows),
                'show' => BackendUserUtility::getBackendUser()->check('tables_select', $this->userTable),
                'edit' => BackendUserUtility::getBackendUser()->check('tables_modify', $this->userTable),
            ];
        }

        $this->view->assignMultiple([
            'data' => $data,
        ]);

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
     */
    public function csvDownloadAction(Group $group, string $table): void
    {
        $result = $this->recipientService->compileMailGroup($group->getUid());
        $idLists = $result['queryInfo']['id_lists'];

        if ($table === 'tx_mail_domain_model_group') {
            CsvUtility::downloadCSV($idLists['tx_mail_domain_model_group']);
        } else {
            if (GeneralUtility::inList('tt_address,fe_users,' . $this->userTable, $table)) {
                if (BackendUserUtility::getBackendUser()->check('tables_select', $table)) {
                    $fields = $table === 'fe_users' ? str_replace('phone', 'telephone', $this->fieldList) : $this->fieldList;
                    $fields .= ',tstamp';

                    $tempRepository = GeneralUtility::makeInstance(TempRepository::class);
                    $rows = $tempRepository->fetchRecordsListValues($idLists[$table], $table,
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
     * @throws DBALException
     * @throws Exception
     * @throws AspectNotFoundException
     * @throws \TYPO3\CMS\Core\Resource\Exception
     */
    public function csvImportWizardAction(): ResponseInterface
    {
        $requestHostOnly = $this->request->getUri()->getHost();
        $httpReferer = $this->request->getServerParams()['HTTP_REFERER'];

        /* @var $importService ImportService */
        $importService = GeneralUtility::makeInstance(ImportService::class);
        $importService->init($this->id, $httpReferer, $requestHostOnly);
        $data = $importService->csvImport();

        $this->view->assignMultiple([
            'data' => $data,
        ]);

        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setContent($this->view->render());
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/Tooltip');

        return $this->htmlResponse($moduleTemplate->renderContent());
    }

    /**
     * @param array $csvImport
     * @return ResponseInterface
     * @throws DBALException
     * @throws Exception
     * @throws \TYPO3\CMS\Core\Resource\Exception
     * @throws AspectNotFoundException
     */
    public function csvImportWizardStepConfigurationAction(array $csvImport = []): ResponseInterface
    {
        $requestHostOnly = $this->request->getUri()->getHost();
        $httpReferer = $this->request->getServerParams()['HTTP_REFERER'];

        /* @var $importService ImportService */
        $importService = GeneralUtility::makeInstance(ImportService::class);
        $importService->init($this->id, $httpReferer, $requestHostOnly);
        $data = $importService->csvImport('configuration', $csvImport);

        $this->view->assignMultiple([
            'data' => $data,
        ]);

        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setContent($this->view->render());
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/Tooltip');

        return $this->htmlResponse($moduleTemplate->renderContent());
    }

    /**
     * @param array $csvImport
     * @return ResponseInterface
     * @throws DBALException
     * @throws Exception
     * @throws \TYPO3\CMS\Core\Resource\Exception
     * @throws AspectNotFoundException
     */
    public function csvImportWizardStepMappingAction(array $csvImport = []): ResponseInterface
    {
        $requestHostOnly = $this->request->getUri()->getHost();
        $httpReferer = $this->request->getServerParams()['HTTP_REFERER'];

        /* @var $importService ImportService */
        $importService = GeneralUtility::makeInstance(ImportService::class);
        $importService->init($this->id, $httpReferer, $requestHostOnly);
        $data = $importService->csvImport('mapping', $csvImport);

        $this->view->assignMultiple([
            'data' => $data,
        ]);

        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setContent($this->view->render());
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/Tooltip');

        return $this->htmlResponse($moduleTemplate->renderContent());
    }

    /**
     * @param array $csvImport
     * @return ResponseInterface
     * @throws DBALException
     * @throws Exception
     * @throws \TYPO3\CMS\Core\Resource\Exception
     * @throws AspectNotFoundException
     */
    public function csvImportWizardStepStartImportAction(array $csvImport = []): ResponseInterface
    {
        $requestHostOnly = $this->request->getUri()->getHost();
        $httpReferer = $this->request->getServerParams()['HTTP_REFERER'];

        /* @var $importService ImportService */
        $importService = GeneralUtility::makeInstance(ImportService::class);
        $importService->init($this->id, $httpReferer, $requestHostOnly);
        $data = $importService->csvImport('startImport', $csvImport);

        $this->view->assignMultiple([
            'data' => $data,
        ]);

        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setContent($this->view->render());
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/Tooltip');

        return $this->htmlResponse($moduleTemplate->renderContent());
    }
}
