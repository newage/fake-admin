<?php

declare(strict_types=1);

namespace AppTest\Rbac;

use App\Rbac\RbacMiddleware;
use AppTest\Db\DatabaseTestCase;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\ServerRequest;
use Mezzio\Authentication\DefaultUser;
use Mezzio\Authentication\UserInterface;
use Mezzio\Router\Route;
use Mezzio\Router\RouteResult;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RbacMiddlewareTest extends DatabaseTestCase
{
    private MiddlewareInterface $middleware;
    private RequestHandlerInterface $next;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateUp();
        $this->middleware = new RbacMiddleware($this->db);
        $this->next       = new class implements RequestHandlerInterface {
            public ?ServerRequestInterface $received = null;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->received = $request;

                return new EmptyResponse(204);
            }
        };
    }

    public function testAllowsRoleWithPermission(): void
    {
        $response = $this->process($this->request('role:manager', 'sensors.list'));

        self::assertSame(204, $response->getStatusCode());
    }

    public function testForbidsRoleWithoutPermission(): void
    {
        $response = $this->process($this->request('role:user', 'sensors.list'));

        self::assertSame(403, $response->getStatusCode());
        self::assertNull($this->next->received);
    }

    public function testPermissionChangeInDatabaseTakesEffectOnNextRequest(): void
    {
        $request = $this->request('role:user', 'sensors.list');
        self::assertSame(403, $this->process($request)->getStatusCode());

        $this->db->executeStatement(
            "INSERT INTO role_permissions (role_id, permission) SELECT id, 'sensors.list' FROM roles WHERE name = 'user'",
        );
        self::assertSame(204, $this->process($request)->getStatusCode());

        $this->db->executeStatement("DELETE FROM role_permissions WHERE permission = 'sensors.list'");
        self::assertSame(403, $this->process($request)->getStatusCode());
    }

    public function testRouteWithoutDeclaredPermissionIsDenied(): void
    {
        $response = $this->process($this->request('role:admin', null));

        self::assertSame(403, $response->getStatusCode());
    }

    public function testTokenWithoutRoleIsDenied(): void
    {
        $response = $this->process($this->request(null, 'sensors.list'));

        self::assertSame(403, $response->getStatusCode());
    }

    public function testUnknownRoleIsDenied(): void
    {
        $response = $this->process($this->request('role:ghost', 'sensors.list'));

        self::assertSame(403, $response->getStatusCode());
    }

    public function testPublicRequestWithoutUserPassesThrough(): void
    {
        $request = $this->request(null, null)->withoutAttribute(UserInterface::class);

        self::assertSame(204, $this->process($request)->getStatusCode());
    }

    public function testNonMatchingRoutePassesThrough(): void
    {
        $request = $this->request('role:user', null)->withAttribute(RouteResult::class, RouteResult::fromRouteFailure(null));

        self::assertSame(204, $this->process($request)->getStatusCode());
    }

    public function testConstraintsAreExposedButNotInterpreted(): void
    {
        $this->db->executeStatement(
            "UPDATE role_permissions SET `constraints` = '{\"max_page_size\": 10}' WHERE permission = 'sensors.list'",
        );

        $response = $this->process($this->request('role:manager', 'sensors.list'));

        self::assertSame(204, $response->getStatusCode());
        self::assertSame(['max_page_size' => 10], $this->next->received?->getAttribute(RbacMiddleware::CONSTRAINTS_ATTRIBUTE));
    }

    private function process(ServerRequestInterface $request): ResponseInterface
    {
        return $this->middleware->process($request, $this->next);
    }

    private function request(?string $scope, ?string $permission): ServerRequestInterface
    {
        $route = new Route('/api/sensors', $this->middleware, ['GET'], 'sensors');
        $route->setOptions($permission === null ? [] : ['permission' => $permission]);

        return (new ServerRequest([], [], '/api/sensors', 'GET'))
            ->withAttribute(RouteResult::class, RouteResult::fromRoute($route))
            ->withAttribute(UserInterface::class, new DefaultUser('1', [], ['oauth_scopes' => $scope === null ? [] : [$scope]]));
    }
}
