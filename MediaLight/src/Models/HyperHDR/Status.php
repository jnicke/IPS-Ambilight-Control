<?php

declare(strict_types=1);

namespace MediaLight\Models\HyperHDR;

final class Status
{
    public function __construct(
        private readonly bool $online,
        private readonly string $version,
        private readonly bool $instanceEnabled,
        private readonly bool $grabberEnabled,
        private readonly bool $ledDeviceEnabled,
        private readonly bool $smoothingEnabled,
        private readonly float $fps,
        private readonly int $visiblePriority,
        private readonly int $effectCount,
        private readonly string $rawResponse
    ) {
    }

    public function isOnline(): bool
    {
        return $this->online;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function isInstanceEnabled(): bool
    {
        return $this->instanceEnabled;
    }

    public function isGrabberEnabled(): bool
    {
        return $this->grabberEnabled;
    }

    public function isLedDeviceEnabled(): bool
    {
        return $this->ledDeviceEnabled;
    }

    public function isSmoothingEnabled(): bool
    {
        return $this->smoothingEnabled;
    }

    public function getFps(): float
    {
        return $this->fps;
    }

    public function getVisiblePriority(): int
    {
        return $this->visiblePriority;
    }

    public function getEffectCount(): int
    {
        return $this->effectCount;
    }

    public function getRawResponse(): string
    {
        return $this->rawResponse;
    }
}