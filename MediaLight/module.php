<?php

declare(strict_types=1);

require_once __DIR__ . '/core/Autoloader.php';

MediaLightAutoloader::register(
    __DIR__ . '/core'
);

class MediaLight extends IPSModule
{
    private const STATUS_ACTIVE = 102;

    private const STATUS_INACTIVE = 104;

    private const STATUS_ERROR = 200;

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyBoolean(
            'Active',
            true
        );

        $this->RegisterPropertyInteger(
            'UpdateInterval',
            10
        );

        $this->RegisterPropertyBoolean(
            'DebugEnabled',
            false
        );

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
        $config = $this->getConfig();

        if (!$config->isActive()) {
            return;
        }

        $logger = $this->getLogger();

        try {
            $logger->debug('Core-Aktualisierung gestartet.');

            $this->SetValue('Online', true);
            $this->SetValue('Mode', 'READY');
            $this->SetValue(
                'LastUpdate',
                date('d.m.Y H:i:s')
            );
            $this->SetValue('LastError', '');

            $this->SetStatus(self::STATUS_ACTIVE);

            $logger->debug('Core-Aktualisierung beendet.');
        } catch (Throwable $exception) {
            $this->handleException(
                'Update',
                $exception
            );
        }
    }

    public function TestCore(): void
    {
        $logger = $this->getLogger();

        try {
            $config = $this->getConfig();

            $result = [
                'instanceId'     => $this->InstanceID,
                'active'         => $config->isActive(),
                'updateInterval' => $config->getUpdateInterval(),
                'debugEnabled'   => $config->isDebugEnabled(),
                'phpVersion'     => PHP_VERSION,
                'timestamp'      => time()
            ];

            $logger->info(
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

            echo 'MediaLight Core erfolgreich getestet.';
        } catch (Throwable $exception) {
            $this->handleException(
                'Core-Test',
                $exception
            );

            echo 'MediaLight Core-Test fehlgeschlagen: '
                . $exception->getMessage();
        }
    }

    private function getConfig(): MediaLightConfig
    {
        return new MediaLightConfig(
            $this->ReadPropertyBoolean('Active'),
            $this->ReadPropertyInteger('UpdateInterval'),
            $this->ReadPropertyBoolean('DebugEnabled')
        );
    }

    private function getLogger(): MediaLightLogger
    {
        return new MediaLightLogger(
            $this,
            $this->ReadPropertyBoolean('DebugEnabled')
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
                'file' => $exception->getFile(),
                'line' => $exception->getLine()
            ]
        );
    }
}