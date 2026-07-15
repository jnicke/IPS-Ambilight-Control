<?php

declare(strict_types=1);

namespace MediaLight\Drivers\WLED;

use MediaLight\Models\WLED\Bus;
use MediaLight\Models\WLED\Controller;
use MediaLight\Models\WLED\Segment;
use MediaLight\Models\WLED\State;
use RuntimeException;

final class Mapper
{
    /**
     * @param array<string, mixed> $info
     * @param array<string, mixed> $state
     * @param array<string, mixed> $config
     */
    public function mapController(
        array $info,
        array $state,
        array $config
    ): Controller {
        $ledInfo = $this->arrayValue($info, 'leds');
        $wifi = $this->arrayValue($info, 'wifi');

        return new Controller(
            online: true,
            name: $this->stringValue($info, 'name'),
            firmware: $this->stringValue($info, 'ver'),
            release: $this->stringValue($info, 'release'),
            architecture: $this->stringValue($info, 'arch'),
            ipAddress: $this->stringValue($info, 'ip'),
            macAddress: $this->stringValue($info, 'mac'),
            ledCount: $this->intValue($ledInfo, 'count'),
            rgbw: $this->boolValue($ledInfo, 'rgbw'),
            maximumCurrent: $this->intValue($ledInfo, 'maxpwr'),
            currentPower: $this->intValue($ledInfo, 'pwr'),
            framesPerSecond: $this->intValue($ledInfo, 'fps'),
            effectCount: $this->intValue($info, 'fxcount'),
            paletteCount: $this->intValue($info, 'palcount'),
            uptime: $this->intValue($info, 'uptime'),
            freeHeap: $this->intValue($info, 'freeheap'),
            rssi: $this->intValue($wifi, 'rssi'),
            signalQuality: $this->intValue($wifi, 'signal'),
            liveMode: $this->stringValue($info, 'lm'),
            liveSourceIp: $this->stringValue($info, 'lip'),
            buses: $this->mapBuses($config),
            state: $this->mapState($state)
        );
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return Bus[]
     */
    public function mapBuses(array $config): array
    {
        $hardware = $this->arrayValue($config, 'hw');
        $led = $this->arrayValue($hardware, 'led');

        $rawBuses = $led['ins'] ?? [];

        if (!is_array($rawBuses)) {
            throw new RuntimeException(
                'WLED-Konfiguration enthält keine gültige Busliste.'
            );
        }

        $buses = [];

        foreach (array_values($rawBuses) as $index => $rawBus) {
            if (!is_array($rawBus)) {
                continue;
            }

            $pins = [];

            if (isset($rawBus['pin']) && is_array($rawBus['pin'])) {
                foreach ($rawBus['pin'] as $pin) {
                    if (is_numeric($pin)) {
                        $pins[] = (int) $pin;
                    }
                }
            }

            $buses[] = new Bus(
                index: $index,
                start: $this->intValue($rawBus, 'start'),
                length: $this->intValue($rawBus, 'len'),
                pins: $pins,
                type: $this->intValue($rawBus, 'type'),
                colorOrder: $this->intValue($rawBus, 'order'),
                reversed: $this->boolValue($rawBus, 'rev'),
                skip: $this->intValue($rawBus, 'skip'),
                milliAmpsPerLed: $this->intValue($rawBus, 'ledma'),
                maximumCurrent: $this->intValue($rawBus, 'maxpwr'),
                refreshRequired: $this->boolValue($rawBus, 'ref')
            );
        }

        return $buses;
    }

    /**
     * @param array<string, mixed> $state
     */
    public function mapState(array $state): State
    {
        $udp = $this->arrayValue($state, 'udpn');
        $segments = [];

        $rawSegments = $state['seg'] ?? [];

        if (is_array($rawSegments)) {
            foreach ($rawSegments as $rawSegment) {
                if (!is_array($rawSegment)) {
                    continue;
                }

                $segments[] = $this->mapSegment($rawSegment);
            }
        }

        return new State(
            on: $this->boolValue($state, 'on'),
            brightness: $this->intValue($state, 'bri'),
            transition: $this->intValue($state, 'transition'),
            preset: $this->intValue($state, 'ps', -1),
            playlist: $this->intValue($state, 'pl', -1),
            realtime: $this->boolValue($state, 'live'),
            realtimeOverride: $this->intValue($state, 'lor'),
            mainSegment: $this->intValue($state, 'mainseg'),
            udpSend: $this->boolValue($udp, 'send'),
            udpReceive: $this->boolValue($udp, 'recv'),
            segments: $segments
        );
    }

    /**
     * @param array<string, mixed> $rawSegment
     */
    private function mapSegment(array $rawSegment): Segment
    {
        $colors = [];

        if (
            isset($rawSegment['col'])
            && is_array($rawSegment['col'])
        ) {
            foreach ($rawSegment['col'] as $rawColor) {
                if (!is_array($rawColor)) {
                    continue;
                }

                $color = [];

                foreach ($rawColor as $channel) {
                    if (is_numeric($channel)) {
                        $color[] = max(
                            0,
                            min(255, (int) $channel)
                        );
                    }
                }

                $colors[] = $color;
            }
        }

        return new Segment(
            id: $this->intValue($rawSegment, 'id'),
            start: $this->intValue($rawSegment, 'start'),
            stop: $this->intValue($rawSegment, 'stop'),
            length: $this->intValue($rawSegment, 'len'),
            on: $this->boolValue($rawSegment, 'on'),
            frozen: $this->boolValue($rawSegment, 'frz'),
            brightness: $this->intValue($rawSegment, 'bri'),
            colors: $colors,
            effect: $this->intValue($rawSegment, 'fx'),
            speed: $this->intValue($rawSegment, 'sx'),
            intensity: $this->intValue($rawSegment, 'ix'),
            palette: $this->intValue($rawSegment, 'pal'),
            selected: $this->boolValue($rawSegment, 'sel'),
            reversed: $this->boolValue($rawSegment, 'rev')
        );
    }

    /**
     * @param array<string, mixed> $source
     *
     * @return array<string, mixed>
     */
    private function arrayValue(
        array $source,
        string $key
    ): array {
        $value = $source[$key] ?? [];

        return is_array($value)
            ? $value
            : [];
    }

    /**
     * @param array<string, mixed> $source
     */
    private function stringValue(
        array $source,
        string $key,
        string $default = ''
    ): string {
        $value = $source[$key] ?? $default;

        return is_scalar($value)
            ? trim((string) $value)
            : $default;
    }

    /**
     * @param array<string, mixed> $source
     */
    private function intValue(
        array $source,
        string $key,
        int $default = 0
    ): int {
        $value = $source[$key] ?? $default;

        return is_numeric($value)
            ? (int) $value
            : $default;
    }

    /**
     * @param array<string, mixed> $source
     */
    private function boolValue(
        array $source,
        string $key,
        bool $default = false
    ): bool {
        $value = $source[$key] ?? $default;

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value !== 0;
        }

        if (is_string($value)) {
            return in_array(
                strtolower(trim($value)),
                ['true', 'on', 'yes', '1'],
                true
            );
        }

        return $default;
    }
}