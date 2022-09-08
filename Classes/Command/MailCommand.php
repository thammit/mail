<?php

namespace MEDIAESSENZ\Mail\Command;

use MEDIAESSENZ\Mail\Dmailer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class MailCommand extends Command
{
    /**
     * Configure the command by defining the name, options and arguments
     */
    public function configure()
    {
        $this->setDescription('This command invokes dmailer in order to process queued messages.');
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
        /**
         * The direct_mail engine
         * @var $htmlmail Dmailer
         */
        $htmlmail = GeneralUtility::makeInstance(Dmailer::class);
        $htmlmail->start();
        $htmlmail->runcron();
        return Command::SUCCESS;
    }
}
