<?php

declare(strict_types=1);

require_once __DIR__ . '/src/Core/Autoloader.php';

use MediaLight\Core\Autoloader;
use MediaLight\Core\Config;
use MediaLight\Core\HttpClient;
use MediaLight\Core\Logger;
use MediaLight\Drivers\HyperHDR\Client as HyperHDRClient;
use MediaLight\Drivers\HyperHDR\Driver as HyperHDRDriver;
use MediaLight\Models\HyperHDR\Status as HyperHDRStatus;

Autoloader::register(__DIR__ . '/src');

class MediaLight extends IPSModule
{
    private const STATUS_ACTIVE = 102;
    private const STATUS_INACTIVE = 104;
    private const STATUS_HYPERHDR_OFFLINE = 201;

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyBoolean('Active', true);
        $this->RegisterPropertyInteger('UpdateInterval', 10);
        $this->RegisterPropertyBoolean('DebugEnabled', false);

        $this->RegisterPropertyBoolean('HyperHDREnabled', true);
        $this->RegisterPropertyString('HyperHDRHost', '');
        $this->RegisterPropertyInteger('HyperHDRPort', 8090);
        $this->RegisterPropertyBoolean('HyperHDRHTTPS', false);
        $this->RegisterPropertyString('HyperHDRPath', '/json-rpc');
        $this->RegisterPropertyString('HyperHDRToken', '');

        $this->RegisterVariableBoolean(
            'Online',
            'MediaLight online',
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
            'LastUpdate',
            'Letzte Aktualisierung',
            '',
            30
        );

        $this->RegisterVariableString(
            'LastError',
            'Letzter Fehler',
            '',
            40
        );

        $this->RegisterVariableBoolean(
            'HyperHDROnline',
            'HyperHDR online',
            '~Switch',
            100
        );

        $this->RegisterVariableString(
            'HyperHDRVersion',
            'HyperHDR-Version',
            '',
            110
        );

        $this->RegisterVariableBoolean(
            'HyperHDRInstanceEnabled',
            'HyperHDR-Instanz aktiv',
            '~Switch',
            120
        );

        $this->RegisterVariableBoolean(
            'HyperHDRGrabberEnabled',
            'Videoaufnahme aktiv',
            '~Switch',
            130
        );

        $this->RegisterVariableBoolean(
            'HyperHDRLEDDeviceEnabled',
            'LED-Gerät aktiv',
            '~Switch',
            140
        );

        $this->RegisterVariableBoolean(
            'HyperHDRSmoothingEnabled',
            'Glättung aktiv',
            '~Switch',
            150
        );

        $this->RegisterVariableFloat(
            'HyperHDRFPS',
            'HyperHDR FPS',
            '',
            160
        );

        $this->RegisterVariableInteger(
            'HyperHDRVisiblePriority',
            'Sichtbare Priorität',
            '',
            170
        );

        $this->RegisterVariableInteger(
            'HyperHDREffectCount',
            'Anzahl Effekte',
            '',
            180
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
            $this->resetAllStatus();

            return;
        }

        $this->SetStatus(self::STATUS_ACTIVE);
        $this->SetValue('Mode', 'READY');
        $this->SetValue('LastError', '');

