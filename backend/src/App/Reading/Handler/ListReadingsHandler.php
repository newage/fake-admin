<?php

declare(strict_types=1);

namespace App\Reading\Handler;

use App\Http\InvalidParams;
use App\Reading\ReadingsQuery;
use App\Reading\ReadingsRepository;
use App\Reading\ReadingsUnavailable;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class ListReadingsHandler implements RequestHandlerInterface
{
    public function __construct(private readonly ReadingsRepository $readings)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $sensorId = (int) $request->getAttribute('id');

        if (! $this->readings->sensorExists($sensorId)) {
            return new JsonResponse(['error' => 'sensor_not_found'], 404);
        }

        try {
            $query = ReadingsQuery::fromParams($request->getQueryParams());
        } catch (InvalidParams $e) {
            return new JsonResponse(['error' => 'validation_failed', 'errors' => $e->errors], 422);
        }

        try {
            $page = $this->readings->page($sensorId, $query);
        } catch (ReadingsUnavailable) {
            return new JsonResponse(['error' => 'readings_unavailable'], 503);
        }

        return new JsonResponse([
            'items'     => $page['items'],
            'page'      => $query->page,
            'page_size' => $query->pageSize,
            'has_more'  => $page['has_more'],
        ]);
    }
}
