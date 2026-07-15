<?php

declare(strict_types=1);

namespace MediaLight\Core;

final class Logger
{
    /**
     * @var callable(string, string, int): void
     */
    private $debugWriter;

    /**
     * @param callable(string, string, int): void $debugWriter
     */
    public function __construct(
        callable $debugWriter,
        private readonly bool $debugEnabled
    ) {
        $this->debugWriter = $debugWriter;
    }

    public function debug(string $message, mixed $data = null): void
    {
        if (!$this->debugEnabled) {
            return;
        }

        $this->write('DEBUG', $message, $data);
    }

    public function info(string $message, mixed $data = null): void
    {
        $this->write('INFO', $message, $data);
    }

    public function warning(string $message, mixed $data = null): void
    {
        $this->write('WARNING', $message, $data);
    }

    public function error(string $message, mixed $data = null): void
    {
        $this->write('ERROR', $message, $data);
    }

    private function write(
        string $level,
        string $message,
        mixed $data = null
    ): void {
        $payload = $message;

        if ($data !== null) {
            $encoded = json_encode(
                $data,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_PARTIAL_OUTPUT_ON_ERROR
            );

            $payload .= ' | ' . (
                $encoded !== false
                    ? $encoded
                    : '[nicht serialisierbar]'
            );
        }

        ($this->debugWriter)(
            'MediaLight ' . $level,
            $payload,
            0
        );
    }
}