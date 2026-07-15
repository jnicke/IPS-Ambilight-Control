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
use MediaLight\Drivers\WLED\Client as WLEDClient;
use MediaLight\Drivers\WLED\Driver as WLEDDriver;
use MediaLight\Drivers\WLED\Mapper as WLEDMapper;
use MediaLight\Models\WLED\Controller as WLEDController;

Autoloader::register(__DIR__ . '/src');

class MediaLight extends IPSModule
{
    private const STATUS_ACTIVE = 102;
    private const STATUS_INACTIVE = 104;
    private const STATUS_HYPERHDR_OFFLINE = 201;
    private const STATUS_WLED_OFFLINE = 202;
    private const STATUS_MULTIPLE_OFFLINE = 203;

    public function Create(): void
    {
        parent::Create();

        $this->registerProperties();
        $this->registerGeneralVariables();
        $this->registerHyperHDRVariables();
        $this->registerWLEDVariables();

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
            $this->SetValue('LastError', '');

            $statusManager = $this->getStatusManager();
            $statusManager->resetHyperHDR();
            $statusManager->resetWLED();

            return;
        }

        $this->SetValue('Mode', 'READY');
        $this->SetValue('LastError', '');

        $this->Update();
    }

    public function Update(): void
    {
        if (!$this->getConfig()->isActive()) {
            return;
        }

        $errors = [];

        if ($this->ReadPropertyBoolean('HyperHDREnabled')) {
            try {
                $status = $this->getHyperHDRDriver()->readStatus();
                $this->getStatusManager()->applyHyperHDR($status);
            } catch (Throwable $exception) {
                $this->getStatusManager()->resetHyperHDR();
                $errors['HyperHDR'] = $exception->getMessage();

                $this->logException(
                    'HyperHDR-Aktualisierung fehlgeschlagen',
                    $exception
                );
            }
        } else {
            $this->getStatusManager()->resetHyperHDR();
        }

        if ($this->ReadPropertyBoolean('WLEDEnabled')) {
            try {
                $controller = $this->getWLEDDriver()->readController();
                $this->getStatusManager()->applyWLED($controller);
            } catch (Throwable $exception) {
                $this->getStatusManager()->resetWLED();
                $errors['WLED'] = $exception->getMessage();

                $this->logException(
                    'WLED-Aktualisierung fehlgeschlagen',
                    $exception
                );
            }
        } else {
            $this->getStatusManager()->resetWLED();
        }

        $this->SetValue(
            'LastUpdate',
            date('d.m.Y H:i:s')
        );

        if ($errors === []) {
            $this->SetValue('Online', true);
            $this->SetValue('Mode', 'READY');
            $this->SetValue('LastError', '');
            $this->SetStatus(self::STATUS_ACTIVE);

            return;
        }

        $this->SetValue('Online', false);
        $this->SetValue('Mode', 'ERROR');
        $this->SetValue(
            'LastError',
            $this->formatErrors($errors)
        );

        if (
            array_key_exists('HyperHDR', $errors)
            && array_key_exists('WLED', $errors)
        ) {
            $this->SetStatus(self::STATUS_MULTIPLE_OFFLINE);
        } elseif (array_key_exists('HyperHDR', $errors)) {
            $this->SetStatus(self::STATUS_HYPERHDR_OFFLINE);
        } else {
            $this->SetStatus(self::STATUS_WLED_OFFLINE);
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

    public function TestWLED(): void
    {
        try {
            $controller = $this->getWLEDDriver()->readController();

            $this->getStatusManager()->applyWLED($controller);

            echo $this->buildWLEDTestResult($controller);
        } catch (Throwable $exception) {
            $this->getStatusManager()->resetWLED();
            $this->SetValue('LastError', $exception->getMessage());

            echo 'WLED-Test fehlgeschlagen: '
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

    private function registerProperties(): void
    {
        $this->RegisterPropertyBoolean('Active', true);
        $this->RegisterPropertyInteger('UpdateInterval', 10);
        $this->RegisterPropertyBoolean('DebugEnabled', false);

        $this->RegisterPropertyBoolean('HyperHDREnabled', true);
        $this->RegisterPropertyString('HyperHDRHost', '');
        $this->RegisterPropertyInteger('HyperHDRPort', 8090);
        $this->RegisterPropertyBoolean('HyperHDRHTTPS', false);
        $this->RegisterPropertyString('HyperHDRPath', '/json-rpc');
        $this->RegisterPropertyString('HyperHDRToken', '');

        $this->RegisterPropertyBoolean('WLEDEnabled', true);
        $this->RegisterPropertyString('WLEDHost', '');
        $this->RegisterPropertyBoolean('WLEDHTTPS', false);
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

    private function registerWLEDVariables(): void
    {
        $position = 500;

        $definitions = [
            ['bool', 'WLEDOnline', 'WLED online'],
            ['string', 'WLEDName', 'WLED-Name'],
            ['string', 'WLEDFirmware', 'WLED-Firmware'],
            ['string', 'WLEDRelease', 'WLED-Release'],
            ['string', 'WLEDArchitecture', 'WLED-Architektur'],
            ['string', 'WLEDIPAddress', 'WLED-IP-Adresse'],
            ['string', 'WLEDMACAddress', 'WLED-MAC-Adresse'],
            ['int', 'WLEDLEDCount', 'WLED LED-Anzahl'],
            ['int', 'WLEDBusCount', 'WLED Bus-Anzahl'],
            ['bool', 'WLEDRGBW', 'WLED RGBW'],
            ['int', 'WLEDMaximumCurrent', 'WLED Stromlimit'],
            ['int', 'WLEDCurrentPower', 'WLED aktuelle Leistung'],
            ['int', 'WLEDFPS', 'WLED FPS'],
            ['int', 'WLEDEffectCount', 'WLED Effekte'],
            ['int', 'WLEDPaletteCount', 'WLED Paletten'],
            ['int', 'WLEDUptime', 'WLED Laufzeit'],
            ['int', 'WLEDFreeHeap', 'WLED freier Speicher'],
            ['int', 'WLEDRSSI', 'WLED RSSI'],
            ['int', 'WLEDSignalQuality', 'WLED Signalqualität'],
            ['string', 'WLEDLiveMode', 'WLED Live-Modus'],
            ['string', 'WLEDLiveSourceIP', 'WLED Live-Quelle'],
            ['bool', 'WLEDPower', 'WLED eingeschaltet'],
            ['int', 'WLEDBrightness', 'WLED Helligkeit'],
            ['bool', 'WLEDRealtime', 'WLED Realtime'],
            ['int', 'WLEDRealtimeMode', 'WLED Realtime-Override'],
            ['int', 'WLEDSegmentCount', 'WLED Segmente'],
            ['bool', 'WLEDUDPSend', 'WLED UDP senden'],
            ['bool', 'WLEDUDPReceive', 'WLED UDP empfangen']
        ];

        foreach ($definitions as [$type, $ident, $name]) {
            $position += 10;

            match ($type) {
                'bool' => $this->RegisterVariableBoolean(
                    $ident,
                    $name,
                    '~Switch',
                    $position
                ),
                'int' => $this->RegisterVariableInteger(
                    $ident,
                    $name,
                    '',
                    $position
                ),
                default => $this->RegisterVariableString(
                    $ident,
                    $name,
                    '',
                    $position
                )
            };
        }

        for ($busNumber = 1; $busNumber <= 4; $busNumber++) {
            $this->registerWLEDBusVariables(
                $busNumber,
                900 + ($busNumber * 200)
            );
        }
    }

    private function registerWLEDBusVariables(
        int $busNumber,
        int $position
    ): void {
        $prefix = 'WLEDBus' . $busNumber;
        $caption = 'WLED Bus ' . $busNumber . ' ';

        $this->RegisterVariableBoolean(
            $prefix . 'Available',
            $caption . 'verfügbar',
            '~Switch',
            $position += 10
        );

        $this->RegisterVariableInteger(
            $prefix . 'Start',
            $caption . 'Start',
            '',
            $position += 10
        );

        $this->RegisterVariableInteger(
            $prefix . 'Stop',
            $caption . 'Ende',
            '',
            $position += 10
        );

        $this->RegisterVariableInteger(
            $prefix . 'Length',
            $caption . 'LED-Anzahl',
            '',
            $position += 10
        );

        $this->RegisterVariableInteger(
            $prefix . 'GPIO',
            $caption . 'GPIO',
            '',
            $position += 10
        );

        $this->RegisterVariableString(
            $prefix . 'Pins',
            $caption . 'Pins',
            '',
            $position += 10
        );

        $this->RegisterVariableInteger(
            $prefix . 'Type',
            $caption . 'LED-Typ',
            '',
            $position += 10
        );

        $this->RegisterVariableInteger(
            $prefix . 'ColorOrder',
            $caption . 'Farbreihenfolge',
            '',
            $position += 10
        );

        $this->RegisterVariableBoolean(
            $prefix . 'Reversed',
            $caption . 'umgekehrt',
            '~Switch',
            $position += 10
        );

        $this->RegisterVariableInteger(
            $prefix . 'Skip',
            $caption . 'übersprungene LEDs',
            '',
            $position += 10
        );

        $this->RegisterVariableInteger(
            $prefix . 'MilliAmpsPerLED',
            $caption . 'mA je LED',
            '',
            $position += 10
        );

        $this->RegisterVariableInteger(
            $prefix . 'MaximumCurrent',
            $caption . 'Stromlimit',
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

        return new HyperHDRDriver(
            client: new HyperHDRClient(
                httpClient: new HttpClient(
                    timeout: 5,
                    logger: $logger
                ),
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

    private function getWLEDDriver(): WLEDDriver
    {
        $logger = $this->getLogger();

        return new WLEDDriver(
            client: new WLEDClient(
                httpClient: new HttpClient(
                    timeout: 5,
                    logger: $logger
                ),
                logger: $logger,
                host: $this->ReadPropertyString('WLEDHost'),
                https: $this->ReadPropertyBoolean('WLEDHTTPS')
            ),
            mapper: new WLEDMapper(),
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

    /**
     * @param array<string, string> $errors
     */
    private function formatErrors(array $errors): string
    {
        $parts = [];

        foreach ($errors as $device => $message) {
            $parts[] = $device . ': ' . $message;
        }

        return implode(' | ', $parts);
    }

    private function logException(
        string $context,
        Throwable $exception
    ): void {
        $this->getLogger()->error(
            $context,
            [
                'exception' => $exception::class,
                'message'   => $exception->getMessage(),
                'file'      => $exception->getFile(),
                'line'      => $exception->getLine()
            ]
        );
    }

    private function buildWLEDTestResult(
        WLEDController $controller
    ): string {
        $lines = [
            sprintf(
                'WLED erreichbar: %s, Firmware %s, %d LEDs, %d Busse',
                $controller->getName(),
                $controller->getFirmware(),
                $controller->getLedCount(),
                $controller->getBusCount()
            )
        ];

        foreach ($controller->getBuses() as $bus) {
            $lines[] = sprintf(
                'Bus %d: GPIO %d, Start %d, Länge %d',
                $bus->getNumber(),
                $bus->getPrimaryPin(),
                $bus->getStart(),
                $bus->getLength()
            );
        }

        return implode(PHP_EOL, $lines);
    }
}