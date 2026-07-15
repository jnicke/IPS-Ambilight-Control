<?php

declare(strict_types=1);

namespace MediaLight\Drivers\WLED;

use MediaLight\Core\Logger;
use MediaLight\Models\WLED\Controller;
use RuntimeException;
use Throwable;

final class Driver
{
    public function __construct(
        private readonly Client $client,
        private readonly Mapper $mapper,
        private readonly Logger $logger
    ) {
    }

    public function readController(): Controller
    {
        try {
            $info = $this->client->getInfo();
            $state = $this->client->getState();
            $config = $this->client->getConfig();

            $controller = $this->mapper->mapController(
                info: $info,
                state: $state,
                config: $config
            );

            $this->logger->debug(
                'WLED-Controller ausgewertet',
                [
                    'name'          => $controller->getName(),
                    'firmware'      => $controller->getFirmware(),
                    'ipAddress'     => $controller->getIpAddress(),
                    'ledCount'      => $controller->getLedCount(),
                    'busCount'      => $controller->getBusCount(),
                    'segmentCount'  => count(
                        $controller->getState()->getSegments()
                    ),
                    'realtime'      => $controller
                        ->getState()
                        ->isRealtime(),
                    'liveMode'      => $controller->getLiveMode(),
                    'liveSourceIp'  => $controller->getLiveSourceIp()
                ]
            );

            return $controller;
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'WLED-Status konnte nicht gelesen werden: '
                . $exception->getMessage(),
                0,
                $exception
            );
        }
    }
}