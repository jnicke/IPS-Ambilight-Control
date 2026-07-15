<?php

declare(strict_types=1);

namespace MediaLight\Models\WLED;

final class Segment
{
    /**
     * @param array<int, array<int, int>> $colors
     */
    public function __construct(
        private readonly int $id,
        private readonly int $start,
        private readonly int $stop,
        private readonly int $length,
        private readonly bool $on,
        private readonly bool $frozen,
        private readonly int $brightness,
        private readonly array $colors,
        private readonly int $effect,
        private readonly int $speed,
        private readonly int $intensity,
        private readonly int $palette,
        private readonly bool $selected,
        private readonly bool $reversed
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getStart(): int
    {
        return $this->start;
    }

    public function getStop(): int
    {
        return $this->stop;
    }

    public function getLength(): int
    {
        return $this->length;
    }

    public function isOn(): bool
    {
        return $this->on;
    }

    public function isFrozen(): bool
    {
        return $this->frozen;
    }

    public function getBrightness(): int
    {
        return $this->brightness;
    }

    /**
     * @return array<int, array<int, int>>
     */
    public function getColors(): array
    {
        return $this->colors;
    }

    /**
     * @return int[]
     */
    public function getPrimaryColor(): array
    {
        return $this->colors[0] ?? [0, 0, 0, 0];
    }

    public function getEffect(): int
    {
        return $this->effect;
    }

    public function getSpeed(): int
    {
        return $this->speed;
    }

    public function getIntensity(): int
    {
        return $this->intensity;
    }

    public function getPalette(): int
    {
        return $this->palette;
    }

    public function isSelected(): bool
    {
        return $this->selected;
    }

    public function isReversed(): bool
    {
        return $this->reversed;
    }
}