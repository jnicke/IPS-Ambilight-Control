<?php

declare(strict_types=1);

namespace MediaLight\Core;

use JsonException;
use RuntimeException;

final class HttpClient
{
    private const BUSY_RETRY_ATTEMPTS = 3;
    private const BUSY_RETRY_DELAY_MICROSECONDS = 300000;

    public function __construct(
        private readonly int $timeout = 5,
        private readonly ?Logger $logger = null
    ) {
    }

    /**
     * @param array<string, string> $headers
     *
     * @return array<string, mixed>
     */
    public function getJson(
        string $url,
        array $headers = []
    ): array {
        return $this->requestJson(
            method: 'GET',
            url: $url,
            payload: null,
            headers: $headers
        );
    }

    /**
     * @param array<string, mixed>  $payload
     * @param array<string, string> $headers
     *
     * @return array<string, mixed>
     */
    public function postJson(
        string $url,
        array $payload,
        array $headers = []
    ): array {
        return $this->requestJson(
            method: 'POST',
            url: $url,
            payload: $payload,
            headers: $headers
        );
    }

    /**
     * @param array<string, mixed>  $payload
     * @param array<string, string> $headers
     *
     * @return array<string, mixed>
     */
    public function putJson(
        string $url,
        array $payload,
        array $headers = []
    ): array {
        return $this->requestJson(
            method: 'PUT',
            url: $url,
            payload: $payload,
            headers: $headers
        );
    }

    /**
     * @param array<string, mixed>|null $payload
     * @param array<string, string>     $headers
     *
     * @return array<string, mixed>
     */
    private function requestJson(
        string $method,
        string $url,
        ?array $payload,
        array $headers
    ): array {
        $curl = curl_init();

        if ($curl === false) {
            throw new RuntimeException(
                'cURL konnte nicht initialisiert werden.'
            );
        }

        $headerLines = [
            'Accept: application/json'
        ];

        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $options = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => max(1, $this->timeout),
            CURLOPT_TIMEOUT        => max(1, $this->timeout),
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_HTTPHEADER     => $headerLines,
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4
        ];

        if ($payload !== null) {
            try {
                $json = json_encode(
                    $payload,
                    JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_THROW_ON_ERROR
                );
            } catch (JsonException $exception) {
                curl_close($curl);

                throw new RuntimeException(
                    'HTTP-Nutzdaten konnten nicht als JSON codiert werden: '
                    . $exception->getMessage(),
                    0,
                    $exception
                );
            }

            $headerLines[] = 'Content-Type: application/json';

            $options[CURLOPT_HTTPHEADER] = $headerLines;
            $options[CURLOPT_POSTFIELDS] = $json;
        }

        curl_setopt_array($curl, $options);

        $this->logger?->debug(
            'HTTP-Anfrage',
            [
                'method'  => strtoupper($method),
                'url'     => $url,
                'payload' => $payload
            ]
        );

        $response = false;
        $statusCode = 0;
        $attempt = 0;

        do {
            $attempt++;

            $response = curl_exec($curl);

            if ($response === false) {
                $error = curl_error($curl);
                curl_close($curl);

                throw new RuntimeException(
                    'HTTP-Anfrage fehlgeschlagen: ' . $error
                );
            }

            $statusCode = (int) curl_getinfo(
                $curl,
                CURLINFO_HTTP_CODE
            );

            if (
                $statusCode !== 503
                || $attempt >= self::BUSY_RETRY_ATTEMPTS
            ) {
                break;
            }

            $this->logger?->debug(
                'HTTP 503 (busy), Anfrage wird wiederholt',
                [
                    'attempt' => $attempt,
                    'url'     => $url
                ]
            );

            usleep(self::BUSY_RETRY_DELAY_MICROSECONDS);
        } while (true);

        curl_close($curl);

        $this->logger?->debug(
            'HTTP-Antwort',
            [
                'statusCode' => $statusCode,
                'body'       => $response
            ]
        );

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException(
                sprintf(
                    'HTTP-Status %d von %s',
                    $statusCode,
                    $url
                )
            );
        }

        try {
            $decoded = json_decode(
                $response,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'Ungültige JSON-Antwort: '
                . $exception->getMessage(),
                0,
                $exception
            );
        }

        if (!is_array($decoded)) {
            throw new RuntimeException(
                'Die HTTP-Antwort enthält kein JSON-Objekt.'
            );
        }

        return $decoded;
    }
}