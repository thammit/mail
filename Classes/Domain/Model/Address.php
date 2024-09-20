<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Model;

use TYPO3\CMS\Core\Utility\ClassNamingUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class Address extends AbstractRecipient implements RecipientInterface, CategoryInterface
{
    const ENHANCED_MODEL = \FriendsOfTYPO3\TtAddress\Domain\Model\Address::class;

    /**
     * @var int
     */
    protected int $tstamp = 0;

    public function getRecordIdentifier(): string
    {
        return self::ENHANCED_MODEL . ':' . $this->uid;
    }

    public function getEnhancedData(): array
    {
        $repositoryName = ClassNamingUtility::translateModelNameToRepositoryName(self::ENHANCED_MODEL);
        $repository = GeneralUtility::makeInstance($repositoryName);
        $address = $repository->findByUid($this->uid);
        $enhancedData = parent::getEnhancedData();
        if ($address instanceof \FriendsOfTYPO3\TtAddress\Domain\Model\Address) {
            $enhancedData += [
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
                'tstamp' => $this->tstamp,
            ];
        }

        return $enhancedData;
    }
}
