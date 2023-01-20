<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Middleware;

use Exception;
use League\HTMLToMarkdown\Converter\TableConverter;
use League\HTMLToMarkdown\HtmlConverter;
use MEDIAESSENZ\Mail\Converter\CategoryCommentConverter;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

if (!Environment::isComposerMode() && !class_exists(TableConverter::class)) {
    // @phpstan-ignore-next-line
    require_once 'phar://' . ExtensionManagementUtility::extPath('mail') . 'Resources/Private/PHP/mail-dependencies.phar/vendor/autoload.php';
}

class MarkdownMiddleware implements MiddlewareInterface
{

    public function __construct(private ResponseFactoryInterface $responseFactory)
    {
    }

    /**
     * This is a preprocessor for the actual jumpurl extension to allow counting of clicked links
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     * @throws Exception
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!($request->getQueryParams()['plain'] ?? false)) {
            return $handler->handle($request);
        }
        $response = $handler->handle($request);
        $responseBody = $response->getBody();
        $markDownResponse = $this->responseFactory->createResponse()->withHeader('Content-Type', 'text/plain');
        $markDownContent = $this->convertHtml2Markdown((string)$responseBody);
        // do not allow more than two line brakes
        $markDownContent = preg_replace("/( \n)/","\n", $markDownContent);
        $markDownContent = preg_replace("/(\n){2,}/","\n\n", $markDownContent);
        $markDownContent = preg_replace("/(\\\_)/","_", $markDownContent);
        $markDownResponse->getBody()->write($markDownContent);
        return $markDownResponse;
    }

    protected function convertHtml2Markdown($html): string
    {
        $converter = new HtmlConverter([
            'strip_tags' => true,
            'hard_break' => true,
            'strip_placeholder_links' => true,
            'remove_nodes' => 'head nav footer img figure',
            'preserve_category_comments' => true
        ]);
        $converter->getEnvironment()->addConverter(new CategoryCommentConverter());
        $converter->getEnvironment()->addConverter(new TableConverter());
        return $converter->convert($html);
    }
}
