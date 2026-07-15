<?php

declare(strict_types=1);

namespace MediaLight\Models\HyperHDR;

final class Status
{
    public function __construct(
        private readonly bool $online,
        private readonly string $version,
        private readonly string $hostname,
        private readonly int $currentInstance,
        private readonly string $instanceName,
        private readonly bool $instanceEnabled,
        private readonly bool $grabberEnabled,
        private readonly string $grabberDevice,
        private readonly string $videoMode,
        private readonly bool $ledDeviceEnabled,
        private readonly bool $smoothingEnabled,
        private readonly bool $hdrEnabled,
        private readonly bool $blackBorderEnabled,
        private readonly bool $forwarderEnabled,
        private readonly float $fps,
        private readonly int $visiblePriority,
        private readonly string $priorityComponent,
        private readonly string $priorityOwner,
        private readonly int $effectCount,
        private readonly int $ledCount,
        private readonly bool $wledConnected,
        private readonly int $sessionCount,
        private readonly string $lastError,
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

    public function getHostname(): string
    {
        return $this->hostname;
    }

    public function getCurrentInstance(): int
    {
        return $this->currentInstance;
    }

    public function getInstanceName(): string
    {
        return $this->instanceName;
    }

    public function isInstanceEnabled(): bool
    {
        return $this->instanceEnabled;
    }

    public function isGrabberEnabled(): bool
    {
        return $this->grabberEnabled;
    }

    public function getGrabberDevice(): string
    {
        return $this->grabberDevice;
    }

    public function getVideoMode(): string
    {
        return $this->videoMode;
    }

    public function isLedDeviceEnabled(): bool
    {
        return $this->ledDeviceEnabled;
    }

    public function isSmoothingEnabled(): bool
    {
        return $this->smoothingEnabled;
    }

    public function isHdrEnabled(): bool
    {
        return $this->hdrEnabled;
    }

    public function isBlackBorderEnabled(): bool
    {
        return $this->blackBorderEnabled;
    }

    public function isForwarderEnabled(): bool
    {
        return $this->forwarderEnabled;
    }

    public function getFps(): float
    {
        return $this->fps;
    }

    public function getVisiblePriority(): int
    {
        return $this->visiblePriority;
    }

    public function getPriorityComponent(): string
    {
        return $this->priorityComponent;
    }

    public function getPriorityOwner(): string
    {
        return $this->priorityOwner;
    }

    public function getEffectCount(): int
    {
        return $this->effectCount;
    }

    public function getLedCount(): int
    {
        return $this->ledCount;
    }

    public function isWledConnected(): bool
    {
        return $this->wledConnected;
    }

    public function getSessionCount(): int
    {
        return $this->sessionCount;
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    public function getRawResponse(): string
    {
        return $this->rawResponse;
    }
}