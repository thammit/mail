<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Controller;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use MEDIAESSENZ\Mail\Domain\Model\Group;
use MEDIAESSENZ\Mail\Service\ImportService;
use MEDIAESSENZ\Mail\Type\Enumeration\RecipientGroupType;
use MEDIAESSENZ\Mail\Utility\BackendUserUtility;
use MEDIAESSENZ\Mail\Utility\CsvUtility;
use MEDIAESSENZ\Mail\Utility\LanguageUtility;
use MEDIAESSENZ\Mail\Utility\RecipientUtility;
use MEDIAESSENZ\Mail\Utility\ViewUtility;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Exception\StopActionException;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapper;

class RecipientController extends AbstractController
{
    protected string $fieldList = 'uid,name,first_name,middle_name,last_name,title,email,phone,www,address,company,city,zip,country,fax,categories,mail_html';

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
                    $typeProcessed .= ' (' . BackendUtility::getProcessedValue('tx_mail_domain_model_group', 'record_types', $group->getRecordTypes()) . ')';
                    break;
                case RecipientGroupType::MODEL:
                    $typeProcessed .= ' (' . $group->getRecordType() . ')';
                    break;
                case RecipientGroupType::STATIC:
                    $typeProcessed .= ' (' . $group->getStaticList() . ' Records)';
                    break;
            }
            $data[] = [
                'group' => $group,
                'typeProcessed' => $typeProcessed,
                'categories' => in_array($group->getType(), [RecipientGroupType::PAGES, RecipientGroupType::MODEL]) ? $group->getCategories() : [],
                'count' => RecipientUtility::calculateTotalRecipientsOfUidLists($this->recipientService->getRecipientsUidListsGroupedByTable($group, $this->siteConfiguration)),
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
     * @throws UnknownObjectException
     * @throws \Doctrine\DBAL\Exception
     * @throws InvalidQueryException
     * @throws \TYPO3\CMS\Extbase\Persistence\Generic\Exception
     */
    public function showAction(Group $group): ResponseInterface
    {
        $idLists = $this->recipientService->getRecipientsUidListsGroupedByTable($group, $this->siteConfiguration);
        $totalRecipients = RecipientUtility::calculateTotalRecipientsOfUidLists($idLists);

        $data = [
            'uid' => $group->getUid(),
            'title' => $group->getTitle(),
            'totalRecipients' => $totalRecipients,
            'tables' => [],
            'special' => [],
        ];

        foreach ($idLists as $tableName => $idList) {
            $categoryColumn = true;
            $htmlColumn = true;
            $model = $this->siteConfiguration['RecipientGroups'][$tableName]['model'] ?? false;
            if ($model) {
                $rows = $this->recipientService->getRecipientsDataByUidListAndModelName($idList, $model);
            } else if (str_contains($tableName, 'Domain\\Model')) {
                $rows = $this->recipientService->getRecipientsDataByUidListAndModelName($idList, $tableName);
                $dataMapper = GeneralUtility::makeInstance(DataMapper::class);
                $tableName = $dataMapper->getDataMap($tableName)->getTableName();
            } else if ($tableName === 'tx_mail_domain_model_group') {
                $rows = $idLists['tx_mail_domain_model_group'];
                $categoryColumn = false;
                $htmlColumn = false;
            } else {
                $rows = $this->recipientService->getRecipientsDataByUidListAndTable($idList, $tableName);
            }
            $data['tables'][$tableName] = [
                'table' => $tableName,
                'recipients' => $rows,
                'numberOfRecipients' => count($rows),
                'categoryColumn' => $categoryColumn,
                'htmlColumn' => $htmlColumn,
                'show' => BackendUserUtility::getBackendUser()->check('tables_select', $tableName),
                'edit' => BackendUserUtility::getBackendUser()->check('tables_modify', $tableName),
            ];
        }

        $this->view->assign('data', $data);

        $this->moduleTemplate->setContent($this->view->render());
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/Tooltip');
        $this->addDocheaderButtons($group->getTitle());

        return $this->htmlResponse($this->moduleTemplate->renderContent());
    }

    /**
     * @param Group $group
     * @param string $table
     * @return void
     * @throws DBALException
     * @throws Exception
     * @throws IllegalObjectTypeException
     * @throws InvalidQueryException
     * @throws StopActionException
     * @throws UnknownObjectException
     * @throws \Doctrine\DBAL\Exception
     * @throws \TYPO3\CMS\Extbase\Persistence\Generic\Exception
     */
    public function csvDownloadAction(Group $group, string $table): void
    {
        $idLists = $this->recipientService->getRecipientsUidListsGroupedByTable($group, $this->siteConfiguration);

        foreach ($idLists as $tableName => $idList) {
            $model = $this->siteConfiguration['RecipientGroups'][$tableName]['model'] ?? false;
            if ($model) {
                $rows = $this->recipientService->getRecipientsDataByUidListAndModelName($idList, $model, []);
            } else if (str_contains($tableName, 'Domain\\Model')) {
                $rows = $this->recipientService->getRecipientsDataByUidListAndModelName($idList, $tableName, []);
                $dataMapper = GeneralUtility::makeInstance(DataMapper::class);
                $tableName = $dataMapper->getDataMap($tableName)->getTableName();
            } else if ($tableName === 'tx_mail_domain_model_group') {
                $rows = $idLists['tx_mail_domain_model_group'];
            } else {
                $fields = $tableName === 'fe_users' ? str_replace('phone', 'telephone', $this->fieldList) : $this->fieldList;
                $fields .= ',tstamp';
                $rows = $this->recipientService->getRecipientsDataByUidListAndTable($idList, $tableName, GeneralUtility::trimExplode(',', $fields, true));
            }
            if ($tableName === $table) {
                if (BackendUserUtility::getBackendUser()->check('tables_select', $tableName)) {
                    CsvUtility::downloadCSV($rows);
                } else {
                    ViewUtility::addFlashMessageError('', LanguageUtility::getLL('recipient.notification.disallowedCsvExport.message'), true);
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
