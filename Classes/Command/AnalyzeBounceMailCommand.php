<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Command;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use Fetch\Message;
use Fetch\Server;
use MEDIAESSENZ\Mail\Utility\BounceMailUtility;
use MEDIAESSENZ\Mail\Domain\Repository\SysDmailMaillogRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class AnalyzeBounceMailCommand extends Command
{

    private LanguageService $languageService;

    public function __construct(private readonly LanguageServiceFactory $languageServiceFactory, private readonly Context $context, string $name = null)
    {
        $this->languageService = $this->languageServiceFactory->create('default');
        $this->languageService->includeLLFile('EXT:mail/Resources/Private/Language/Modules.xlf');
        parent::__construct($name);
    }

    /**
     * Configure the command by defining the name, options and arguments
     */
    public function configure()
    {
        $this->setDescription('This command will get bounce mail from the configured mailbox')
            ->addOption(
                'server',
                's',
                InputOption::VALUE_REQUIRED,
                'Server URL/IP'
            )
            ->addOption(
                'port',
                'p',
                InputOption::VALUE_REQUIRED,
                'Port number'
            )
            ->addOption(
                'user',
                'u',
                InputOption::VALUE_REQUIRED,
                'Username'
            )
            ->addOption(
                'password',
                'pw',
                InputOption::VALUE_REQUIRED,
                'Password'
            )
            ->addOption(
                'type',
                't',
                InputOption::VALUE_REQUIRED,
                'Type of mailserver (imap or pop3)'
            )
            ->addOption(
                'count',
                'c',
                InputOption::VALUE_REQUIRED,
                'Number of bounce mail to be processed'
            )//->setHelp('')
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws DBALException
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title($this->getDescription());

        $server = '';
        $port = 0;
        $user = '';
        $password = '';
        $type = '';
        $count = 0;
        // check if PHP IMAP is installed
        if (!extension_loaded('imap')) {
            $io->error($this->languageService->getLL('scheduler.bounceMail.phpImapError'));
            return Command::FAILURE;
        }

        if ($input->getOption('server')) {
            $server = $input->getOption('server');
            //$io->writeln($server);
        }
        if ($input->getOption('port')) {
            $port = (int)$input->getOption('port');
            //$io->writeln($port);
        }
        if ($input->getOption('user')) {
            $user = $input->getOption('user');
            //$io->writeln($user);
        }
        if ($input->getOption('password')) {
            $password = $input->getOption('password');
            //$io->writeln($password);
        }
        if ($input->getOption('type')) {
            $type = $input->getOption('type');
            //$io->writeln($type);
            if (!in_array($type, ['imap', 'pop3'])) {
                $io->warning('Type: only imap or pop3');
                return Command::FAILURE;
            }
        }
        if ($input->getOption('count')) {
            $count = (int)$input->getOption('count');
            //$io->writeln($count);
        }

        // try to connect to mail server
        $mailServer = $this->connectMailServer($server, $port, $type, $user, $password, $io);
        if ($mailServer instanceof Server) {
            // we are connected to mail server
            // get unread mails
            $messages = $mailServer->search('UNSEEN', $count);
            if (count($messages)) {
                /** @var Message $message The message object */
                foreach ($messages as $message) {
                    // process the mail
                    if ($this->processBounceMail($message)) {
                        //$io->writeln($message->getSubject());
                        // set delete
                        $message->delete();
                    } else {
                        $message->setFlag('SEEN');
                    }
                }
            }
            // expunge to delete permanently
            $mailServer->expunge();
            imap_close($mailServer->getImapStream());
            return Command::SUCCESS;
        }

        return Command::FAILURE;
    }

    /**
     * Process the bounce mail
     * @param Message $message the message object
     * @return bool true if bounce mail can be parsed, else false
     * @throws DBALException
     * @throws Exception
     */
    private function processBounceMail(Message $message): bool
    {
        $midArray = BounceMailUtility::searchForHeaderData($message, 'X-TYPO3MID:');

        if (!$midArray) {
            // no mid, rid and rtbl found - exit
            return false;
        }

        // Extract text content
        $cp = BounceMailUtility::analyseReturnError($message->getMessageBody());

        $row = GeneralUtility::makeInstance(SysDmailMaillogRepository::class)->selectForAnalyzeBounceMail($midArray['recipient_uid'], $midArray['recipient_table'], $midArray['mail']);

        // only write to log table, if we found a corresponding recipient record
        if (!empty($row)) {
            $tableMaillog = 'tx_mail_domain_model_log';
            /** @var Connection $connection */
            $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($tableMaillog);
            try {
                $insertFields = [
                    'tstamp' => $this->context->getPropertyFromAspect('date', 'timestamp'),
                    'response_type' => -127,
                    'mail' => (int)$midArray['mail'],
                    'recipient_uid' => (int)$midArray['recipient_uid'],
                    'recipient_table' => $midArray['recipient_table'],
                    'email' => $row['email'],
                    'return_content' => serialize($cp),
                    'return_code' => (int)$cp['reason'],
                ];
                $connection->insert($tableMaillog, $insertFields);
                $lastInsertId = $connection->lastInsertId($tableMaillog);

                return (bool)$lastInsertId;
            } catch (\Exception $e) {
            }
        }

        return false;
    }

    /**
     * Create connection to mail server.
     * Return mailServer object or false on error
     *
     * @param string $server
     * @param int $port
     * @param string $type
     * @param string $user
     * @param string $password
     * @param SymfonyStyle $io
     * @return bool|Server
     */
    private function connectMailServer(string $server, int $port, string $type, string $user, string $password, SymfonyStyle $io): bool|Server
    {
        // check if we can connect using the given data
        /** @var Server $mailServer */
        $mailServer = GeneralUtility::makeInstance(
            Server::class,
            $server,
            $port,
            $type
        );

        // set mail username and password
        $mailServer->setAuthentication($user, $password);

        try {
            $mailServer->getImapStream();
            return $mailServer;
        } catch (\Exception $e) {
            $io->error($this->languageService->getLL('scheduler.bounceMail.dataVerification') . $e->getMessage());
            return false;
        }
    }
}
