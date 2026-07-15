<?php

declare(strict_types=1);

final class MediaLightHttpClient
{
    private int $timeout;

    private ?MediaLightLogger $logger;

    public function __construct(
        int $timeout = 5,
        ?MediaLightLogger $logger = null
    ) {
        $this->timeout = max(1, $timeout);
        $this->logger = $logger;
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
            'GET',
            $url,
            null,
            $headers
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
            'POST',
            $url,
            $payload,
            $headers
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
            CURLOPT_CONNECTTIMEOUT => $this->timeout,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headerLines
        ];

        if ($payload !== null) {
            $json = json_encode(
                $payload,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_THROW_ON_ERROR
            );

            $headerLines[] = 'Content-Type: application/json';

            $options[CURLOPT_HTTPHEADER] = $headerLines;
            $options[CURLOPT_POSTFIELDS] = $json;
        }

        curl_setopt_array($curl, $options);

        $this->logger?->debug(
            'HTTP request',
            [
                'method' => $method,
                'url'    => $url,
                'body'   => $payload
            ]
        );

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

        curl_close($curl);

        $this->logger?->debug(
            'HTTP response',
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
                'Die JSON-Antwort ist kein Objekt oder Array.'
            );
        }

        return $decoded;
    }
}