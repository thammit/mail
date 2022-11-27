<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Model;

use TYPO3\CMS\Core\Utility\ClassNamingUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class Address extends AbstractRecipient implements RecipientInterface
{
    const ENHANCED_MODEL = \FriendsOfTYPO3\TtAddress\Domain\Model\Address::class;

    public function getRecordIdentifier(): string
    {
        return self::ENHANCED_MODEL . ':' . $this->uid;
    }

    public function getCsvExportData(): array
    {
        $categories = [];
        if ($this->categories->count() > 0) {
            foreach ($this->categories as $category) {
                $categories[] = $category->getTitle();
            }
        }
        $csvExportArray = [
            'uid' => $this->uid,
            'email' => $this->email,
            'name' => $this->name,
            'active' => $this->active ? '1' : '0',
            'accepts_html' => $this->acceptsHtml,
            'categories' => implode(', ', $categories)
        ];

        $repositoryName = ClassNamingUtility::translateModelNameToRepositoryName(self::ENHANCED_MODEL);
        $repository = GeneralUtility::makeInstance($repositoryName);
        $address = $repository->findByUid($this->uid);
        $additionalFields = [];
        if ($address instanceof \FriendsOfTYPO3\TtAddress\Domain\Model\Address) {
            $additionalFields = [
                'gender' => $address->getGender(),
                'title' => $address->getTitle(),
                'first_name' => $address->getFirstName(),
                'middle_name' => $address->getMiddleName(),
                'last_name' => $address->getLastName(),
                'phone' => $address->getPhone(),
                'mobile' => $address->getMobile(),
                'www' => $address->getWww(),
                'fax' => $address->getFax(),
                'company' => $address->getCompany(),
                'address' => $address->getAddress(),
                'zip' => $address->getZip(),
                'city' => $address->getCity(),
                'country' => $address->getCountry(),
            ];
        }

        return $csvExportArray + $additionalFields;
    }
}
