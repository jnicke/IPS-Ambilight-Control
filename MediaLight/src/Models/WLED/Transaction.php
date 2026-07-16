<?php

declare(strict_types=1);

namespace MediaLight\Drivers\WLED;

use LogicException;
use MediaLight\Core\Logger;
use MediaLight\Models\WLED\BusUpdate;
use RuntimeException;

final class Transaction
{
    /**
     * @var array<int, BusUpdate>
     */
    private array $updates = [];

    private bool $committed = false;

    public function __construct(
        private readonly Client $client,
        private readonly Logger $logger,
        private readonly bool $realtimeActive,
        private readonly int $protectedBusNumber = 1
    ) {
    }

    public function bus(int $busNumber): BusUpdate
    {
        $this->assertOpen();

        if (
            $this->realtimeActive
            && $busNumber === $this->protectedBusNumber
        ) {
            throw new LogicException(
                sprintf(
                    'Bus %d ist während des WLED-Realtime-Betriebs '
                    . 'für HyperHDR reserviert.',
                    $busNumber
                )
            );
        }

        if ($busNumber < 1) {
            throw new LogicException(
                'Die Busnummer muss mindestens 1 sein.'
            );
        }

        if (!isset($this->updates[$busNumber])) {
            $this->updates[$busNumber] = new BusUpdate(
                busNumber: $busNumber,
                segmentId: $busNumber - 1
            );
        }

        return $this->updates[$busNumber];
    }

    /**
     * Sendet alle vorgemerkten Änderungen in einem HTTP-Aufruf.
     *
     * @return array<string, mixed>
     */
    public function commit(
        ?int $transition = null,
        bool $forceControllerOn = true
    ): array {
        $this->assertOpen();

        if ($this->updates === []) {
            throw new RuntimeException(
                'Die WLED-Transaktion enthält keine Änderungen.'
            );
        }

        ksort($this->updates);

        $segments = [];

        foreach ($this->updates as $update) {
            $segments[] = $update->toPayload();
        }

        $payload = [
            'seg' => $segments,
            'v'   => true
        ];

        if ($forceControllerOn) {
            $payload['on'] = true;
        }

        if ($transition !== null) {
            $payload['transition'] = max(
                0,
                min(65535, $transition)
            );
        }

        $this->logger->debug(
            'WLED-Transaktion wird übertragen',
            [
                'realtimeActive' => $this->realtimeActive,
                'segments'       => $segments,
                'transition'     => $transition
            ]
        );

        $response = $this->client->postState($payload);

        $this->committed = true;

        return $response;
    }

    public function isCommitted(): bool
    {
        return $this->committed;
    }

    public function hasChanges(): bool
    {
        return $this->updates !== [];
    }

    private function assertOpen(): void
    {
        if ($this->committed) {
            throw new LogicException(
                'Die WLED-Transaktion wurde bereits abgeschlossen.'
            );
        }
    }
}