        $this->Update();
    }

    public function Update(): void
    {
        if (!$this->getConfig()->isActive()) {
            return;
        }

        try {
            if ($this->ReadPropertyBoolean('HyperHDREnabled')) {
                $this->updateHyperHDR();
            } else {
                $this->resetHyperHDRStatus();
            }

            $this->SetValue('Online', true);
            $this->SetValue(
                'LastUpdate',
                date('d.m.Y H:i:s')
            );
            $this->SetValue('LastError', '');
            $this->SetStatus(self::STATUS_ACTIVE);
        } catch (Throwable $exception) {
            $this->SetValue('Online', false);
            $this->SetValue('LastError', $exception->getMessage());
            $this->SetStatus(self::STATUS_HYPERHDR_OFFLINE);

            $this->resetHyperHDRStatus();

            $this->getLogger()->error(
                'Aktualisierung fehlgeschlagen',
                [
                    'exception' => $exception::class,
                    'message'   => $exception->getMessage(),
                    'file'      => $exception->getFile(),
                    'line'      => $exception->getLine()
                ]
            );
        }
    }

    public function TestCore(): void
    {
        $this->getLogger()->info(
            'Core-Test erfolgreich.',
            [
                'phpVersion' => PHP_VERSION,
                'instanceId' => $this->InstanceID
            ]
        );

        echo 'MediaLight Core mit PSR-4 erfolgreich getestet.';
    }

    public function TestHyperHDR(): void
    {
        try {
            $status = $this->getHyperHDRDriver()->readStatus();

            $this->applyHyperHDRStatus($status);

            echo sprintf(
                'HyperHDR erreichbar. Version: %s, FPS: %.2f',
                $status->getVersion() !== ''
                    ? $status->getVersion()
                    : 'unbekannt',
                $status->getFps()
            );
        } catch (Throwable $exception) {
            $this->resetHyperHDRStatus();
            $this->SetValue('LastError', $exception->getMessage());

            echo 'HyperHDR-Test fehlgeschlagen: '
                . $exception->getMessage();
        }
    }

    public function WriteDebug(
        string $sender,
        string $message,
        int $format = 0
    ): void {
        $this->SendDebug($sender, $message, $format);
    }

    private function updateHyperHDR(): void
    {
        $status = $this->getHyperHDRDriver()->readStatus();

        $this->applyHyperHDRStatus($status);
    }

    private function applyHyperHDRStatus(
        HyperHDRStatus $status
    ): void {
        $this->SetValue(
            'HyperHDROnline',
            $status->isOnline()
        );

        $this->SetValue(
            'HyperHDRVersion',
            $status->getVersion()
        );

        $this->SetValue(
            'HyperHDRInstanceEnabled',
            $status->isInstanceEnabled()
        );

        $this->SetValue(
            'HyperHDRGrabberEnabled',
            $status->isGrabberEnabled()
        );

        $this->SetValue(
            'HyperHDRLEDDeviceEnabled',
            $status->isLedDeviceEnabled()
        );

        $this->SetValue(
            'HyperHDRSmoothingEnabled',
            $status->isSmoothingEnabled()
        );

        $this->SetValue(
            'HyperHDRFPS',
            $status->getFps()
        );

        $this->SetValue(
            'HyperHDRVisiblePriority',
            $status->getVisiblePriority()
        );

        $this->SetValue(
            'HyperHDREffectCount',
            $status->getEffectCount()
        );
    }

    private function getHyperHDRDriver(): HyperHDRDriver
    {
        $logger = $this->getLogger();

        $httpClient = new HttpClient(
            timeout: 5,
            logger: $logger
        );

        $client = new HyperHDRClient(
            httpClient: $httpClient,
            logger: $logger,
            host: $this->ReadPropertyString('HyperHDRHost'),
            port: $this->ReadPropertyInteger('HyperHDRPort'),
            https: $this->ReadPropertyBoolean('HyperHDRHTTPS'),
            path: $this->ReadPropertyString('HyperHDRPath'),
            token: $this->ReadPropertyString('HyperHDRToken')
        );

        return new HyperHDRDriver(
            client: $client,
            logger: $logger
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

    private function resetHyperHDRStatus(): void
    {
        $this->SetValue('HyperHDROnline', false);
        $this->SetValue('HyperHDRVersion', '');
        $this->SetValue('HyperHDRInstanceEnabled', false);
        $this->SetValue('HyperHDRGrabberEnabled', false);
        $this->SetValue('HyperHDRLEDDeviceEnabled', false);
        $this->SetValue('HyperHDRSmoothingEnabled', false);
        $this->SetValue('HyperHDRFPS', 0.0);
        $this->SetValue('HyperHDRVisiblePriority', -1);
        $this->SetValue('HyperHDREffectCount', 0);
    }

    private function resetAllStatus(): void
    {
        $this->SetValue('Online', false);
        $this->SetValue('Mode', 'INACTIVE');
        $this->SetValue('LastUpdate', '');
        $this->SetValue('LastError', '');

        $this->resetHyperHDRStatus();
    }
}