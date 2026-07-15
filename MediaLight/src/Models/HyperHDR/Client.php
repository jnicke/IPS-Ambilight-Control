<?php

declare(strict_types=1);

namespace MediaLight\Drivers\HyperHDR;

use MediaLight\Core\HttpClient;
use MediaLight\Core\Logger;
use RuntimeException;

final class Client
{
    private int $transactionId = 1;

    public function __construct(
        private readonly HttpClient $httpClient,
        private readonly Logger $logger,
        private readonly string $host,
        private readonly int $port = 8090,
        private readonly bool $https = false,
        private readonly string $path = '/json-rpc',
        private readonly string $token = ''
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getServerInfo(): array
    {
        return $this->request('serverinfo');
    }

    /**
     * Diese Methode liest ausschließlich Daten.
     *
     * @param array<string, mixed> $parameters
     *
     * @return array<string, mixed>
     */
    public function request(
        string $command,
        array $parameters = []
    ): array {
        if (trim($this->host) === '') {
            throw new RuntimeException(
                'Kein HyperHDR-Host konfiguriert.'
            );
        }

        $payload = array_merge(
            [
                'command' => $command,
                'tan'     => $this->nextTransactionId()
            ],
            $parameters
        );

        $headers = [];

        if (trim($this->token) !== '') {
            $headers['Authorization'] = 'Bearer ' . trim($this->token);
        }

        $this->logger->debug(
            'HyperHDR-Anfrage',
            [
                'endpoint' => $this->getEndpoint(),
                'payload'  => $payload
            ]
        );

        $response = $this->httpClient->postJson(
            $this->getEndpoint(),
            $payload,
            $headers
        );

        $this->logger->debug(
            'HyperHDR-Antwort',
            $response
        );

        if (
            array_key_exists('success', $response)
            && $response['success'] !== true
        ) {
            $error = isset($response['error'])
                ? (string) $response['error']
                : 'HyperHDR meldet einen unbekannten Fehler.';

            throw new RuntimeException($error);
        }

        return $response;
    }

    private function getEndpoint(): string
    {
        $scheme = $this->https ? 'https' : 'http';
        $path = '/' . ltrim($this->path, '/');

        return sprintf(
            '%s://%s:%d%s',
            $scheme,
            trim($this->host),
            $this->port,
            $path
        );
    }

    private function nextTransactionId(): int
    {
        $current = $this->transactionId++;

        if ($this->transactionId > 2147483647) {
            $this->transactionId = 1;
        }

        return $current;
    }
}