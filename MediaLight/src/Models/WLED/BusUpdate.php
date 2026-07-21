<?php

declare(strict_types=1);

namespace MediaLight\Models\WLED;

use InvalidArgumentException;

final class BusUpdate
{
    private ?bool $power = null;

    private ?int $brightness = null;

    /**
     * @var array{0:int,1:int,2:int,3:int}|null
     */
    private ?array $color = null;

    private ?int $effect = null;

    private ?int $speed = null;

    private ?int $intensity = null;

    private ?int $palette = null;

    private ?bool $reversed = null;

    private ?bool $frozen = null;

    public function __construct(
        private readonly int $busNumber,
        private readonly int $segmentId
    ) {
        if ($busNumber < 1) {
            throw new InvalidArgumentException(
                'Die Busnummer muss mindestens 1 sein.'
            );
        }

        if ($segmentId < 0) {
            throw new InvalidArgumentException(
                'Die Segment-ID darf nicht negativ sein.'
            );
        }
    }

    public function getBusNumber(): int
    {
        return $this->busNumber;
    }

    public function getSegmentId(): int
    {
        return $this->segmentId;
    }

    public function power(bool $power): self
    {
        $this->power = $power;

        return $this;
    }

    public function brightness(int $brightness): self
    {
        $this->brightness = self::limitByte($brightness);

        return $this;
    }

    public function rgb(
        int $red,
        int $green,
        int $blue
    ): self {
        return $this->rgbw(
            $red,
            $green,
            $blue,
            0
        );
    }

    public function rgbw(
        int $red,
        int $green,
        int $blue,
        int $white
    ): self {
        $this->color = [
            self::limitByte($red),
            self::limitByte($green),
            self::limitByte($blue),
            self::limitByte($white)
        ];

        return $this;
    }

    public function effect(int $effect): self
    {
        $this->effect = self::limitByte($effect);

        return $this;
    }

    public function speed(int $speed): self
    {
        $this->speed = self::limitByte($speed);

        return $this;
    }

    public function intensity(int $intensity): self
    {
        $this->intensity = self::limitByte($intensity);

        return $this;
    }

    public function palette(int $palette): self
    {
        $this->palette = self::limitByte($palette);

        return $this;
    }

    public function reversed(bool $reversed): self
    {
        $this->reversed = $reversed;

        return $this;
    }

    public function freeze(bool $frozen): self
    {
        $this->frozen = $frozen;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        $payload = [
            'id' => $this->segmentId
        ];

        if ($this->power !== null) {
            $payload['on'] = $this->power;
        }

        if ($this->brightness !== null) {
            $payload['bri'] = $this->brightness;
        }

        if ($this->color !== null) {
            $payload['col'] = [
                $this->color
            ];
        }

        if ($this->effect !== null) {
            $payload['fx'] = $this->effect;
        }

        if ($this->speed !== null) {
            $payload['sx'] = $this->speed;
        }

        if ($this->intensity !== null) {
            $payload['ix'] = $this->intensity;
        }

        if ($this->palette !== null) {
            $payload['pal'] = $this->palette;
        }

        if ($this->reversed !== null) {
            $payload['rev'] = $this->reversed;
        }

        if ($this->frozen !== null) {
            $payload['frz'] = $this->frozen;
        }

        return $payload;
    }

    private static function limitByte(int $value): int
    {
        return max(0, min(255, $value));
    }
}