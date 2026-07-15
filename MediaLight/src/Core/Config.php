<?php

declare(strict_types=1);

namespace MediaLight\Core;

final class Config
{
    public function __construct(
        private readonly bool $active,
        private readonly int $updateInterval,
        private readonly bool $debugEnabled
    ) {
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getUpdateInterval(): int
    {
        return max(1, $this->updateInterval);
    }

    public function isDebugEnabled(): bool
    {
        return $this->debugEnabled;
    }
}