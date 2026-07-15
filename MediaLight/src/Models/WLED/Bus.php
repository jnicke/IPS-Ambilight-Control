<?php

declare(strict_types=1);

namespace MediaLight\Models\WLED;

final class Bus
{
    /**
     * @param int[] $pins
     */
    public function __construct(
        private readonly int $index,
        private readonly int $start,
        private readonly int $length,
        private readonly array $pins,
        private readonly int $type,
        private readonly int $colorOrder,
        private readonly bool $reversed,
        private readonly int $skip,
        private readonly int $milliAmpsPerLed,
        private readonly int $maximumCurrent,
        private readonly bool $refreshRequired
    ) {
    }

    public function getIndex(): int
    {
        return $this->index;
    }

    public function getNumber(): int
    {
        return $this->index + 1;
    }

    public function getStart(): int
    {
        return $this->start;
    }

    public function getLength(): int
    {
        return $this->length;
    }

    public function getStop(): int
    {
        return $this->start + $this->length;
    }

    /**
     * @return int[]
     */
    public function getPins(): array
    {
        return $this->pins;
    }

    public function getPrimaryPin(): int
    {
        return $this->pins[0] ?? -1;
    }

    public function getType(): int
    {
        return $this->type;
    }

    public function getColorOrder(): int
    {
        return $this->colorOrder;
    }

    public function isReversed(): bool
    {
        return $this->reversed;
    }

    public function getSkip(): int
    {
        return $this->skip;
    }

    public function getMilliAmpsPerLed(): int
    {
        return $this->milliAmpsPerLed;
    }

    public function getMaximumCurrent(): int
    {
        return $this->maximumCurrent;
    }

    public function isRefreshRequired(): bool
    {
        return $this->refreshRequired;
    }
}