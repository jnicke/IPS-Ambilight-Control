<?php

declare(strict_types=1);

namespace MediaLight\Drivers\HyperHDR;

use InvalidArgumentException;
use JsonException;
use MediaLight\Core\Logger;
use MediaLight\Models\HyperHDR\Status;

final class Driver
{
    private const CONTROLLABLE_COMPONENTS = [
        'ALL',
        'LEDDEVICE',
        'VIDEOGRABBER',
        'SYSTEMGRABBER',
        'SMOOTHING',
        'HDR',
        'BLACKBORDER',
        'FORWARDER'
    ];

    public function __construct(
        private readonly Client $client,
        private readonly Logger $logger
    ) {
    }

    /**
     * Schaltet eine HyperHDR-Komponente ein oder aus.
     */
    public function setComponentState(
        string $component,
        bool $enabled
    ): void {
        $normalized = strtoupper(trim($component));

        if (
            !in_array(
                $normalized,
                self::CONTROLLABLE_COMPONENTS,
                true
            )
        ) {
            throw new InvalidArgumentException(
                sprintf(
                    'Unbekannte HyperHDR-Komponente "%s". Erlaubt sind: %s',
                    $component,
                    implode(', ', self::CONTROLLABLE_COMPONENTS)
                )
            );
        }

        $this->client->setComponentState(
            $normalized,
            $enabled
        );

        $this->logger->info(
            'HyperHDR-Komponente geschaltet',
            [
                'component' => $normalized,
                'enabled'   => $enabled
            ]
        );
    }

    public function readStatus(): Status
    {
        $response = $this->client->getServerInfo();

        $info = isset($response['info']) && is_array($response['info'])
            ? $response['info']
            : [];

        $components = $this->extractComponents($info);

        $currentInstance = isset($info['currentInstance'])
            ? (int) $info['currentInstance']
            : -1;

        $instance = $this->findInstance(
            $info,
            $currentInstance
        );

        $grabbers = isset($info['grabbers']) && is_array($info['grabbers'])
            ? $info['grabbers']
            : [];

        $currentGrabber = isset($grabbers['current'])
            && is_array($grabbers['current'])
                ? $grabbers['current']
                : [];

        $grabberDevice = isset($currentGrabber['device'])
            ? (string) $currentGrabber['device']
            : '';

        $videoMode = isset($currentGrabber['videoMode'])
            ? (string) $currentGrabber['videoMode']
            : '';

        $priority = $this->findVisiblePriority($info);

        $sessions = isset($info['sessions']) && is_array($info['sessions'])
            ? $info['sessions']
            : [];

        $effects = isset($info['effects']) && is_array($info['effects'])
            ? $info['effects']
            : [];

        $leds = isset($info['leds']) && is_array($info['leds'])
            ? $info['leds']
            : [];

        $status = new Status(
            online: true,
            version: $this->extractVersion($info),
            hostname: isset($info['hostname'])
                ? (string) $info['hostname']
                : '',
            currentInstance: $currentInstance,
            instanceName: $instance['name'],
            instanceEnabled: $instance['running'],
            grabberEnabled: $this->componentEnabled(
                $components,
                'VIDEOGRABBER'
            ),
            grabberDevice: $grabberDevice,
            videoMode: $videoMode,
            ledDeviceEnabled: $this->componentEnabled(
                $components,
                'LEDDEVICE'
            ),
            smoothingEnabled: $this->componentEnabled(
                $components,
                'SMOOTHING'
            ),
            hdrEnabled: $this->componentEnabled(
                $components,
                'HDR'
            ),
            blackBorderEnabled: $this->componentEnabled(
                $components,
                'BLACKBORDER'
            ),
            forwarderEnabled: $this->componentEnabled(
                $components,
                'FORWARDER'
            ),
            fps: $this->extractFpsFromVideoMode($videoMode),
            visiblePriority: $priority['priority'],
            priorityComponent: $priority['component'],
            priorityOwner: $priority['owner'],
            effectCount: count($effects),
            ledCount: count($leds),
            wledConnected: $this->containsWledSession($sessions),
            sessionCount: count($sessions),
            lastError: isset($info['lastError'])
                ? (string) $info['lastError']
                : '',
            rawResponse: $this->encodeResponse($response)
        );

        $this->logger->debug(
            'HyperHDR-Status ausgewertet',
            [
                'hostname'          => $status->getHostname(),
                'currentInstance'   => $status->getCurrentInstance(),
                'instanceName'      => $status->getInstanceName(),
                'instanceEnabled'   => $status->isInstanceEnabled(),
                'grabberEnabled'    => $status->isGrabberEnabled(),
                'grabberDevice'     => $status->getGrabberDevice(),
                'videoMode'         => $status->getVideoMode(),
                'fps'               => $status->getFps(),
                'visiblePriority'   => $status->getVisiblePriority(),
                'priorityComponent' => $status->getPriorityComponent(),
                'priorityOwner'     => $status->getPriorityOwner(),
                'effectCount'       => $status->getEffectCount(),
                'ledCount'          => $status->getLedCount(),
                'wledConnected'     => $status->isWledConnected(),
                'sessionCount'      => $status->getSessionCount()
            ]
        );

        return $status;
    }

