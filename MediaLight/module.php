<?php

declare(strict_types=1);

require_once __DIR__ . '/src/Core/Autoloader.php';

use MediaLight\Core\Autoloader;
use MediaLight\Core\Config;
use MediaLight\Core\HttpClient;
use MediaLight\Core\Logger;
use MediaLight\Core\StatusManager;
use MediaLight\Drivers\HyperHDR\Client as HyperHDRClient;
use MediaLight\Drivers\HyperHDR\Driver as HyperHDRDriver;

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

        $this->registerGeneralVariables();
        $this->registerHyperHDRVariables();

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
            $this->SetValue('Online', false);
            $this->SetValue('Mode', 'INACTIVE');
            $this->getStatusManager()->resetHyperHDR();

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
                $status = $this->getHyperHDRDriver()->readStatus();
                $this->getStatusManager()->applyHyperHDR($status);
            } else {
                $this->getStatusManager()->resetHyperHDR();
            }

            $this->SetValue('Online', true);
            $this->SetValue('Mode', 'READY');
            $this->SetValue(
                'LastUpdate',
                date('d.m.Y H:i:s')
            );
            $this->SetValue('LastError', '');
            $this->SetStatus(self::STATUS_ACTIVE);
        } catch (Throwable $exception) {
            $this->SetValue('Online', false);
            $this->SetValue('Mode', 'ERROR');
            $this->SetValue('LastError', $exception->getMessage());
            $this->SetStatus(self::STATUS_HYPERHDR_OFFLINE);

            $this->getStatusManager()->resetHyperHDR();

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
        echo 'MediaLight Core mit PSR-4 erfolgreich getestet.';
    }

    public function TestHyperHDR(): void
    {
        try {
            $status = $this->getHyperHDRDriver()->readStatus();

            $this->getStatusManager()->applyHyperHDR($status);

            echo sprintf(
                'HyperHDR erreichbar: %s, %s, %.2f FPS, %d LEDs',
                $status->getHostname() !== ''
                    ? $status->getHostname()
                    : 'Hostname unbekannt',
                $status->getVideoMode() !== ''
                    ? $status->getVideoMode()
                    : 'Videomodus unbekannt',
                $status->getFps(),
                $status->getLedCount()
            );
        } catch (Throwable $exception) {
            $this->getStatusManager()->resetHyperHDR();
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

    public function WriteValue(
        string $ident,
        mixed $value
    ): void {
        $variableId = @$this->GetIDForIdent($ident);

        if ($variableId <= 0) {
            $this->getLogger()->warning(
                'Statusvariable nicht gefunden',
                ['ident' => $ident]
            );

            return;
        }

        $this->SetValue($ident, $value);
    }

    private function registerGeneralVariables(): void
    {
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
    }

    private function registerHyperHDRVariables(): void
    {
        $position = 100;

        $this->RegisterVariableBoolean(
            'HyperHDROnline',
            'HyperHDR online',
            '~Switch',
            $position += 10
        );

        $this->RegisterVariableString(
            'HyperHDRVersion',
            'HyperHDR-Version',
            '',
            $position += 10
        );

        $this->RegisterVariableString(
            'HyperHDRHostname',
            'HyperHDR-Hostname',
            '',
            $position += 10
        );

        $this->RegisterVariableInteger(
            'HyperHDRCurrentInstance',
            'Aktuelle HyperHDR-Instanz',
            '',
            $position += 10
        );

        $this->RegisterVariableString(
            'HyperHDRInstanceName',
            'HyperHDR-Instanzname',
            '',
            $position += 10
        );

        $this->RegisterVariableBoolean(
            'HyperHDRInstanceEnabled',
            'HyperHDR-Instanz aktiv',
            '~Switch',
            $position += 10
        );

        $this->RegisterVariableBoolean(
            'HyperHDRGrabberEnabled',
            'Videoaufnahme aktiv',
            '~Switch',
            $position += 10
        );

        $this->RegisterVariableString(
            'HyperHDRGrabberDevice',
            'Grabber-Gerät',
            '',
            $position += 10
        );

        $this->RegisterVariableString(
            'HyperHDRVideoMode',
            'Videomodus',
            '',
            $position += 10
        );

        $this->RegisterVariableBoolean(
            'HyperHDRLEDDeviceEnabled',
            'LED-Gerät aktiv',
            '~Switch',
            $position += 10
        );

        $this->RegisterVariableBoolean(
            'HyperHDRSmoothingEnabled',
            'Glättung aktiv',
            '~Switch',
            $position += 10
        );

        $this->RegisterVariableBoolean(
            'HyperHDRHDREnabled',
            'HDR aktiv',
            '~Switch',
            $position += 10
        );

        $this->RegisterVariableBoolean(
            'HyperHDRBlackBorderEnabled',
            'Schwarzbalkenerkennung aktiv',
            '~Switch',
            $position += 10
        );

        $this->RegisterVariableBoolean(
            'HyperHDRForwarderEnabled',
            'Weiterleitung aktiv',
            '~Switch',
            $position += 10
        );

        $this->RegisterVariableFloat(
            'HyperHDRFPS',
            'HyperHDR FPS',
            '',
            $position += 10
        );

        $this->RegisterVariableInteger(
            'HyperHDRVisiblePriority',
            'Sichtbare Priorität',
            '',
            $position += 10
        );

        $this->RegisterVariableString(
            'HyperHDRPriorityComponent',
            'Prioritätskomponente',
            '',
            $position += 10
        );

        $this->RegisterVariableString(
            'HyperHDRPriorityOwner',
            'Prioritätsquelle',
            '',
            $position += 10
        );

        $this->RegisterVariableInteger(
            'HyperHDREffectCount',
            'Anzahl Effekte',
            '',
            $position += 10
        );

        $this->RegisterVariableInteger(
            'HyperHDRLEDCount',
            'Anzahl HyperHDR-LEDs',
            '',
            $position += 10
        );

        $this->RegisterVariableBoolean(
            'HyperHDRWLEDConnected',
            'WLED mit HyperHDR verbunden',
            '~Switch',
            $position += 10
        );

        $this->RegisterVariableInteger(
            'HyperHDRSessionCount',
            'Verbundene HyperHDR-Sitzungen',
            '',
            $position += 10
        );

        $this->RegisterVariableString(
            'HyperHDRLastError',
            'Letzter HyperHDR-Fehler',
            '',
            $position += 10
        );
    }

    private function getStatusManager(): StatusManager
    {
        return new StatusManager(
            valueWriter: function (
                string $ident,
                mixed $value
            ): void {
                $this->WriteValue($ident, $value);
            },
            logger: $this->getLogger()
        );
    }

    private function getHyperHDRDriver(): HyperHDRDriver
    {
        $logger = $this->getLogger();

        $httpClient = new HttpClient(
            timeout: 5,
            logger: $logger
        );

        return new HyperHDRDriver(
            client: new HyperHDRClient(
                httpClient: $httpClient,
                logger: $logger,
                host: $this->ReadPropertyString('HyperHDRHost'),
                port: $this->ReadPropertyInteger('HyperHDRPort'),
                https: $this->ReadPropertyBoolean('HyperHDRHTTPS'),
                path: $this->ReadPropertyString('HyperHDRPath'),
                token: $this->ReadPropertyString('HyperHDRToken')
            ),
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
}