<?php

declare(strict_types=1);

namespace GustavoQueiroz\HyperfCrudGeneratorTest\Unit;

use GustavoQueiroz\HyperfCrudGenerator\Authorization\ConfigAuthorization;
use GustavoQueiroz\HyperfCrudGenerator\Http\DocumentationController;
use GustavoQueiroz\HyperfCrudGenerator\Http\ValidationMiddleware;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\MessageBag;
use Hyperf\Contract\ValidatorInterface;
use Hyperf\HttpMessage\Server\Response;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Validation\ValidationException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class HttpSupportTest extends TestCase
{
    private function response(): ResponseInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('json')->willReturnCallback(static fn ($data) => (new Response())->withContent(json_encode($data))->withHeader('Content-Type', 'application/json'));
        $response->method('raw')->willReturnCallback(static fn ($data) => (new Response())->withContent($data));
        $response->method('html')->willReturnCallback(static fn ($data) => (new Response())->withContent($data));
        return $response;
    }

    public function testAuthorizationReceivesRequestAndSubjectAndCanDeny(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $subject = new \stdClass();
        $config = $this->createMock(ConfigInterface::class);
        $config->method('get')->willReturnCallback(static function ($key, $default = null) use ($request, $subject) {
            return match ($key) {
                'crud_generator.authorization.rules.User.view' => static function ($actualRequest, $actualSubject) use ($request, $subject) {
                    self::assertSame($request, $actualRequest);
                    self::assertSame($subject, $actualSubject);
                    return true;
                },
                'crud_generator.authorization.default' => 'deny',
                default => $default,
            };
        });
        $authorization = new ConfigAuthorization($config, $request);
        self::assertTrue($authorization->allows('User', 'view', $subject));
        self::assertFalse($authorization->allows('User', 'delete', $subject));
    }

    public function testDocumentationServesJsonAndRejectsTraversal(): void
    {
        defined('BASE_PATH') || define('BASE_PATH', dirname(__DIR__) . '/output');
        $config = $this->createMock(ConfigInterface::class);
        $config->method('get')->willReturn(dirname(__DIR__) . '/output/docs/openapi');
        $controller = new DocumentationController($config, $this->response());
        self::assertSame(404, $controller->spec('../../composer')->getStatusCode());
        self::assertSame(404, $controller->spec('missing')->getStatusCode());
        $response = $controller->spec('user');
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('3.0.3', json_decode((string) $response->getBody(), true)['openapi']);
        self::assertStringContainsString('/docs/openapi/user.json', (string) $controller->index()->getBody());
    }

    public function testMiddlewareTurnsPreControllerValidationFailureIntoJson422(): void
    {
        $validator = $this->createMock(ValidatorInterface::class);
        $messages = $this->createMock(MessageBag::class);
        $messages->method('messages')->willReturn(['email' => ['Required']]);
        $validator->method('errors')->willReturn($messages);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new ValidationException($validator));
        $result = (new ValidationMiddleware($this->response()))->process($this->createMock(ServerRequestInterface::class), $handler);
        self::assertSame(422, $result->getStatusCode());
        self::assertSame(['email' => ['Required']], json_decode((string) $result->getBody(), true)['errors']);
    }
}
