<?php

declare(strict_types=1);

namespace MediaLight\Models\WLED;

final class Controller
{
    /**
     * @param Bus[] $buses
     */
    public function __construct(
        private readonly bool $online,
        private readonly string $name,
        private readonly string $firmware,
        private readonly string $release,
        private readonly string $architecture,
        private readonly string $ipAddress,
        private readonly string $macAddress,
        private readonly int $ledCount,
        private readonly bool $rgbw,
        private readonly int $maximumCurrent,
        private readonly int $currentPower,
        private readonly int $framesPerSecond,
        private readonly int $effectCount,
        private readonly int $paletteCount,
        private readonly int $uptime,
        private readonly int $freeHeap,
        private readonly int $rssi,
        private readonly int $signalQuality,
        private readonly string $liveMode,
        private readonly string $liveSourceIp,
        private readonly array $buses,
        private readonly State $state
    ) {
    }

    public function isOnline(): bool
    {
        return $this->online;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getFirmware(): string
    {
        return $this->firmware;
    }

    public function getRelease(): string
    {
        return $this->release;
    }

    public function getArchitecture(): string
    {
        return $this->architecture;
    }

    public function getIpAddress(): string
    {
        return $this->ipAddress;
    }

    public function getMacAddress(): string
    {
        return $this->macAddress;
    }

    public function getLedCount(): int
    {
        return $this->ledCount;
    }

    public function isRgbw(): bool
    {
        return $this->rgbw;
    }

    public function getMaximumCurrent(): int
    {
        return $this->maximumCurrent;
    }

    public function getCurrentPower(): int
    {
        return $this->currentPower;
    }

    public function getFramesPerSecond(): int
    {
        return $this->framesPerSecond;
    }

    public function getEffectCount(): int
    {
        return $this->effectCount;
    }

    public function getPaletteCount(): int
    {
        return $this->paletteCount;
    }

    public function getUptime(): int
    {
        return $this->uptime;
    }

    public function getFreeHeap(): int
    {
        return $this->freeHeap;
    }

    public function getRssi(): int
    {
        return $this->rssi;
    }

    public function getSignalQuality(): int
    {
        return $this->signalQuality;
    }

    public function getLiveMode(): string
    {
        return $this->liveMode;
    }

    public function getLiveSourceIp(): string
    {
        return $this->liveSourceIp;
    }

    /**
     * @return Bus[]
     */
    public function getBuses(): array
    {
        return $this->buses;
    }

    public function getBus(int $index): ?Bus
    {
        foreach ($this->buses as $bus) {
            if ($bus->getIndex() === $index) {
                return $bus;
            }
        }

        return null;
    }

    public function getBusCount(): int
    {
        return count($this->buses);
    }

    public function getState(): State
    {
        return $this->state;
    }
}