<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Command;

use Doctrine\DBAL\Exception;
use Ddeboer\Imap\Message;
use Ddeboer\Imap\Server;
use MEDIAESSENZ\Mail\Domain\Repository\LogRepository;
use MEDIAESSENZ\Mail\Type\Enumeration\ResponseType;
use MEDIAESSENZ\Mail\Utility\BounceMailUtility;
use MEDIAESSENZ\Mail\Utility\LanguageUtility;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Localization\LanguageService;

class AnalyzeBounceMailCommand extends Command
{

    private LanguageService $languageService;

    public function __construct(
        private Context $context,
        private LogRepository $logRepository,
        string $name = null
    )
    {
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
                'Port number',
                '993'
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
        $port = '993';
        $user = '';
        $password = '';

        // check if PHP IMAP is installed
        if (!extension_loaded('imap')) {
            $io->error(LanguageUtility::getLL('scheduler.bounceMail.phpImapError'));
            return Command::FAILURE;
        }

        if ($input->getOption('server')) {
            $server = $input->getOption('server');
        }

        if ($input->getOption('port')) {
            $port = (string)$input->getOption('port');
        }

        if ($input->getOption('user')) {
            $user = $input->getOption('user');
        }

        if ($input->getOption('password')) {
            $password = $input->getOption('password');
        }

        try {
            // try to connect to mail server
            $connection = (new Server($server, $port))->authenticate($user, $password);
            $messages = $connection->getMailbox('INBOX')->getMessages();

            if (count($messages)) {
                /** @var Message $message The message object */
                foreach ($messages as $message) {
                    // process the mail
                    if ($this->processBounceMail($message)) {
                        // set delete
                        $message->delete();
                    } else {
                        $message->setFlag('SEEN');
                    }
                }
            }

            // expunge to delete permanently
            $connection->expunge();

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error(LanguageUtility::getLL('scheduler.bounceMail.dataVerification') . $e->getMessage());
        }

        return Command::FAILURE;
    }

    /**
     * Process the bounce mail
     * @param Message $message the message object
     * @return bool true if bounce mail can be parsed, else false
     * @throws Exception
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
                $analyzeResult = BounceMailUtility::analyseReturnError($message->getRawMessage());
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
