<?php

declare(strict_types=1);

require_once __DIR__ . '/src/Core/Autoloader.php';

use MediaLight\Core\Autoloader;
use MediaLight\Core\Config;
use MediaLight\Core\HttpClient;
use MediaLight\Core\Logger;

Autoloader::register(__DIR__ . '/src');

class MediaLight extends IPSModule
{
    private const STATUS_ACTIVE = 102;
    private const STATUS_INACTIVE = 104;
    private const STATUS_ERROR = 200;

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyBoolean('Active', true);
        $this->RegisterPropertyInteger('UpdateInterval', 10);
        $this->RegisterPropertyBoolean('DebugEnabled', false);

        $this->RegisterVariableBoolean(
            'Online',
            'Online',
            '~Switch',
            10
        );

        $this->RegisterVariableString(
            'Mode',
            'Modus',
            '',
            20
        );

        $this->RegisterVariableString(
            'Scene',
            'Szene',
            '',
            30
        );

        $this->RegisterVariableString(
            'LastUpdate',
            'Letzte Aktualisierung',
            '',
            40
        );

        $this->RegisterVariableString(
            'LastError',
            'Letzter Fehler',
            '',
            50
        );

        $this->RegisterTimer(
            'UpdateTimer',
            0,
            'AMBI_Update($_IPS["TARGET"]);'
        );
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $config = $this->getConfig();

        $this->SetTimerInterval(
            'UpdateTimer',
            $config->isActive()
                ? $config->getUpdateInterval() * 1000
                : 0
        );

        if (!$config->isActive()) {
            $this->SetStatus(self::STATUS_INACTIVE);
            $this->resetRuntimeStatus();

            return;
        }

        $this->SetStatus(self::STATUS_ACTIVE);
        $this->SetValue('Mode', 'INITIALIZING');
        $this->SetValue('Scene', '');
        $this->SetValue('LastError', '');

        $this->getLogger()->info(
            'MediaLight wurde initialisiert.',
            [
                'instanceId'     => $this->InstanceID,
                'updateInterval' => $config->getUpdateInterval()
            ]
        );

        $this->Update();
    }

    public function Update(): void
    {
        if (!$this->getConfig()->isActive()) {
            return;
        }

        try {
            $this->getLogger()->debug(
                'Core-Aktualisierung gestartet.'
            );

            $this->SetValue('Online', true);
            $this->SetValue('Mode', 'READY');
            $this->SetValue(
                'LastUpdate',
                date('d.m.Y H:i:s')
            );
            $this->SetValue('LastError', '');
            $this->SetStatus(self::STATUS_ACTIVE);
        } catch (Throwable $exception) {
            $this->handleException(
                'Update',
                $exception
            );
        }
    }

    public function TestCore(): void
    {
        try {
            $config = $this->getConfig();

            $httpClient = new HttpClient(
                timeout: 5,
                logger: $this->getLogger()
            );

            $result = [
                'instanceId'     => $this->InstanceID,
                'active'         => $config->isActive(),
                'updateInterval' => $config->getUpdateInterval(),
                'debugEnabled'   => $config->isDebugEnabled(),
                'phpVersion'     => PHP_VERSION,
                'httpClient'     => $httpClient::class,
                'timestamp'      => time()
            ];

            $this->getLogger()->info(
                'Core-Test erfolgreich.',
                $result
            );

            $this->SetValue('Online', true);
            $this->SetValue('Mode', 'CORE_OK');
            $this->SetValue(
                'LastUpdate',
                date('d.m.Y H:i:s')
            );
            $this->SetValue('LastError', '');

            echo 'MediaLight Core mit PSR-4 erfolgreich getestet.';
        } catch (Throwable $exception) {
            $this->handleException(
                'Core-Test',
                $exception
            );

            echo 'MediaLight Core-Test fehlgeschlagen: '
                . $exception->getMessage();
        }
    }

    public function WriteDebug(
        string $sender,
        string $message,
        int $format = 0
    ): void {
        $this->SendDebug(
            $sender,
            $message,
            $format
        );
    }

    private function getConfig(): Config
    {
        return new Config(
            active: $this->ReadPropertyBoolean('Active'),
            updateInterval: $this->ReadPropertyInteger(
                'UpdateInterval'
            ),
            debugEnabled: $this->ReadPropertyBoolean(
                'DebugEnabled'
            )
        );
    }

    private function getLogger(): Logger
    {
        return new Logger(
            debugWriter: function (
                string $sender,
                string $message,
                int $format
            ): void {
                $this->WriteDebug(
                    $sender,
                    $message,
                    $format
                );
            },
            debugEnabled: $this->ReadPropertyBoolean(
                'DebugEnabled'
            )
        );
    }

    private function resetRuntimeStatus(): void
    {
        $this->SetValue('Online', false);
        $this->SetValue('Mode', 'INACTIVE');
        $this->SetValue('Scene', '');
        $this->SetValue('LastUpdate', '');
        $this->SetValue('LastError', '');
    }

    private function handleException(
        string $context,
        Throwable $exception
    ): void {
        $message = sprintf(
            '%s: %s',
            $context,
            $exception->getMessage()
        );

        $this->SetValue('Online', false);
        $this->SetValue('Mode', 'ERROR');
        $this->SetValue('LastError', $message);
        $this->SetStatus(self::STATUS_ERROR);

        $this->getLogger()->error(
            $message,
            [
                'class' => $exception::class,
                'file'  => $exception->getFile(),
                'line'  => $exception->getLine()
            ]
        );
    }
}