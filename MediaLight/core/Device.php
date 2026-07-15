<?php

declare(strict_types=1);

abstract class MediaLightDevice
{
    protected MediaLightHttpClient $httpClient;

    protected MediaLightLogger $logger;

    public function __construct(
        MediaLightHttpClient $httpClient,
        MediaLightLogger $logger
    ) {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
    }

    abstract public function getName(): string;

    abstract public function isOnline(): bool;

    /**
     * @return array<string, mixed>
     */
    abstract public function getStatus(): array;
}