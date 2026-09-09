<?php

declare(strict_types=1);

namespace GustavoQueiroz\HyperfCrudGenerator\Http;

use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Validation\ValidationException;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** Wraps Hyperf validation so failures before the controller also follow the API contract. */
final class ValidationMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly ResponseInterface $response)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): PsrResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (ValidationException $exception) {
            return $this->response->json([
                'message' => 'Validation failed',
                'errors' => $exception->validator->errors()->getMessages(),
            ])->withStatus(422);
        }
    }
}
