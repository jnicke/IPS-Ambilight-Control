<?php

declare(strict_types=1);

namespace MediaLight\Drivers\WLED;

use MediaLight\Core\Logger;
use MediaLight\Models\WLED\Bus;
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
                    'name'         => $controller->getName(),
                    'firmware'     => $controller->getFirmware(),
                    'ipAddress'    => $controller->getIpAddress(),
                    'ledCount'     => $controller->getLedCount(),
                    'busCount'     => $controller->getBusCount(),
                    'segmentCount' => count(
                        $controller->getState()->getSegments()
                    ),
                    'realtime'     => $controller
                        ->getState()
                        ->isRealtime(),
                    'liveMode'     => $controller->getLiveMode(),
                    'liveSourceIp' => $controller->getLiveSourceIp()
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

    /**
     * Liefert die Effektliste des Controllers.
     *
     * @return list<string>
     */
    public function readEffects(): array
    {
        return $this->client->getEffects();
    }

    public function beginTransaction(): Transaction
    {
        $controller = $this->readController();

        return new Transaction(
            client: $this->client,
            logger: $this->logger,
            realtimeActive: $controller
                ->getState()
                ->isRealtime(),
            protectedBusNumber: 1
        );
    }

    /**
     * Legt für jeden physischen LED-Bus ein passendes Segment an.
     *
     * Bus 1 wird Segment 0, Bus 2 Segment 1 usw.
     *
     * @return array<string, mixed>
     */
    public function synchronizeSegments(): array
    {
        $controller = $this->readController();

        if ($controller->getBuses() === []) {
            throw new RuntimeException(
                'WLED meldet keine konfigurierten LED-Busse.'
            );
        }

        $segments = [];

        foreach ($controller->getBuses() as $bus) {
            $segments[] = $this->createSegmentDefinition($bus);
        }

        $payload = [
            'on'      => true,
            'mainseg' => 0,
            'seg'     => $segments,
            'v'       => true
        ];

        $this->logger->info(
            'WLED-Segmente werden mit LED-Bussen synchronisiert',
            [
                'segmentCount' => count($segments),
                'segments'     => $segments
            ]
        );

        return $this->client->postState($payload);
    }

    /**
     * Direkter Komfortaufruf für einen einzelnen Bus.
     *
     * @return array<string, mixed>
     */
    public function setBusPower(
        int $busNumber,
        bool $power,
        int $transition = 7
    ): array {
        $transaction = $this->beginTransaction();

        $transaction
            ->bus($busNumber)
            ->power($power)
            ->freeze(false);

        return $transaction->commit(
            transition: $transition,
            forceControllerOn: $power
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function setBusBrightness(
        int $busNumber,
        int $brightness,
        int $transition = 7
    ): array {
        $transaction = $this->beginTransaction();

        $transaction
            ->bus($busNumber)
            ->power(true)
            ->brightness($brightness)
            ->freeze(false);

        return $transaction->commit(
            transition: $transition
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function setBusRgbw(
        int $busNumber,
        int $red,
        int $green,
        int $blue,
        int $white = 0,
        int $brightness = 255,
        int $transition = 7
    ): array {
        $transaction = $this->beginTransaction();

        $transaction
            ->bus($busNumber)
            ->power(true)
            ->brightness($brightness)
            ->rgbw(
                $red,
                $green,
                $blue,
                $white
            )
            ->effect(0)
            ->freeze(false);

        return $transaction->commit(
            transition: $transition
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function setBusEffect(
        int $busNumber,
        int $effect,
        int $speed = 128,
        int $intensity = 128,
        int $palette = 0,
        int $brightness = 255,
        int $transition = 7
    ): array {
        $transaction = $this->beginTransaction();

        $transaction
            ->bus($busNumber)
            ->power(true)
            ->brightness($brightness)
            ->effect($effect)
            ->speed($speed)
            ->intensity($intensity)
            ->palette($palette)
            ->freeze(false);

        return $transaction->commit(
            transition: $transition
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function createSegmentDefinition(Bus $bus): array
    {
        return [
            'id'    => $bus->getIndex(),
            'start' => $bus->getStart(),
            'stop'  => $bus->getStop(),
            'grp'   => 1,
            'spc'   => 0,
            'of'    => 0,
            'on'    => true,
            'frz'   => false,
            'bri'   => 255,
            'col'   => [
                [255, 160, 0, 0],
                [0, 0, 0, 0],
                [0, 0, 0, 0]
            ],
            'fx'    => 0,
            'sx'    => 128,
            'ix'    => 128,
            'pal'   => 0,
            'sel'   => $bus->getIndex() === 0,
            'rev'   => $bus->isReversed()
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function setMasterPower(
        bool $power,
        int $transition = 7
    ): array {
        return $this->client->postState([
            'on'         => $power,
            'transition' => $transition
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function setMasterBrightness(
        int $brightness,
        int $transition = 7
    ): array {
        return $this->client->postState([
            'on'         => true,
            'bri'        => max(1, min(255, $brightness)),
            'transition' => $transition
        ]);
    }
}