<?php

declare(strict_types=1);

namespace MediaLight\Drivers\AppleTV;

use MediaLight\Core\HttpClient;
use MediaLight\Core\Logger;
use RuntimeException;

final class Client
{
    public function __construct(
        private readonly HttpClient $httpClient,
        private readonly Logger $logger,
        private readonly string $host,
        private readonly int $port = 8091,
        private readonly bool $https = false
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getStatus(): array
    {
        $url = $this->getBaseUrl() . '/status';

        $this->logger->debug(
            'Apple-TV-Bridge abrufen',
            ['url' => $url]
        );

        return $this->httpClient->getJson($url);
    }

    private function getBaseUrl(): string
    {
        $host = trim($this->host);

        if ($host === '') {
            throw new RuntimeException(
                'Kein Apple-TV-Bridge-Host konfiguriert.'
            );
        }

        if (
            str_contains($host, '/')
            || str_contains($host, '://')
        ) {
            throw new RuntimeException(
                'Der Apple-TV-Bridge-Host darf nur Hostname '
                . 'oder IP-Adresse enthalten.'
            );
        }

        return sprintf(
            '%s://%s:%d',
            $this->https ? 'https' : 'http',
            $host,
            $this->port
        );
    }
}