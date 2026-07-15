<?php

declare(strict_types=1);

final class MediaLightConfig
{
    private bool $active;

    private int $updateInterval;

    private bool $debugEnabled;

    public function __construct(
        bool $active,
        int $updateInterval,
        bool $debugEnabled
    ) {
        $this->active = $active;
        $this->updateInterval = max(1, $updateInterval);
        $this->debugEnabled = $debugEnabled;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getUpdateInterval(): int
    {
        return $this->updateInterval;
    }

    public function isDebugEnabled(): bool
    {
        return $this->debugEnabled;
    }
}