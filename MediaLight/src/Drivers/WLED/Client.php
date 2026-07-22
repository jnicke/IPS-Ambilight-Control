<?php

declare(strict_types=1);

namespace MediaLight\Drivers\WLED;

use MediaLight\Core\HttpClient;
use MediaLight\Core\Logger;
use RuntimeException;

final class Client
{
    public function __construct(
        private readonly HttpClient $httpClient,
        private readonly Logger $logger,
        private readonly string $host,
        private readonly bool $https = false
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getInfo(): array
    {
        return $this->get('/json/info');
    }

    /**
     * @return array<string, mixed>
     */
    public function getState(): array
    {
        return $this->get('/json/state');
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->get('/json/cfg');
    }

    /**
     * Liefert die Effektnamen des Controllers in Reihenfolge der Effekt-IDs.
     *
     * @return list<string>
     */
    public function getEffects(): array
    {
        $effects = $this->get('/json/effects');

        $names = [];

        foreach ($effects as $name) {
            if (!is_string($name)) {
                continue;
            }

            $names[] = $name;
        }

        return $names;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function postState(array $payload): array
    {
        if (!array_key_exists('v', $payload)) {
            $payload['v'] = true;
        }

        $url = $this->getBaseUrl() . '/json/state';

        $this->logger->debug(
            'WLED-Zustand senden',
            [
                'url'     => $url,
                'payload' => $payload
            ]
        );

        return $this->httpClient->postJson(
            $url,
            $payload
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function get(string $path): array
    {
        $url = $this->getBaseUrl() . $path;

        $this->logger->debug(
            'WLED-Daten abrufen',
            ['url' => $url]
        );

        return $this->httpClient->getJson($url);
    }

    private function getBaseUrl(): string
    {
        $host = trim($this->host);

        if ($host === '') {
            throw new RuntimeException(
                'Kein WLED-Hostname bzw. keine WLED-IP-Adresse konfiguriert.'
            );
        }

        if (
            str_contains($host, '/')
            || str_contains($host, '://')
        ) {
            throw new RuntimeException(
                'Der WLED-Host darf nur Hostname oder IP-Adresse enthalten.'
            );
        }

        return sprintf(
            '%s://%s',
            $this->https ? 'https' : 'http',
            $host
        );
    }
}