    /**
     * @param array<string, mixed> $info
     *
     * @return array<string, bool>
     */
    private function extractComponents(array $info): array
    {
        $components = [];

        if (
            !isset($info['components'])
            || !is_array($info['components'])
        ) {
            return $components;
        }

        foreach ($info['components'] as $component) {
            if (!is_array($component)) {
                continue;
            }

            $name = strtoupper(
                trim((string) ($component['name'] ?? ''))
            );

            if ($name === '') {
                continue;
            }

            $components[$name] = (bool) (
                $component['enabled'] ?? false
            );
        }

        return $components;
    }

    /**
     * @param array<string, bool> $components
     */
    private function componentEnabled(
        array $components,
        string $name
    ): bool {
        $normalized = strtoupper($name);

        return $components[$normalized] ?? false;
    }

    /**
     * @param array<string, mixed> $info
     *
     * @return array{name: string, running: bool}
     */
    private function findInstance(
        array $info,
        int $currentInstance
    ): array {
        if (
            !isset($info['instance'])
            || !is_array($info['instance'])
        ) {
            return [
                'name' => '',
                'running' => false
            ];
        }

        foreach ($info['instance'] as $instance) {
            if (!is_array($instance)) {
                continue;
            }

            if (
                isset($instance['instance'])
                && (int) $instance['instance'] === $currentInstance
            ) {
                return [
                    'name' => (string) (
                        $instance['friendly_name'] ?? ''
                    ),
                    'running' => (bool) (
                        $instance['running'] ?? false
                    )
                ];
            }
        }

        return [
            'name' => '',
            'running' => false
        ];
    }

    /**
     * @param array<string, mixed> $info
     *
     * @return array{
     *     priority: int,
     *     component: string,
     *     owner: string
     * }
     */
    private function findVisiblePriority(array $info): array
    {
        $result = [
            'priority' => -1,
            'component' => '',
            'owner' => ''
        ];

        if (
            !isset($info['priorities'])
            || !is_array($info['priorities'])
        ) {
            return $result;
        }

        foreach ($info['priorities'] as $priority) {
            if (!is_array($priority)) {
                continue;
            }

            if (!(bool) ($priority['visible'] ?? false)) {
                continue;
            }

            return [
                'priority' => (int) (
                    $priority['priority'] ?? -1
                ),
                'component' => (string) (
                    $priority['componentId'] ?? ''
                ),
                'owner' => (string) (
                    $priority['owner'] ?? ''
                )
            ];
        }

        return $result;
    }

    private function extractFpsFromVideoMode(
        string $videoMode
    ): float {
        if ($videoMode === '') {
            return 0.0;
        }

        if (
            preg_match(
                '/^\s*\d+x\d+x([0-9]+(?:\.[0-9]+)?)/i',
                $videoMode,
                $matches
            ) === 1
        ) {
            return (float) $matches[1];
        }

        return 0.0;
    }

    /**
     * `serverinfo` liefert bei HyperHDR 22 beta2 keine Version.
     *
     * @param array<string, mixed> $info
     */
    private function extractVersion(array $info): string
    {
        $candidates = [
            $info['version'] ?? null,
            $info['hyperhdrVersion'] ?? null
        ];

        foreach ($candidates as $candidate) {
            if (
                is_string($candidate)
                && trim($candidate) !== ''
            ) {
                return trim($candidate);
            }
        }

        return '';
    }

    /**
     * @param array<int, mixed> $sessions
     */
    private function containsWledSession(array $sessions): bool
    {
        foreach ($sessions as $session) {
            if (!is_array($session)) {
                continue;
            }

            $name = strtolower(
                (string) ($session['name'] ?? '')
            );

            $host = strtolower(
                (string) ($session['host'] ?? '')
            );

            if (
                str_contains($name, 'wled')
                || str_contains($host, 'wled')
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $response
     */
    private function encodeResponse(array $response): string
    {
        try {
            return json_encode(
                $response,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_THROW_ON_ERROR
            );
        } catch (JsonException) {
            return '';
        }
    }
}