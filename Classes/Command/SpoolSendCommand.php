<?php

namespace MEDIAESSENZ\Mail\Command;

use MEDIAESSENZ\Mail\Mail\Mailer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Mail\DelayedTransportInterface;
use TYPO3\CMS\Core\Mail\FileSpool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class SpoolSendCommand extends Command
{
    /**
     * Configure the command by defining the name, options and arguments
     */
    public function configure()
    {
        $this->setDescription('This command invokes EXT:mail mailer in order to process queued messages.');
        $this
            ->addOption('site-identifier', null, InputOption::VALUE_REQUIRED, 'The site identifier for mail settings.', '')
            ->addOption('message-limit', null, InputOption::VALUE_REQUIRED, 'The maximum number of messages to send.')
            ->addOption('time-limit', null, InputOption::VALUE_REQUIRED, 'The time limit for sending messages (in seconds).')
            ->addOption('recover-timeout', null, InputOption::VALUE_REQUIRED, 'The timeout for recovering messages that have taken too long to send (in seconds).');
    }

    /**
     * Executes the mailer command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $siteIdentifier = $input->getOption('site-identifier');
        $mailer = $this->getMailer($siteIdentifier);

        $transport = $mailer->getTransport();
        if ($transport instanceof DelayedTransportInterface) {
            if ($transport instanceof FileSpool) {
                $transport->setMessageLimit((int)$input->getOption('message-limit'));
                $transport->setTimeLimit((int)$input->getOption('time-limit'));
                $recoverTimeout = (int)$input->getOption('recover-timeout');
                if ($recoverTimeout) {
                    $transport->recover($recoverTimeout);
                } else {
                    $transport->recover();
                }
            }
            $sent = $transport->flushQueue($mailer->getRealTransport($siteIdentifier));
            $io->comment($sent . ' emails sent');
            return Command::SUCCESS;
        }
        $io->error('The Mailer Transport "transport_spool_type" is not set to "file" or "memory".');

        return Command::FAILURE;
    }

    /**
     * Returns the mail mailer.
     *
     * @param string $siteIdentifier
     * @return Mailer
     * @throws Exception
     */
    protected function getMailer(string $siteIdentifier = ''): Mailer
    {
        $mailer = GeneralUtility::makeInstance(Mailer::class);
        $mailer->init($siteIdentifier);
        return $mailer;
    }
}
