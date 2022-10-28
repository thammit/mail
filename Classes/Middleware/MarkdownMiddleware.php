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
        if (!$request->getQueryParams()['plain']) {
            return $handler->handle($request);
        }
        $response = $handler->handle($request);
        $responseBody = $response->getBody();
        $size = $responseBody->getSize();
        $markDownResponse = $this->responseFactory->createResponse()->withHeader('Content-Type', 'text/plain');
        $markDownResponse->getBody()->write($this->convertHtml2Markdown((string)$responseBody));
        return $markDownResponse;
    }

    protected function convertHtml2Markdown($html): string
    {
        $converter = new HtmlConverter([
            'strip_tags' => true,
            'strip_placeholder_links' => true,
            'remove_nodes' => 'head nav footer',
            'preserve_category_comments' => true
        ]);
        $converter->getEnvironment()->addConverter(new CategoryCommentConverter());
        $converter->getEnvironment()->addConverter(new TableConverter());
        return $converter->convert($html);
    }
}
