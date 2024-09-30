<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Command;

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
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class MassMailingCommand extends Command
{

    /**
     * Configure the command by defining the name, options and arguments
     */
    public function configure(): void
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
     * @throws SiteNotFoundException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title($this->getDescription());

        /** @var MailerService $mailerService */
        $mailerService = GeneralUtility::makeInstance(MailerService::class);
        $mailerService->setSiteIdentifier($input->getOption('site-identifier'));
        $mailerService->start((int)$input->getOption('send-per-cycle'));

        if (!($GLOBALS['TYPO3_REQUEST'] ?? false)) {
            // If this command is called in cli context there is no request object which is needed by extbase
            // As a workaround we create a request object here
            $io->note('No request object found. Create a fake request object to make extbase happy ...');
            $site = GeneralUtility::makeInstance(SiteFinder::class)->getSiteByIdentifier($input->getOption('site-identifier'));
            $request = (new ServerRequest())
                ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
                ->withAttribute('site', $site);
            $GLOBALS['TYPO3_REQUEST'] = $request;
        }

        try {
            $mailerService->handleQueue();
        } catch (\Doctrine\DBAL\Exception $e) {
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
        } catch (\JsonException $e) {
            $io->warning('JsonException: ' . $e->getMessage());
        }

        // unlink($lockfile);
        return Command::SUCCESS;
    }
}
