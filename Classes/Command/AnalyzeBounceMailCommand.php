<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Command;

use Doctrine\DBAL\DBALException;
use Fetch\Message;
use Fetch\Server;
use MEDIAESSENZ\Mail\Domain\Repository\LogRepository;
use MEDIAESSENZ\Mail\Type\Enumeration\ResponseType;
use MEDIAESSENZ\Mail\Utility\BounceMailUtility;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

if (!class_exists(Server::class) && !Environment::isComposerMode()) {
    // @phpstan-ignore-next-line
    @include 'phar://' . ExtensionManagementUtility::extPath('mail') . 'Resources/Private/PHP/mail-dependencies.phar/vendor/autoload.php';
}

class AnalyzeBounceMailCommand extends Command
{

    private LanguageService $languageService;

    public function __construct(
        private LanguageServiceFactory $languageServiceFactory,
        private Context $context,
        private LogRepository $logRepository,
        string $name = null
    )
    {
        $this->languageService = $this->languageServiceFactory->create('default');
        $this->languageService->includeLLFile('EXT:mail/Resources/Private/Language/Modules.xlf');
        parent::__construct($name);
    }

    /**
     * Configure the command by defining the name, options and arguments
     */
    public function configure(): void
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
        try {
            /** @var Server $mailServer */
            $mailServer = GeneralUtility::makeInstance(
                Server::class,
                $server,
                $port,
                $type
            );

            $mailServer->setAuthentication($user, $password);
            $mailServer->getImapStream();
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
        } catch (\Exception $e) {
            $io->error($this->languageService->getLL('scheduler.bounceMail.dataVerification') . $e->getMessage());
        }

        return Command::FAILURE;
    }

    /**
     * Process the bounce mail
     * @param Message $message the message object
     * @return bool true if bounce mail can be parsed, else false
     * @throws DBALException
     */
    private function processBounceMail(Message $message): bool
    {
        $mailData = BounceMailUtility::getMailDataFromHeader($message);

        if (!$mailData) {
            // no mid, rid and rtbl found - exit
            return false;
        }

        $row = $this->logRepository->findOneByRecipientUidAndRecipientSourceIdentifierAndMailUid($mailData['recipient_uid'], $mailData['recipient_source'], $mailData['mail']);

        if ($row) {
            try {
                $analyzeResult = BounceMailUtility::analyseReturnError($message->getMessageBody());
                $insertFields = [
                    'tstamp' => $this->context->getPropertyFromAspect('date', 'timestamp'),
                    'response_type' => ResponseType::FAILED,
                    'mail' => (int)$mailData['mail'],
                    'recipient_uid' => (int)$mailData['recipient_uid'],
                    'recipient_source' => $mailData['recipient_source'],
                    'email' => $row['email'],
                    'return_content' => json_encode($analyzeResult, JSON_THROW_ON_ERROR),
                    'return_code' => (int)$analyzeResult['reason'],
                ];

                return $this->logRepository->insertRecord($insertFields) === 1;
            } catch (\Exception $e) {
            }
        }

        return false;
    }
}
