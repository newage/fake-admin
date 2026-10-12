<?php

declare(strict_types=1);

namespace App\Auth\Middleware;

use Laminas\Diactoros\Response\JsonResponse;
use Mezzio\Authentication\AuthenticationInterface;
use Mezzio\Authentication\UserInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function in_array;
use function str_starts_with;

/**
 * Requires a valid access token for everything under /api/, except an explicit list of public paths.
 * Runs before routing, so nonexistent paths also give 401, not 404.
 */
final class ApiAuthenticationMiddleware implements MiddlewareInterface
{
    private const PREFIX       = '/api/';
    private const PUBLIC_PATHS = ['/api/ping'];

    public function __construct(private readonly AuthenticationInterface $auth)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        if (! str_starts_with($path, self::PREFIX) || in_array($path, self::PUBLIC_PATHS, true)) {
            return $handler->handle($request);
        }

        $user = $this->auth->authenticate($request);
        if ($user === null) {
            return new JsonResponse(['error' => 'unauthorized'], 401, ['WWW-Authenticate' => 'Bearer realm="OAuth2 token"']);
        }

        return $handler->handle($request->withAttribute(UserInterface::class, $user));
    }
}
