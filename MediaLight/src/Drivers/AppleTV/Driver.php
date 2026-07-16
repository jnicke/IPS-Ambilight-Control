<?php

declare(strict_types=1);

namespace MediaLight\Drivers\AppleTV;

use MediaLight\Core\Logger;
use MediaLight\Models\AppleTV\Status;
use RuntimeException;
use Throwable;

final class Driver
{
    public function __construct(
        private readonly Client $client,
        private readonly Logger $logger
    ) {
    }

    public function readStatus(): Status
    {
        try {
            $payload = $this->client->getStatus();
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'Apple-TV-Bridge nicht erreichbar: '
                . $exception->getMessage(),
                0,
                $exception
            );
        }

        return $this->mapStatus($payload);
    }

    /**
     * Wandelt das JSON der Bridge in ein Statusmodell um.
     *
     * Wird sowohl für den zyklischen Abruf als auch für per
     * WebHook eingelieferte Ereignisse verwendet.
     *
     * @param array<string, mixed> $payload
     */
    public function mapStatus(array $payload): Status
    {
        $status = new Status(
            online: (bool) ($payload['online'] ?? false),
            power: $this->text($payload, 'power', 'unknown'),
            state: $this->text($payload, 'state', 'offline'),
            deviceState: $this->text($payload, 'device_state'),
            mediaType: $this->text($payload, 'media_type'),
            title: $this->text($payload, 'title'),
            artist: $this->text($payload, 'artist'),
            album: $this->text($payload, 'album'),
            app: $this->text($payload, 'app'),
            appId: $this->text($payload, 'app_id'),
            position: $this->number($payload, 'position'),
            totalTime: $this->number($payload, 'total_time'),
            updated: (int) ($payload['updated'] ?? 0),
            lastEvent: (int) ($payload['last_event'] ?? 0),
            error: $this->text($payload, 'error'),
            identifier: $this->text($payload, 'identifier')
        );

        $this->logger->debug(
            'Apple-TV-Status ausgewertet',
            [
                'online'     => $status->isOnline(),
                'power'      => $status->getPower(),
                'state'      => $status->getState(),
                'app'        => $status->getApp(),
                'appId'      => $status->getAppId(),
                'title'      => $status->getTitle(),
                'lastEvent'  => $status->getLastEvent(),
                'error'      => $status->getError()
            ]
        );

        return $status;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function text(
        array $payload,
        string $key,
        string $default = ''
    ): string {
        $value = $payload[$key] ?? null;

        if ($value === null) {
            return $default;
        }

        return (string) $value;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function number(
        array $payload,
        string $key
    ): float {
        $value = $payload[$key] ?? null;

        if (!is_int($value) && !is_float($value)) {
            return 0.0;
        }

        return (float) $value;
    }
}