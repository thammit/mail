<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Mail;

use InvalidArgumentException;
use League\HTMLToMarkdown\HtmlConverter;
use League\HTMLToMarkdown\Converter\TableConverter;
use MEDIAESSENZ\Mail\Events\MailRenderedEvent;
use MEDIAESSENZ\Mail\Utility\FrontendUtility;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;
use Symfony\Component\Mime\Address;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ControllerContext;
use TYPO3\CMS\Extbase\Mvc\Exception\InvalidControllerNameException;
use TYPO3\CMS\Extbase\Mvc\Exception\InvalidExtensionNameException;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use UnexpectedValueException;

final class MailService
{
    protected MailConfiguration $config;
    protected ?TypoScriptFrontendController $tsfe;

    private ?EventDispatcherInterface $eventDispatcher = null;

    public function __construct(MailConfiguration $config, ?TypoScriptFrontendController $tsfe = null)
    {
        if (!$config->senderEmail) {
            throw new \InvalidArgumentException('Sender email is required', 1663687143);
        }
        if (!$config->templatePaths) {
            throw new \InvalidArgumentException('A template root path is required', 1663687144);
        }
        $this->config = $config;
        $this->tsfe = $tsfe ?? $GLOBALS['TSFE'] ?? null;
    }

    public function injectEventDispatcher(EventDispatcherInterface $eventDispatcher): void
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Create a mail message based on configuration
     *
     * @return MailMessage
     */
    public function createMessage(): MailMessage
    {
        /** @var MailMessage $msg */
        $msg = GeneralUtility::makeInstance(MailMessage::class);

        $msg->from(new Address($this->config->senderEmail, $this->config->senderName));
        if ($this->config->cc) {
            $msg->setCc([$this->config->cc]);
        }
        if ($this->config->bcc) {
            $msg->setBcc([$this->config->bcc]);
        }
        if ($this->config->replyTo) {
            $msg->replyTo(new Address($this->config->replyToEmail, $this->config->replyToName));
        }
        if ($this->config->organization) {
            $msg->getHeaders()->addTextHeader('Organization', $this->config->organization);
        }
        if ($this->config->setAutoSubmittedHeader) {
            $msg->getHeaders()
                ->addTextHeader('Auto-Submitted', 'auto-generated')
                ->addTextHeader('Precedence', 'list')
                ->addTextHeader('X-Auto-Response-Suppress', 'OOF');
        }

        return $msg;
    }

    public function createMailView(
        MailMessage $msg,
        ?ContentObjectRenderer $cObj = null,
        ?ControllerContext $context = null
    ): StandaloneView {
        $mailView = GeneralUtility::makeInstance(StandaloneView::class, $cObj);

        $uriBuilder = FrontendUtility::getFrontendUriBuilder();
        if (!$uriBuilder) {
            throw new RuntimeException('Frontend UriBuilder couldn\'t be created', 1643385471);
        }
        $request = $uriBuilder->getRequest();
        try {
            $request->setControllerName($this->config->controllerName ?: null);
            $request->setPluginName($this->config->pluginName ?: null);
            $request->setControllerExtensionName($this->config->extensionName ?: null);
        } catch (InvalidControllerNameException|InvalidExtensionNameException $e) {
            throw new RuntimeException('Request configuration failed', 1643385470, $e);
        }

        $mailView->getRenderingContext()->setRequest($request);

        $mailView->setLayoutRootPaths($this->config->layoutPaths);
        $mailView->setPartialRootPaths($this->config->partialPaths);

        $mailView->assign('config', $this->config);
        $mailView->assign('msg', $msg);

        return $mailView;
    }

