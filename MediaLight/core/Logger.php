<?php

declare(strict_types=1);

final class MediaLightLogger
{
    private IPSModule $module;

    private bool $debugEnabled;

    public function __construct(
        IPSModule $module,
        bool $debugEnabled
    ) {
        $this->module = $module;
        $this->debugEnabled = $debugEnabled;
    }

    /**
     * @param mixed $data
     */
    public function debug(string $message, $data = null): void
    {
        if (!$this->debugEnabled) {
            return;
        }

        $this->write('DEBUG', $message, $data);
    }

    /**
     * @param mixed $data
     */
    public function info(string $message, $data = null): void
    {
        $this->write('INFO', $message, $data);
    }

    /**
     * @param mixed $data
     */
    public function warning(string $message, $data = null): void
    {
        $this->write('WARNING', $message, $data);
    }

    /**
     * @param mixed $data
     */
    public function error(string $message, $data = null): void
    {
        $this->write('ERROR', $message, $data);
    }

    /**
     * @param mixed $data
     */
    private function write(
        string $level,
        string $message,
        $data
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

        $this->module->SendDebug(
            'MediaLight ' . $level,
            $payload,
            0
        );
    }
}