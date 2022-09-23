<?php

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace MEDIAESSENZ\Mail\Mail;

use RuntimeException;
use Symfony\Component\Mailer\Transport\TransportInterface;
use TYPO3\CMS\Core\Exception as CoreException;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Adapter for Symfony/Mailer to be used by TYPO3 extensions.
 *
 * This will use the setting in TYPO3_CONF_VARS to choose the correct transport
 * for it to work out-of-the-box.
 */
class Mailer extends \TYPO3\CMS\Core\Mail\Mailer
{
    /**
     * @throws CoreException
     */
    public function init(string $siteIdentifier = ''): void
    {
        $this->injectMailSettings($this->getMailSettings($siteIdentifier));

        try {
            $this->initializeTransport();
        } catch (\Exception $e) {
            throw new CoreException($e->getMessage(), 1291068569);
        }
    }

    protected function getMailSettings(string $siteIdentifier = ''): array
    {
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $globalMailSettings = (array)$GLOBALS['TYPO3_CONF_VARS']['MAIL'];
        $siteMailSettings = [];
        if ($siteIdentifier) {
            try {
                $site = $siteFinder->getSiteByIdentifier($siteIdentifier);
                $siteMailSettings = $site->getConfiguration()['mail'] ?? [];
            } catch (SiteNotFoundException $exception) {
                // Site is not found -> use mail settings from TYPO3_CONF_VARS
            }
        } else {
            // if no identifier is set use first site if exists
            $allSites = $siteFinder->getAllSites();
            if (count($allSites) > 0) {
                $site = reset($allSites);
                $siteMailSettings = $site->getConfiguration()['mail'] ?? [];
            }
        }
        return array_replace_recursive($globalMailSettings, $siteMailSettings);
    }

    /**
     * Returns the real transport (not a spool).
     *
     * @param string $siteIdentifier
     * @return TransportInterface
     * @throws CoreException
     */
    public function getRealTransport(string $siteIdentifier = ''): TransportInterface
    {
        $mailSettings = !empty($this->mailSettings) ? $this->mailSettings : $this->getMailSettings($siteIdentifier);
        unset($mailSettings['transport_spool_type']);
        return $this->getTransportFactory()->get($mailSettings);
    }

    /**
     * Prepares a transport using the TYPO3_CONF_VARS configuration
     *
     * Used options:
     * $TYPO3_CONF_VARS['MAIL']['transport'] = 'smtp' | 'sendmail' | 'null' | 'mbox'
     *
     * $TYPO3_CONF_VARS['MAIL']['transport_smtp_server'] = 'smtp.example.org:25';
     * $TYPO3_CONF_VARS['MAIL']['transport_smtp_encrypt'] = FALSE; # requires openssl in PHP
     * $TYPO3_CONF_VARS['MAIL']['transport_smtp_username'] = 'username';
     * $TYPO3_CONF_VARS['MAIL']['transport_smtp_password'] = 'password';
     *
     * $TYPO3_CONF_VARS['MAIL']['transport_sendmail_command'] = '/usr/sbin/sendmail -bs'
     *
     * @throws CoreException
     * @throws RuntimeException
     */
    private function initializeTransport()
    {
        $this->transport = $this->getTransportFactory()->get($this->mailSettings);
    }
}
