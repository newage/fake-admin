<?php

declare(strict_types=1);

namespace App\Sensor\Handler;

use App\Http\InvalidParams;
use App\Sensor\SensorListQuery;
use App\Sensor\SensorRepository;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class ListSensorsHandler implements RequestHandlerInterface
{
    public function __construct(private readonly SensorRepository $sensors)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $query = SensorListQuery::fromParams($request->getQueryParams());
        } catch (InvalidParams $e) {
            return new JsonResponse(['error' => 'validation_failed', 'errors' => $e->errors], 422);
        }

        $result = $this->sensors->paginate($query);

        return new JsonResponse([
            'items'     => $result['items'],
            'total'     => $result['total'],
            'page'      => $query->page,
            'page_size' => $query->pageSize,
        ]);
    }
}
