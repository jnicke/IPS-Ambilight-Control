<?php

declare(strict_types=1);

namespace MediaLight\Drivers\HyperHDR;

use JsonException;
use MediaLight\Core\Logger;
use MediaLight\Models\HyperHDR\Status;

final class Driver
{
    public function __construct(
        private readonly Client $client,
        private readonly Logger $logger
    ) {
    }

    public function readStatus(): Status
    {
        $response = $this->client->getServerInfo();

        $info = isset($response['info']) && is_array($response['info'])
            ? $response['info']
            : [];

        $components = $this->extractComponents($info);

        $version = $this->findFirstString(
            $info,
            [
                ['hyperhdr', 'version'],
                ['version'],
                ['system', 'hyperhdrVersion'],
                ['system', 'version']
            ]
        );

        $fps = $this->extractFps($info);

        $visiblePriority = $this->extractVisiblePriority($info);

        $effects = isset($info['effects']) && is_array($info['effects'])
            ? count($info['effects'])
            : 0;

        $instanceEnabled = $this->componentEnabled(
            $components,
            ['ALL', 'HYPERHDR']
        );

        if (!$instanceEnabled) {
            $instanceEnabled = $this->findFirstBool(
                $info,
                [
                    ['instance', 'running'],
                    ['instance', 'enabled'],
                    ['running']
                ],
                true
            );
        }

        $status = new Status(
            online: true,
            version: $version,
            instanceEnabled: $instanceEnabled,
            grabberEnabled: $this->componentEnabled(
                $components,
                [
                    'VIDEOGRABBER',
                    'V4L',
                    'V4L2',
                    'VIDEO'
                ]
            ),
            ledDeviceEnabled: $this->componentEnabled(
                $components,
                ['LEDDEVICE']
            ),
            smoothingEnabled: $this->componentEnabled(
                $components,
                ['SMOOTHING']
            ),
            fps: $fps,
            visiblePriority: $visiblePriority,
            effectCount: $effects,
            rawResponse: $this->encodeResponse($response)
        );

        $this->logger->debug(
            'HyperHDR-Status ausgewertet',
            [
                'version'          => $status->getVersion(),
                'instanceEnabled'  => $status->isInstanceEnabled(),
                'grabberEnabled'   => $status->isGrabberEnabled(),
                'ledDeviceEnabled' => $status->isLedDeviceEnabled(),
                'fps'              => $status->getFps(),
                'visiblePriority'  => $status->getVisiblePriority(),
                'effectCount'      => $status->getEffectCount()
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
        $result = [];

        if (
            !isset($info['components'])
            || !is_array($info['components'])
        ) {
            return $result;
        }

        foreach ($info['components'] as $component) {
            if (!is_array($component)) {
                continue;
            }

            $name = strtoupper(
                trim(
                    (string) (
                        $component['name']
                        ?? $component['component']
                        ?? ''
                    )
                )
            );

            if ($name === '') {
                continue;
            }

            $result[$name] = (bool) (
                $component['enabled']
                ?? $component['state']
                ?? false
            );
        }

        return $result;
    }

    /**
     * @param array<string, bool> $components
     * @param string[]            $names
     */
    private function componentEnabled(
        array $components,
        array $names
    ): bool {
        foreach ($names as $name) {
            $normalized = strtoupper($name);

            if (
                array_key_exists($normalized, $components)
                && $components[$normalized]
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $info
     */
    private function extractFps(array $info): float
    {
        $paths = [
            ['fps'],
            ['videoFps'],
            ['currentFps'],
            ['grabber', 'fps'],
            ['grabber', 'currentFps'],
            ['performance', 'fps'],
            ['performance', 'videoFps'],
            ['videograbber', 'fps']
        ];

        foreach ($paths as $path) {
            $value = $this->getPathValue($info, $path);

            if (is_int($value) || is_float($value)) {
                return round((float) $value, 2);
            }

            if (is_string($value) && is_numeric($value)) {
                return round((float) $value, 2);
            }
        }

        $recursive = $this->findNumericByKey(
            $info,
            ['fps', 'currentfps', 'videofps']
        );

        return $recursive !== null
            ? round($recursive, 2)
            : 0.0;
    }

    /**
     * @param array<string, mixed> $info
     */
    private function extractVisiblePriority(array $info): int
    {
        $direct = $this->getPathValue(
            $info,
            ['priorities', 'currentPriority']
        );

        if (is_numeric($direct)) {
            return (int) $direct;
        }

        if (!isset($info['priorities'])) {
            return -1;
        }

        $priorities = $info['priorities'];

        if (is_array($priorities)) {
            foreach ($priorities as $priority) {
                if (!is_array($priority)) {
                    continue;
                }

                if (
                    (bool) (
                        $priority['visible']
                        ?? $priority['active']
                        ?? false
                    )
                ) {
                    return (int) (
                        $priority['priority']
                        ?? $priority['value']
                        ?? -1
                    );
                }
            }
        }

        return -1;
    }

    /**
     * @param array<string, mixed> $source
     * @param array<int, array<int, string>> $paths
     */
    private function findFirstString(
        array $source,
        array $paths
    ): string {
        foreach ($paths as $path) {
            $value = $this->getPathValue($source, $path);

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $source
     * @param array<int, array<int, string>> $paths
     */
    private function findFirstBool(
        array $source,
        array $paths,
        bool $default
    ): bool {
        foreach ($paths as $path) {
            $value = $this->getPathValue($source, $path);

            if (is_bool($value)) {
                return $value;
            }

            if (is_int($value)) {
                return $value !== 0;
            }
        }

        return $default;
    }

    /**
     * @param array<string, mixed> $source
     * @param string[]             $path
     */
    private function getPathValue(
        array $source,
        array $path
    ): mixed {
        $value = $source;

        foreach ($path as $part) {
            if (
                !is_array($value)
                || !array_key_exists($part, $value)
            ) {
                return null;
            }

            $value = $value[$part];
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $source
     * @param string[]             $keys
     */
    private function findNumericByKey(
        array $source,
        array $keys
    ): ?float {
        foreach ($source as $key => $value) {
            if (
                in_array(strtolower((string) $key), $keys, true)
                && is_numeric($value)
            ) {
                return (float) $value;
            }

            if (is_array($value)) {
                $found = $this->findNumericByKey($value, $keys);

                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
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