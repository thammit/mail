<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Command;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use MEDIAESSENZ\Mail\Service\MailerService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class MassMailingCommand extends Command
{

    /**
     * Configure the command by defining the name, options and arguments
     */
    public function configure()
    {
        $this->setDescription('Sends planed mass mails from EXT:mail');
        $this->addOption('site-identifier', null, InputOption::VALUE_REQUIRED, 'The site identifier for mail settings.', '');
        $this->addOption('send-per-cycle', null, InputOption::VALUE_REQUIRED, 'Send per cycle');
        $this->setHelp('Sends newsletters which are ready to send. Depend on how many newsletters are planned or left to get send out and the extension configuration for number of messages to be sent per cycle, this command will send the latest open newsletter queue, like the recommended scheduler task or BE module for invoking mailer engine will do.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title($this->getDescription());

        /*
        $lockfile = Environment::getPublicPath() . '/typo3temp/tx_mail_cron.lock';

        // Check if cronjob is already running:
        if (@file_exists($lockfile)) {
            // If the lock is not older than 1 day, skip:
            if (filemtime($lockfile) > (time() - (60 * 60 * 24))) {
                $io->warning('TYPO3 Mail Cron: Aborting, another process is already running!');
                return Command::FAILURE;
            } else {
                $io->writeln('TYPO3 Mail Cron: A .lock file was found but it is older than 1 day! Processing mails ...');
            }
        }

        touch($lockfile);
        // Fix file permissions
        GeneralUtility::fixPermissions($lockfile);
        */

        /**
         * The direct_mail engine
         * @var $mailerService MailerService
         */
        $mailerService = GeneralUtility::makeInstance(MailerService::class);
        $mailerService->setSiteIdentifier($input->getOption('site-identifier'));
        $mailerService->start((int)$input->getOption('send-per-cycle'));
        try {
            $mailerService->runcron();
        } catch (DBALException $e) {
            $io->warning('DBALException: ' . $e->getMessage());
            return Command::FAILURE;
        } catch (Exception $e) {
            $io->warning('Exception: ' . $e->getMessage());
            return Command::FAILURE;
        } catch (TransportExceptionInterface $e) {
            $io->warning('TransportExceptionInterface: ' . $e->getMessage());
            return Command::FAILURE;
        } catch (ExtensionConfigurationExtensionNotConfiguredException $e) {
            $io->warning('ExtensionConfigurationExtensionNotConfiguredException: ' . $e->getMessage());
            return Command::FAILURE;
        } catch (ExtensionConfigurationPathDoesNotExistException $e) {
            $io->warning('ExtensionConfigurationPathDoesNotExistException: ' . $e->getMessage());
            return Command::FAILURE;
        } catch (\TYPO3\CMS\Core\Exception $e) {
            $io->warning('TYPO3\CMS\Core\Exception: ' . $e->getMessage());
            return Command::FAILURE;
        }

        // unlink($lockfile);
        return Command::SUCCESS;
    }
}
