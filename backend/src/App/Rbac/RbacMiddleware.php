<?php

declare(strict_types=1);

namespace App\Rbac;

use App\Auth\Repository\ScopeRepository;
use Doctrine\DBAL\Connection;
use Laminas\Diactoros\Response\JsonResponse;
use Mezzio\Authentication\UserInterface;
use Mezzio\Router\RouteResult;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function is_array;
use function is_string;
use function json_decode;
use function str_starts_with;
use function strlen;
use function substr;

use const JSON_THROW_ON_ERROR;

/**
 * Lets the request through only if the token's role has permission for the route key (`options.permission`)
 * in the `role_permissions` table. A route without a declared permission is closed (deny by default).
 * Requests without a user (public paths already passed by authentication) are not checked.
 */
final class RbacMiddleware implements MiddlewareInterface
{
    /** The permission's `constraints` field: currently only passed along, not interpreted. */
    public const CONSTRAINTS_ATTRIBUTE = 'rbac.constraints';

    public function __construct(private readonly Connection $db)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $request->getAttribute(UserInterface::class);
        if (! $user instanceof UserInterface) {
            return $handler->handle($request);
        }

        $routeResult = $request->getAttribute(RouteResult::class);
        if (! $routeResult instanceof RouteResult || $routeResult->isFailure()) {
            return $handler->handle($request);
        }

        $permission = $routeResult->getMatchedRoute()->getOptions()['permission'] ?? null;
        $role       = $this->role($user);
        if (! is_string($permission) || $role === null) {
            return $this->forbidden();
        }

        $row = $this->db->fetchAssociative(
            'SELECT p.`constraints` AS constraints
               FROM role_permissions p JOIN roles r ON r.id = p.role_id
              WHERE r.name = ? AND p.permission = ?',
            [$role, $permission],
        );
        if ($row === false) {
            return $this->forbidden();
        }

        $constraints = $row['constraints'] === null ? null : json_decode((string) $row['constraints'], true, 512, JSON_THROW_ON_ERROR);

        return $handler->handle($request->withAttribute(self::CONSTRAINTS_ATTRIBUTE, $constraints));
    }

    private function role(UserInterface $user): ?string
    {
        $scopes = $user->getDetail('oauth_scopes');
        if (! is_array($scopes)) {
            return null;
        }

        foreach ($scopes as $scope) {
            if (is_string($scope) && str_starts_with($scope, ScopeRepository::ROLE_PREFIX)) {
                return substr($scope, strlen(ScopeRepository::ROLE_PREFIX));
            }
        }

        return null;
    }

    private function forbidden(): ResponseInterface
    {
        return new JsonResponse(['error' => 'forbidden'], 403);
    }
}
