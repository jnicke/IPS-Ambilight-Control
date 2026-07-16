<?php

declare(strict_types=1);

namespace MediaLight\Models\AppleTV;

final class Status
{
    public function __construct(
        private readonly bool $online,
        private readonly string $power,
        private readonly string $state,
        private readonly string $deviceState,
        private readonly string $mediaType,
        private readonly string $title,
        private readonly string $artist,
        private readonly string $album,
        private readonly string $app,
        private readonly string $appId,
        private readonly float $position,
        private readonly float $totalTime,
        private readonly int $updated,
        private readonly int $lastEvent,
        private readonly string $error,
        private readonly string $identifier
    ) {
    }

    public function isOnline(): bool
    {
        return $this->online;
    }

    public function getPower(): string
    {
        return $this->power;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function getDeviceState(): string
    {
        return $this->deviceState;
    }

    public function getMediaType(): string
    {
        return $this->mediaType;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getArtist(): string
    {
        return $this->artist;
    }

    public function getAlbum(): string
    {
        return $this->album;
    }

    public function getApp(): string
    {
        return $this->app;
    }

    public function getAppId(): string
    {
        return $this->appId;
    }

    public function getPosition(): float
    {
        return $this->position;
    }

    public function getTotalTime(): float
    {
        return $this->totalTime;
    }

    public function getUpdated(): int
    {
        return $this->updated;
    }

    public function getLastEvent(): int
    {
        return $this->lastEvent;
    }

    public function getError(): string
    {
        return $this->error;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }
}