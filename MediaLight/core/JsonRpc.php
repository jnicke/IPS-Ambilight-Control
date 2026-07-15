<?php

declare(strict_types=1);

final class MediaLightJsonRpc
{
    private MediaLightHttpClient $httpClient;

    private string $endpoint;

    private int $transactionId = 1;

    public function __construct(
        MediaLightHttpClient $httpClient,
        string $endpoint
    ) {
        $this->httpClient = $httpClient;
        $this->endpoint = $endpoint;
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return array<string, mixed>
     */
    public function call(
        string $command,
        array $parameters = []
    ): array {
        $payload = array_merge(
            [
                'command' => $command,
                'tan'     => $this->nextTransactionId()
            ],
            $parameters
        );

        $response = $this->httpClient->postJson(
            $this->endpoint,
            $payload
        );

        if (
            array_key_exists('success', $response)
            && $response['success'] === false
        ) {
            $message = isset($response['error'])
                ? (string) $response['error']
                : 'Unbekannter JSON-RPC-Fehler';

            throw new RuntimeException($message);
        }

        return $response;
    }

    private function nextTransactionId(): int
    {
        $current = $this->transactionId;
        $this->transactionId++;

        if ($this->transactionId > 2147483647) {
            $this->transactionId = 1;
        }

        return $current;
    }
}