<?php

declare(strict_types=1);

namespace MediaLight\Models\WLED;

final class State
{
    /**
     * @param Segment[] $segments
     */
    public function __construct(
        private readonly bool $on,
        private readonly int $brightness,
        private readonly int $transition,
        private readonly int $preset,
        private readonly int $playlist,
        private readonly bool $realtime,
        private readonly int $realtimeOverride,
        private readonly int $mainSegment,
        private readonly bool $udpSend,
        private readonly bool $udpReceive,
        private readonly array $segments
    ) {
    }

    public function isOn(): bool
    {
        return $this->on;
    }

    public function getBrightness(): int
    {
        return $this->brightness;
    }

    public function getTransition(): int
    {
        return $this->transition;
    }

    public function getPreset(): int
    {
        return $this->preset;
    }

    public function getPlaylist(): int
    {
        return $this->playlist;
    }

    public function isRealtime(): bool
    {
        return $this->realtime;
    }

    public function getRealtimeOverride(): int
    {
        return $this->realtimeOverride;
    }

    public function getMainSegment(): int
    {
        return $this->mainSegment;
    }

    public function isUdpSendEnabled(): bool
    {
        return $this->udpSend;
    }

    public function isUdpReceiveEnabled(): bool
    {
        return $this->udpReceive;
    }

    /**
     * @return Segment[]
     */
    public function getSegments(): array
    {
        return $this->segments;
    }

    public function getSegment(int $id): ?Segment
    {
        foreach ($this->segments as $segment) {
            if ($segment->getId() === $id) {
                return $segment;
            }
        }

        return null;
    }
}