    /**
     * Renders a complete mail
     *
     * Naming convention for templates: &lt;language-iso-2&gt;/&lt;controller&gt;/&lt;email&gt;.(html|txt)
     * e.g. en/booking/confirmation.html
     *
     * The subject used for the email is the first line of the text-template
     *
     * $view must include the request information which controller is currently used
     *
     * If a template in the given language can't be found a fallback to "en" is tried
     *
     * @param StandaloneView $view
     * @param string $email Name of the mail to load
     * @param string $langOverride Use other language than current website language. (de, en, ...)
     *
     * @return MailContent
     * @throws RuntimeException
     */
    public function renderMail(StandaloneView $view, string $email, string $langOverride = ''): MailContent
    {
        if (empty($langOverride)) {
            if ($this->tsfe) {
                $languagePath = $this->tsfe->getLanguage()->getTwoLetterIsoCode();
            } else {
                throw new InvalidArgumentException('No language given');
            }
        } else {
            $languagePath = $langOverride;
        }

        $languagePath = $this->config->validatedLanguage($languagePath);

        $backupFormat = $view->getFormat();

        $templatePath = $view->getRequest()->getControllerName() . '/' . $email;

        $templateSources = $this->getTemplateSources($languagePath, $templatePath);

        $view->setTemplateSource($templateSources->html);
        $view->setFormat('html');
        $html = $view->render();

        if (!$html) {
            throw new RuntimeException(
                sprintf(
                    'The requested HTML mail template "%s/%s" does not exist or is empty',
                    $view->getRequest()->getControllerName(),
                    $email
                ),
                1409913173
            );
        }

        $view->setTemplateSource($templateSources->text);
        // changing the format is crucial so that Fluid uses the right partials (e.g. footer.txt)
        $view->setFormat('txt');
        // if text template is empty, render may return null
        $text = (string)$view->render();

        $view->setFormat($backupFormat);

        // gather subject from first line of the templates (HTML wins)
        $subject = '';
        if ($text !== '') {
            $parts = explode("\n", $text, 2);
            $text = trim($parts[1]);
            $subject = trim($parts[0]);
        }
        if ($html !== '') {
            $parts = explode("\n", $html, 2);
            $html = trim($parts[1]);
            if (!$subject) {
                $subject = trim($parts[0]);
            }
        }

        // render text version fallback via HTML to markdown converter
        if ($text === '' && $html) {
            $converter = new HtmlConverter(['strip_tags' => true]);
            $converter->getEnvironment()->addConverter(new TableConverter());
            $text = $converter->convert($html);
        }

        $mailContent = new MailContent($subject, $text, $html);

        if (!$this->eventDispatcher) {
            return $mailContent;
        }

        /** @var MailRenderedEvent $event */
        $event = $this->eventDispatcher->dispatch(
            new MailRenderedEvent($mailContent)
        );
        return $event->getMailContent();
    }

    /**
     * Loads an email template
     * Naming convention for templates: &lt;language-iso-2&gt;/&lt;controller&gt;/&lt;email&gt;.(html|txt)
     *
     * This will automatically fallback to English ("en/" subfolder), if not present in the provided language subfolder.
     *
     * @param string $languagePath Language subfolder
     * @param string $templatePath Path to template within language subfolder
     *
     * @return MailContent
     */
    private function getTemplateSources(string $languagePath, string $templatePath): MailContent
    {
        $htmlTemplatePath = $templatePath . '.html';
        $mailCandidates = [
            $languagePath . '/' . $htmlTemplatePath,
            'en/' . $htmlTemplatePath
        ];
        $templateFilePath = '';
        while (!$templateFilePath && $mail = array_shift($mailCandidates)) {
            $templateFilePath = $this->resolveTemplatePath($this->config->templatePaths, $mail);
        }
        if (!$templateFilePath) {
            throw new UnexpectedValueException('No valid HTML mail template found for ' . $htmlTemplatePath);
        }

        $plainPath = str_replace('.html', '.txt', $templateFilePath);

        return new MailContent('', file_exists($plainPath) ? file_get_contents($plainPath) : '', file_get_contents($templateFilePath));
    }

    private function resolveTemplatePath(array $paths, string $fileName): string
    {
        $templatePaths = ArrayUtility::sortArrayWithIntegerKeys($paths);
        $templatePaths = array_reverse($templatePaths, true);
        $templateFilePath = '';
        foreach ($templatePaths as $path) {
            $path .= $fileName;
            $path = GeneralUtility::getFileAbsFileName($path);
            if ($path && file_exists($path)) {
                $templateFilePath = $path;
                break;
            }
        }

        return $templateFilePath;
    }
}
