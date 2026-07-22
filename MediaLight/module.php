<?php

declare(strict_types=1);

require_once __DIR__ . '/src/Core/Autoloader.php';

use MediaLight\Core\Autoloader;
use MediaLight\Core\Config;
use MediaLight\Core\HttpClient;
use MediaLight\Core\Logger;
use MediaLight\Core\StatusManager;
use MediaLight\Drivers\AppleTV\Client as AppleTVClient;
use MediaLight\Drivers\AppleTV\Driver as AppleTVDriver;
use MediaLight\Drivers\HyperHDR\Client as HyperHDRClient;
use MediaLight\Drivers\HyperHDR\Driver as HyperHDRDriver;
use MediaLight\Drivers\WLED\Client as WLEDClient;
use MediaLight\Drivers\WLED\Driver as WLEDDriver;
use MediaLight\Drivers\WLED\Mapper as WLEDMapper;
use MediaLight\Models\AppleTV\Status as AppleTVStatus;
use MediaLight\Models\HyperHDR\Status as HyperHDRStatus;
use MediaLight\Models\WLED\Controller as WLEDController;

Autoloader::register(__DIR__ . '/src');

class MediaLight extends IPSModule
{
    private const STATUS_ACTIVE = 102;
    private const STATUS_INACTIVE = 104;
    private const STATUS_HYPERHDR_OFFLINE = 201;
    private const STATUS_WLED_OFFLINE = 202;
    private const STATUS_MULTIPLE_OFFLINE = 203;
    private const STATUS_APPLETV_OFFLINE = 204;

    private const WEBHOOK_PATH = '/hook/medialight';

    private const MODE_OFF = 0;
    private const MODE_LIVE = 1;
    private const MODE_WARM_WHITE = 2;
    private const MODE_NIGHT = 3;
    private const MODE_CLEANING = 4;
    private const MODE_FIREPLACE = 5;
    private const MODE_RAINBOW = 6;

    private const WARM_WHITE_RGBW = [255, 160, 60, 200];
    private const WARM_WHITE_BRIGHTNESS = 153;
    private const NIGHT_BRIGHTNESS = 15;
    private const CLEANING_RGBW = [255, 255, 255, 255];

    /**
     * WLED-Effekt- und Paletten-IDs (Stand WLED 0.14/0.15,
     * bei Firmware-Wechsel ggf. anpassen).
     */
    private const FX_SOLID = 0;
    private const FX_RAINBOW = 9;
    private const FX_FIRE_2012 = 66;
    private const PALETTE_DEFAULT = 0;
    private const PALETTE_FIRE = 35;

    private const APP_MODE_NO_CHANGE = -1;

    private const BUS_SCENE_UNCHANGED = 0;
    private const BUS_SCENE_OFF = 1;
    private const BUS_SCENE_WARM_DIMMED = 2;
    private const BUS_SCENE_NEUTRAL_WHITE = 3;

    private const BUS_SCENE_WARM_BRIGHTNESS = 60;
    private const BUS_SCENE_NEUTRAL_RGBW = [255, 220, 180, 255];
    private const BUS_SCENE_NEUTRAL_BRIGHTNESS = 200;

    private const WLED_EFFECT_FALLBACK = [
        0 => "Solid", 1 => "Blink", 2 => "Breathe", 3 => "Wipe",
        4 => "Wipe Random", 5 => "Random Colors", 6 => "Sweep", 7 => "Dynamic",
        8 => "Colorloop", 9 => "Rainbow", 10 => "Scan", 11 => "Scan Dual",
        12 => "Fade", 13 => "Theater", 14 => "Theater Rainbow", 15 => "Running",
        16 => "Saw", 17 => "Twinkle", 18 => "Dissolve", 19 => "Dissolve Rnd",
        20 => "Sparkle", 21 => "Sparkle Dark", 22 => "Sparkle+", 23 => "Strobe",
        24 => "Strobe Rainbow", 25 => "Strobe Mega", 26 => "Blink Rainbow", 27 => "Android",
        28 => "Chase", 29 => "Chase Random", 30 => "Chase Rainbow", 31 => "Chase Flash",
        32 => "Chase Flash Rnd", 33 => "Rainbow Runner", 34 => "Colorful", 35 => "Traffic Light",
        36 => "Sweep Random", 37 => "Chase 2", 38 => "Aurora", 39 => "Stream",
        40 => "Scanner", 41 => "Lighthouse", 42 => "Fireworks", 43 => "Rain",
        44 => "Tetrix", 45 => "Fire Flicker", 46 => "Gradient", 47 => "Loading",
        48 => "Rolling Balls", 49 => "Fairy", 50 => "Two Dots", 51 => "Fairytwinkle",
        52 => "Running Dual", 53 => "Image", 54 => "Chase 3", 55 => "Tri Wipe",
        56 => "Tri Fade", 57 => "Lightning", 58 => "ICU", 59 => "Multi Comet",
        60 => "Scanner Dual", 61 => "Stream 2", 62 => "Oscillate", 63 => "Pride 2015",
        64 => "Juggle", 65 => "Palette", 66 => "Fire 2012", 67 => "Colorwaves",
        68 => "Bpm", 69 => "Fill Noise", 70 => "Noise 1", 71 => "Noise 2",
        72 => "Noise 3", 73 => "Noise 4", 74 => "Colortwinkles", 75 => "Lake",
        76 => "Meteor", 77 => "Copy Segment", 78 => "Railway", 79 => "Ripple",
        80 => "Twinklefox", 81 => "Twinklecat", 82 => "Halloween Eyes", 83 => "Solid Pattern",
        84 => "Solid Pattern Tri", 85 => "Spots", 86 => "Spots Fade", 87 => "Glitter",
        88 => "Candle", 89 => "Fireworks Starburst", 90 => "Fireworks 1D", 91 => "Bouncing Balls",
        92 => "Sinelon", 93 => "Sinelon Dual", 94 => "Sinelon Rainbow", 95 => "Popcorn",
        96 => "Drip", 97 => "Plasma", 98 => "Percent", 99 => "Ripple Rainbow",
        100 => "Heartbeat", 101 => "Pacifica", 102 => "Candle Multi", 103 => "Solid Glitter",
        104 => "Sunrise", 105 => "Phased", 106 => "Twinkleup", 107 => "Noise Pal",
        108 => "Sine", 109 => "Phased Noise", 110 => "Flow", 111 => "Chunchun",
        112 => "Dancing Shadows", 113 => "Washing Machine", 114 => "Rotozoomer", 115 => "Blends",
        116 => "TV Simulator", 117 => "Dynamic Smooth", 118 => "Spaceships", 119 => "Crazy Bees",
        120 => "Ghost Rider", 121 => "Blobs", 122 => "Scrolling Text", 123 => "Drift Rose",
        124 => "Distortion Waves", 125 => "Soap", 126 => "Octopus", 127 => "Waving Cell",
        128 => "Pixels", 129 => "Pixelwave", 130 => "Juggles", 131 => "Matripix",
        132 => "Gravimeter", 133 => "Plasmoid", 134 => "Puddles", 135 => "Midnoise",
        136 => "Noisemeter", 137 => "Freqwave", 138 => "Freqmatrix", 139 => "GEQ",
        140 => "Waterfall", 141 => "Freqpixels", 143 => "Noisefire", 144 => "Puddlepeak",
        145 => "Noisemove", 146 => "Noise2D", 147 => "Perlin Move", 148 => "Ripple Peak",
        149 => "Firenoise", 150 => "Squared Swirl", 151 => "PacMan", 152 => "DNA",
        153 => "Matrix", 154 => "Metaballs", 155 => "Freqmap", 156 => "Gravcenter",
        157 => "Gravcentric", 158 => "Gravfreq", 159 => "DJ Light", 160 => "Funky Plank",
        161 => "Shimmer", 162 => "Pulser", 163 => "Blurz", 164 => "Drift",
        165 => "Waverly", 166 => "Sun Radiation", 167 => "Colored Bursts", 168 => "Julia",
        172 => "Game Of Life", 173 => "Tartan", 174 => "Polar Lights", 175 => "Swirl",
        176 => "Lissajous", 177 => "Frizzles", 178 => "Plasma Ball", 179 => "Flow Stripe",
        180 => "Hiphotic", 181 => "Sindots", 182 => "DNA Spiral", 183 => "Black Hole",
        184 => "Wavesins", 185 => "Rocktaves", 186 => "Akemi", 187 => "PS Volcano",
        188 => "PS Fire", 189 => "PS Fireworks", 190 => "PS Vortex", 191 => "PS Fuzzy Noise",
        192 => "PS Ballpit", 193 => "PS Box", 194 => "PS Attractor", 195 => "PS Impact",
        196 => "PS Waterfall", 197 => "PS Spray", 198 => "PS GEQ 2D", 199 => "PS GEQ Nova",
        200 => "PS Ghost Rider", 201 => "PS Blobs", 202 => "PS DripDrop", 203 => "PS Pinball",
        204 => "PS Dancing Shadows", 205 => "PS Fireworks 1D", 206 => "PS Sparkler", 207 => "PS Hourglass",
        208 => "PS Spray 1D", 209 => "PS 1D Balance", 210 => "PS Chase", 211 => "PS Starburst",
        212 => "PS GEQ 1D", 213 => "PS Fire 1D", 214 => "PS Sonic Stream", 215 => "PS Sonic Boom",
        216 => "PS Springy", 217 => "PS Galaxy", 218 => "Color Clouds", 219 => "Slow Transition",
    ];

    public function Create(): void
    {
        parent::Create();

        $this->registerAmbilightModeProfile();

        $this->registerWLEDEffectProfile();

        $this->registerProperties();
        $this->registerGeneralVariables();
        $this->registerHyperHDRVariables();
        $this->registerWLEDVariables();
        $this->registerWLEDControlVariables();
        $this->registerAppleTVVariables();

        $this->RegisterAttributeInteger(
            'PreviousAmbilightMode',
            self::MODE_OFF
        );

        $this->RegisterAttributeString(
            'CleaningSnapshot',
            ''
        );

        $this->RegisterAttributeString(
            'LastAppleTVState',
            ''
        );

        $this->RegisterAttributeString(
            'LastAppleTVAppId',
            ''
        );

        $this->RegisterMessage(0, IPS_KERNELSTARTED);

        $this->RegisterTimer(
            'UpdateTimer',
            0,
            'AMBI_Update($_IPS["TARGET"]);'
        );
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        if (IPS_GetKernelRunlevel() === KR_READY) {
            $this->registerHook(self::WEBHOOK_PATH);
        }

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

        $this->synchronizeWLEDEffectProfile();

        $this->Update();
    }

    public function Update(): void
    {
        if (!$this->getConfig()->isActive()) {
            return;
        }

        $errors = [];
        $status = null;
        $controller = null;

        if ($this->ReadPropertyBoolean('HyperHDREnabled')) {
            try {
                $status = $this->getHyperHDRDriver()->readStatus();
                $this->getStatusManager()->applyHyperHDR($status);
            } catch (Throwable $exception) {
                $status = null;

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
                $controller = null;

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

        $this->updateLayoutConsistency($controller, $status);

        if ($this->ReadPropertyBoolean('AppleTVEnabled')) {
            try {
                $appleTV = $this->getAppleTVDriver()->readStatus();

                $this->applyAppleTVStatus($appleTV);
                $this->applyAppleTVAutomation($appleTV);
            } catch (Throwable $exception) {
                $this->resetAppleTVStatus();
                $errors['AppleTV'] = $exception->getMessage();

                $this->logException(
                    'Apple-TV-Aktualisierung fehlgeschlagen',
                    $exception
                );
            }
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

        if (count($errors) >= 2) {
            $this->SetStatus(self::STATUS_MULTIPLE_OFFLINE);
        } elseif (array_key_exists('HyperHDR', $errors)) {
            $this->SetStatus(self::STATUS_HYPERHDR_OFFLINE);
        } elseif (array_key_exists('WLED', $errors)) {
            $this->SetStatus(self::STATUS_WLED_OFFLINE);
        } else {
            $this->SetStatus(self::STATUS_APPLETV_OFFLINE);
        }
    }

    public function MessageSink(
        $TimeStamp,
        $SenderID,
        $Message,
        $Data
    ): void {
        if ($Message === IPS_KERNELSTARTED) {
            $this->registerHook(self::WEBHOOK_PATH);
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
            $this->SetValue('LastActionError', $exception->getMessage());

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
            $this->SetValue('LastActionError', $exception->getMessage());

            echo 'WLED-Test fehlgeschlagen: '
                . $exception->getMessage();
        }
    }

    public function TestAppleTV(): void
    {
        try {
            $status = $this->getAppleTVDriver()->readStatus();

            $this->applyAppleTVStatus($status);

            echo sprintf(
                'Apple-TV-Bridge erreichbar. Apple TV %s, Status: %s, App: %s',
                $status->isOnline() ? 'online' : 'offline',
                $status->getState() !== ''
                    ? $status->getState()
                    : 'unbekannt',
                $status->getApp() !== ''
                    ? $status->getApp()
                    : 'keine'
            );
        } catch (Throwable $exception) {
            $this->resetAppleTVStatus();
            $this->SetValue(
                'LastActionError',
                $exception->getMessage()
            );

            echo 'Apple-TV-Test fehlgeschlagen: '
                . $exception->getMessage();
        }
    }

    public function SynchronizeWLEDSegments(): void
    {
        echo $this->runSegmentSync();
    }

    private function handleSyncSegmentsAction(): void
    {
        $this->SetValue('SyncSegments', true);

        try {
            $this->runSegmentSync();
        } finally {
            $this->SetValue('SyncSegments', false);
        }
    }

    private function runSegmentSync(): string
    {
        try {
            $driver = $this->getWLEDDriver();
            $driver->synchronizeSegments();

            usleep(300000);

            $controller = $driver->readController();
            $this->getStatusManager()->applyWLED($controller);

            $this->updateLayoutConsistency($controller, null);

            $this->SetValue('LastActionError', '');

            return sprintf(
                'WLED-Segmente erfolgreich synchronisiert. '
                . '%d Busse und %d Segmente erkannt.',
                $controller->getBusCount(),
                count($controller->getState()->getSegments())
            );
        } catch (Throwable $exception) {
            $this->SetValue(
                'LastActionError',
                $exception->getMessage()
            );

            $this->logException(
                'WLED-Segmentsynchronisierung fehlgeschlagen',
                $exception
            );

            return 'WLED-Segmentsynchronisierung fehlgeschlagen: '
                . $exception->getMessage();
        }
    }

    public function TestWLEDBus(int $busNumber): void
    {
        try {
            $this->assertControllableBus($busNumber);

            $driver = $this->getWLEDDriver();

            $this->assertWLEDSegmentExists(
                $driver,
                $busNumber
            );

            $driver->setBusRgbw(
                busNumber: $busNumber,
                red: 255,
                green: 120,
                blue: 20,
                white: 0,
                brightness: 64,
                transition: 7
            );

            usleep(200000);

            $controller = $driver->readController();
            $this->getStatusManager()->applyWLED($controller);

            echo sprintf(
                'WLED Bus %d wurde auf warmes Orange mit 25 %% Helligkeit gesetzt.',
                $busNumber
            );
        } catch (Throwable $exception) {
            $this->SetValue(
                'LastActionError',
                $exception->getMessage()
            );

            $this->logException(
                sprintf(
                    'Test von WLED Bus %d fehlgeschlagen',
                    $busNumber
                ),
                $exception
            );

            echo sprintf(
                'Test von WLED Bus %d fehlgeschlagen: %s',
                $busNumber,
                $exception->getMessage()
            );
        }
    }

    public function RequestAction($Ident, $Value)
    {
        $ident = (string) $Ident;
        $value = $Value;

        if (preg_match('/^Bus([1-4])FollowMode$/', $ident, $matches)) {
            $this->handleBusFollowAction(
                (int) $matches[1],
                (bool) $value
            );

            return;
        }

        if ($ident === 'AmbilightMode') {
            $this->handleAmbilightModeAction((int) $value);

            return;
        }

        if ($ident === 'AppleTVAutomation') {
            $this->SetValue('AppleTVAutomation', (bool) $value);

            return;
        }

        if (
            $ident === 'HyperHDRLEDDeviceEnabled'
            || $ident === 'HyperHDRGrabberEnabled'
        ) {
            $this->handleHyperHDRComponentAction(
                $ident,
                (bool) $value
            );

            return;
        }

        if ($ident === 'SyncSegments') {
            $this->handleSyncSegmentsAction();

            return;
        }

        if (
            $ident === 'WLEDPower'
            || $ident === 'WLEDBrightness'
        ) {
            $this->handleWLEDMasterAction($ident, $value);

            return;
        }

        if (
            preg_match(
                '/^WLEDBus([1-4])(Power|Brightness|Color|White|Effect)$/',
                $ident,
                $matches
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Unbekannte Aktion: ' . $ident
            );
        }

        $busNumber = (int) $matches[1];
        $property = (string) $matches[2];

        try {
            $driver = $this->getWLEDDriver();

            $this->assertWLEDSegmentExists(
                $driver,
                $busNumber
            );

            switch ($property) {
                case 'Power':
                    $driver->setBusPower(
                        busNumber: $busNumber,
                        power: (bool) $value,
                        transition: 7
                    );
                    break;

                case 'Brightness':
                    $driver->setBusBrightness(
                        busNumber: $busNumber,
                        brightness: (int) $value,
                        transition: 7
                    );
                    break;

                case 'Color':
                    $this->setWLEDBusColor(
                        driver: $driver,
                        busNumber: $busNumber,
                        color: (int) $value,
                        white: $this->readIntegerValue(
                            'WLEDBus' . $busNumber . 'White'
                        ),
                        brightness: $this->readIntegerValue(
                            'WLEDBus' . $busNumber . 'Brightness'
                        )
                    );
                    break;

                case 'White':
                    $this->setWLEDBusColor(
                        driver: $driver,
                        busNumber: $busNumber,
                        color: $this->readIntegerValue(
                            'WLEDBus' . $busNumber . 'Color'
                        ),
                        white: (int) $value,
                        brightness: $this->readIntegerValue(
                            'WLEDBus' . $busNumber . 'Brightness'
                        )
                    );
                    break;

                case 'Effect':
                    $driver->setBusEffect(
                        busNumber: $busNumber,
                        effect: (int) $value,
                        speed: 128,
                        intensity: 128,
                        palette: 0,
                        brightness: max(
                            1,
                            $this->readIntegerValue(
                                'WLEDBus'
                                . $busNumber
                                . 'Brightness'
                            )
                        ),
                        transition: 7
                    );
                    break;
            }

            usleep(150000);

            $controller = $driver->readController();
            $this->getStatusManager()->applyWLED($controller);

            $this->SetValue('LastActionError', '');
        } catch (Throwable $exception) {
            $this->SetValue(
                'LastActionError',
                $exception->getMessage()
            );

            $this->logException(
                'WLED-Aktion fehlgeschlagen',
                $exception
            );

            throw $exception;
        }
    }

    private function handleWLEDMasterAction(
        string $ident,
        $value
    ): void {
        try {
            $driver = $this->getWLEDDriver();

            if ($ident === 'WLEDPower') {
                $driver->setMasterPower(
                    power: (bool) $value,
                    transition: 7
                );
            } else {
                $driver->setMasterBrightness(
                    brightness: (int) $value,
                    transition: 7
                );
            }

            usleep(150000);

            $controller = $driver->readController();
            $this->getStatusManager()->applyWLED($controller);

            $this->SetValue('LastActionError', '');
        } catch (Throwable $exception) {
            $this->SetValue(
                'LastActionError',
                $exception->getMessage()
            );

            $this->logException(
                'WLED-Aktion fehlgeschlagen',
                $exception
            );

            throw $exception;
        }
    }

    /**
     * Legt das Auswahlprofil fuer die WLED-Effekte an. Beim Anlegen wird die
     * eingebaute Fallback-Liste verwendet, da in Create() noch keine
     * Controller-Verbindung besteht. refreshWLEDEffectProfile() aktualisiert
     * die Liste spaeter passend zur installierten WLED-Version.
     */
    /**
     * Holt die Effektliste vom Controller und aktualisiert das Auswahlprofil.
     * Schlaegt der Abruf fehl (Controller offline, WLED deaktiviert), bleibt
     * die zuletzt gueltige Liste bestehen.
     */
    private function synchronizeWLEDEffectProfile(): void
    {
        if (!$this->ReadPropertyBoolean('WLEDEnabled')) {
            return;
        }

        try {
            $this->refreshWLEDEffectProfile(
                $this->getWLEDDriver()->readEffects()
            );
        } catch (Throwable $exception) {
            $this->logException(
                'WLED-Effektliste konnte nicht geladen werden',
                $exception
            );
        }
    }

    private function registerWLEDEffectProfile(): void
    {
        $this->writeWLEDEffectProfile(self::WLED_EFFECT_FALLBACK);
    }

    /**
     * Aktualisiert das Effektprofil aus der Effektliste des Controllers.
     *
     * @param array<int|string, string> $effects
     */
    private function refreshWLEDEffectProfile(array $effects): void
    {
        if ($effects === []) {
            return;
        }

        $named = [];

        foreach ($effects as $index => $name) {
            $named[(int) $index] = (string) $name;
        }

        $this->writeWLEDEffectProfile($named);
    }

    /**
     * @param array<int, string> $effects
     */
    private function writeWLEDEffectProfile(array $effects): void
    {
        if ($effects === []) {
            return;
        }

        $profile = 'AMBI.Effect';

        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile(
                $profile,
                VARIABLETYPE_INTEGER
            );
        }

        IPS_SetVariableProfileIcon($profile, 'Bulb');

        IPS_SetVariableProfileValues(
            $profile,
            0,
            max(array_keys($effects)),
            1
        );

        foreach (
            IPS_GetVariableProfile($profile)['Associations'] as $association
        ) {
            IPS_SetVariableProfileAssociation(
                $profile,
                $association['Value'],
                '',
                '',
                -1
            );
        }

        foreach ($effects as $index => $name) {
            $label = trim($name);

            if ($label === '' || strtoupper($label) === 'RSVD') {
                continue;
            }

            IPS_SetVariableProfileAssociation(
                $profile,
                $index,
                $label,
                '',
                -1
            );
        }
    }

    private function registerAmbilightModeProfile(): void
    {
        $profile = 'AMBI.Mode';

        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile(
                $profile,
                VARIABLETYPE_INTEGER
            );
        }

        IPS_SetVariableProfileValues($profile, 0, 6, 1);

        $associations = [
            [self::MODE_OFF, 'Aus', 'Power', -1],
            [self::MODE_LIVE, 'Live', 'TV', 0x2196F3],
            [self::MODE_WARM_WHITE, 'Warmweiß', 'Bulb', 0xFFC107],
            [self::MODE_NIGHT, 'Nacht', 'Moon', 0x3F51B5],
            [self::MODE_CLEANING, 'Reinigung', 'Drops', 0x00BCD4],
            [self::MODE_FIREPLACE, 'Kaminfeuer', 'Flame', 0xFF5722],
            [self::MODE_RAINBOW, 'Regenbogen', 'Paintbrush', 0x9C27B0]
        ];

        foreach ($associations as [$value, $caption, $icon, $color]) {
            IPS_SetVariableProfileAssociation(
                $profile,
                $value,
                $caption,
                $icon,
                $color
            );
        }
    }

    private function handleAmbilightModeAction(int $mode): void
    {
        try {
            $this->applyAmbilightMode($mode);

            $this->SetValue('LastActionError', '');
        } catch (Throwable $exception) {
            $this->SetValue(
                'LastActionError',
                $exception->getMessage()
            );

            $this->logException(
                sprintf(
                    'Ambilight-Modus %d konnte nicht aktiviert werden',
                    $mode
                ),
                $exception
            );

            throw $exception;
        }
    }

    private function applyAmbilightMode(int $mode): void
    {
        $previousMode = $this->ReadAttributeInteger(
            'PreviousAmbilightMode'
        );

        $hyperHDR = $this->getHyperHDRDriver();
        $wled = $this->getWLEDDriver();

        if (
            $previousMode === self::MODE_CLEANING
            && $mode !== self::MODE_CLEANING
        ) {
            $this->restoreControlledBuses($wled);
        }

        $buses = $this->getFollowingBuses();

        $this->applyModeToBuses($mode, $buses, $hyperHDR, $wled);

        $this->WriteAttributeInteger(
            'PreviousAmbilightMode',
            $mode
        );

        $this->SetValue('AmbilightMode', $mode);

        usleep(300000);

        $controller = $wled->readController();
        $this->getStatusManager()->applyWLED($controller);

        $status = $hyperHDR->readStatus();
        $this->getStatusManager()->applyHyperHDR($status);
    }

    /**
     * @return list<int>
     */
    private function getFollowingBuses(): array
    {
        $buses = [];

        for ($busNumber = 1; $busNumber <= 4; $busNumber++) {
            if ($this->GetValue('Bus' . $busNumber . 'FollowMode')) {
                $buses[] = $busNumber;
            }
        }

        return $buses;
    }

    /**
     * @param list<int> $buses
     */
    private function applyModeToBuses(
        int $mode,
        array $buses,
        HyperHDRDriver $hyperHDR,
        WLEDDriver $wled
    ): void {
        if ($mode === self::MODE_LIVE) {
            $this->stopEffectOnIdleBuses($buses, $wled);

            $hyperHDR->setComponentState(
                component: 'VIDEOGRABBER',
                enabled: true
            );

            usleep(300000);

            $hyperHDR->setComponentState(
                component: 'LEDDEVICE',
                enabled: true
            );

            return;
        }

        $hyperHDR->setComponentState(
            component: 'VIDEOGRABBER',
            enabled: false
        );

        $this->waitForWLEDRealtimeEnd($wled);

        $this->unfreezeAllSegments($wled);

        if ($mode === self::MODE_CLEANING) {
            $controller = $wled->readController();

            $this->captureCleaningSnapshot($controller);

            $transaction = $wled->beginTransaction();

            [$red, $green, $blue, $white] = self::CLEANING_RGBW;

            for (
                $busNumber = 1;
                $busNumber <= $controller->getBusCount();
                $busNumber++
            ) {
                $transaction
                    ->bus($busNumber)
                    ->power(true)
                    ->brightness(255)
                    ->rgbw($red, $green, $blue, $white)
                    ->effect(self::FX_SOLID);
            }

            $transaction->commit(transition: 7);

            return;
        }

        if ($buses === []) {
            return;
        }

        foreach ($buses as $busNumber) {
            $this->applyModeToSingleBus($mode, $busNumber, $wled);
        }
    }

    /**
     * Stoppt laufende Effekte (Kaminfeuer/Regenbogen) auf den Deko-Bussen
     * 2-4, die NICHT dem Modus folgen, damit sie danach frei einzeln
     * bedienbar sind. Farbe und Helligkeit bleiben erhalten.
     *
     * @param list<int> $followingBuses
     */
    private function stopEffectOnIdleBuses(
        array $followingBuses,
        WLEDDriver $wled
    ): void {
        $transaction = $wled->beginTransaction();
        $changed = false;

        for ($busNumber = 2; $busNumber <= 4; $busNumber++) {
            if (in_array($busNumber, $followingBuses, true)) {
                continue;
            }

            $transaction
                ->bus($busNumber)
                ->effect(self::FX_SOLID);

            $changed = true;
        }

        if ($changed) {
            $transaction->commit(transition: 7);
        }
    }

    /**
     * Hebt bei ALLEN WLED-Segmenten den Realtime-Freeze auf, damit sie nach
     * dem Verlassen des Live-Modus wieder aus ihrem eigenen Zustand rendern.
     * Ohne dies bleibt ein von HyperHDR eingefrorenes Segment dunkel, obwohl
     * Farbe/Helligkeit/Power korrekt gesetzt sind.
     */
    private function unfreezeAllSegments(WLEDDriver $wled): void
    {
        $transaction = $wled->beginTransaction();
        $changed = false;

        for ($busNumber = 1; $busNumber <= 4; $busNumber++) {
            try {
                $transaction
                    ->bus($busNumber)
                    ->freeze(false);

                $changed = true;
            } catch (\Throwable $exception) {
                $this->getLogger()->warning(
                    'Segment konnte nicht ent-freezet werden',
                    [
                        'busNumber' => $busNumber,
                        'message'   => $exception->getMessage()
                    ]
                );
            }
        }

        if ($changed) {
            $transaction->commit();
        }
    }

    private function applyModeToSingleBus(
        int $mode,
        int $busNumber,
        WLEDDriver $wled
    ): void {
        switch ($mode) {
            case self::MODE_OFF:
                $wled->setBusPower(
                    busNumber: $busNumber,
                    power: false
                );
                break;

            case self::MODE_WARM_WHITE:
            case self::MODE_NIGHT:
                [$red, $green, $blue, $white] = self::WARM_WHITE_RGBW;

                $wled->setBusRgbw(
                    busNumber: $busNumber,
                    red: $red,
                    green: $green,
                    blue: $blue,
                    white: $white,
                    brightness: $mode === self::MODE_NIGHT
                        ? self::NIGHT_BRIGHTNESS
                        : self::WARM_WHITE_BRIGHTNESS
                );
                break;

            case self::MODE_FIREPLACE:
                $wled->setBusEffect(
                    busNumber: $busNumber,
                    effect: self::FX_FIRE_2012,
                    speed: 120,
                    intensity: 128,
                    palette: self::PALETTE_FIRE,
                    brightness: 140
                );
                break;

            case self::MODE_RAINBOW:
                $wled->setBusEffect(
                    busNumber: $busNumber,
                    effect: self::FX_RAINBOW,
                    speed: 60,
                    intensity: 128,
                    palette: self::PALETTE_DEFAULT,
                    brightness: 128
                );
                break;

            default:
                throw new InvalidArgumentException(
                    'Unbekannter Ambilight-Modus: ' . $mode
                );
        }
    }

    private function handleBusFollowAction(
        int $busNumber,
        bool $enabled
    ): void {
        try {
            $this->SetValue('Bus' . $busNumber . 'FollowMode', $enabled);

            $wled = $this->getWLEDDriver();
            $currentMode = $this->GetValue('AmbilightMode');

            if ($enabled) {
                if (
                    $currentMode !== self::MODE_LIVE
                    && $currentMode !== self::MODE_CLEANING
                ) {
                    $this->applyModeToSingleBus(
                        $currentMode,
                        $busNumber,
                        $wled
                    );
                }
            } else {
                $wled->setBusPower(
                    busNumber: $busNumber,
                    power: false
                );
            }

            usleep(200000);

            $controller = $wled->readController();
            $this->getStatusManager()->applyWLED($controller);

            $this->SetValue('LastActionError', '');
        } catch (Throwable $exception) {
            $this->SetValue(
                'LastActionError',
                $exception->getMessage()
            );

            $this->logException(
                sprintf(
                    'Bus %d Folge-Schalter konnte nicht angewendet werden',
                    $busNumber
                ),
                $exception
            );

            throw $exception;
        }
    }

    private function waitForWLEDRealtimeEnd(
        WLEDDriver $driver,
        int $timeoutSeconds = 6
    ): bool {
        $deadline = microtime(true) + $timeoutSeconds;

        do {
            $controller = $driver->readController();

            if (!$controller->getState()->isRealtime()) {
                return true;
            }

            usleep(500000);
        } while (microtime(true) < $deadline);

        $this->getLogger()->warning(
            'WLED hat den Realtime-Modus nicht rechtzeitig verlassen',
            ['timeoutSeconds' => $timeoutSeconds]
        );

        return false;
    }

    /**
     * Sichert den Zustand der Segmente von Bus 2–4, bevor der
     * Reinigungsmodus sie mit Vollweiß übersteuert. Die Steuervariablen
     * taugen dafür nicht als Quelle, weil der StatusManager sie laufend
     * mit dem Ist-Zustand synchronisiert.
     */
    private function captureCleaningSnapshot(
        WLEDController $controller
    ): void {
        $snapshot = [];

        foreach (
            $controller->getState()->getSegments() as $segment
        ) {
            $segmentId = $segment->getId();

            if ($segmentId === 0) {
                continue;
            }

            $snapshot[$segmentId] = [
                'on'  => $segment->isOn(),
                'bri' => $segment->getBrightness(),
                'col' => $segment->getPrimaryColor(),
                'fx'  => $segment->getEffect()
            ];
        }

        $this->WriteAttributeString(
            'CleaningSnapshot',
            json_encode($snapshot)
        );
    }

    private function restoreControlledBuses(
        WLEDDriver $driver
    ): void {
        $raw = $this->ReadAttributeString('CleaningSnapshot');
        $snapshot = json_decode($raw, true);

        if (!is_array($snapshot) || $snapshot === []) {
            return;
        }

        $transaction = $driver->beginTransaction();
        $hasChanges = false;

        foreach ($snapshot as $segmentId => $state) {
            $busNumber = (int) $segmentId + 1;

            if ($busNumber < 2 || $busNumber > 4) {
                continue;
            }

            $update = $transaction->bus($busNumber);
            $hasChanges = true;

            if (!(bool) ($state['on'] ?? true)) {
                $update->power(false);

                continue;
            }

            $color = is_array($state['col'] ?? null)
                ? $state['col']
                : [0, 0, 0, 0];

            $update
                ->power(true)
                ->brightness(
                    max(1, min(255, (int) ($state['bri'] ?? 255)))
                )
                ->rgbw(
                    (int) ($color[0] ?? 0),
                    (int) ($color[1] ?? 0),
                    (int) ($color[2] ?? 0),
                    (int) ($color[3] ?? 0)
                )
                ->effect(
                    (int) ($state['fx'] ?? self::FX_SOLID)
                );
        }

        if ($hasChanges) {
            $transaction->commit(transition: 7);
        }

        $this->WriteAttributeString('CleaningSnapshot', '');
    }

    private function registerAppleTVVariables(): void
    {
        $position = 3000;

        $this->RegisterVariableBoolean(
            'AppleTVOnline',
            'Apple TV online',
            '~Switch',
            $position += 10
        );

        $this->RegisterVariableString(
            'AppleTVPower',
            'Apple TV Betriebszustand',
            '',
            $position += 10
        );

        $this->RegisterVariableString(
            'AppleTVState',
            'Apple TV Wiedergabestatus',
            '',
            $position += 10
        );

        $this->RegisterVariableString(
            'AppleTVApp',
            'Apple TV App',
            '',
            $position += 10
        );

        $this->RegisterVariableString(
            'AppleTVTitle',
            'Apple TV Titel',
            '',
            $position += 10
        );

        $this->RegisterVariableString(
            'AppleTVLastEvent',
            'Apple TV letztes Ereignis',
            '',
            $position += 10
        );

        $this->RegisterVariableBoolean(
            'AppleTVAutomation',
            'Apple-TV-Automatik',
            '~Switch',
            $position += 10
        );

        $this->EnableAction(
            'AppleTVAutomation'
        );
    }

    private function registerHook(string $hook): void
    {
        $webhookInstances = IPS_GetInstanceListByModuleID(
            '{015A6EB8-D6E5-4B93-B496-0D3F77AE9FE1}'
        );

        if ($webhookInstances === []) {
            return;
        }

        $webhookId = $webhookInstances[0];

        $hooks = json_decode(
            IPS_GetProperty($webhookId, 'Hooks'),
            true
        );

        if (!is_array($hooks)) {
            $hooks = [];
        }

        foreach ($hooks as $index => $entry) {
            if (($entry['Hook'] ?? '') !== $hook) {
                continue;
            }

            if ((int) ($entry['TargetID'] ?? 0) === $this->InstanceID) {
                return;
            }

            $hooks[$index]['TargetID'] = $this->InstanceID;

            IPS_SetProperty(
                $webhookId,
                'Hooks',
                json_encode($hooks)
            );
            IPS_ApplyChanges($webhookId);

            return;
        }

        $hooks[] = [
            'Hook'     => $hook,
            'TargetID' => $this->InstanceID
        ];

        IPS_SetProperty(
            $webhookId,
            'Hooks',
            json_encode($hooks)
        );
        IPS_ApplyChanges($webhookId);
    }

    protected function ProcessHookData()
    {
        $body = file_get_contents('php://input');

        $payload = json_decode((string) $body, true);

        if (!is_array($payload)) {
            http_response_code(400);

            echo json_encode(['result' => 'invalid payload']);

            return;
        }

        try {
            $status = $this->getAppleTVDriver()->mapStatus($payload);

            $this->applyAppleTVStatus($status);
            $this->applyAppleTVAutomation($status);

            echo json_encode(['result' => 'ok']);
        } catch (Throwable $exception) {
            $this->logException(
                'Apple-TV-Ereignis konnte nicht verarbeitet werden',
                $exception
            );

            http_response_code(500);

            echo json_encode(['result' => 'error']);
        }
    }

    private function applyAppleTVStatus(
        AppleTVStatus $status
    ): void {
        $this->SetValue('AppleTVOnline', $status->isOnline());
        $this->SetValue('AppleTVPower', $status->getPower());
        $this->SetValue('AppleTVState', $status->getState());
        $this->SetValue('AppleTVApp', $status->getApp());
        $this->SetValue('AppleTVTitle', $status->getTitle());
        $this->SetValue(
            'AppleTVLastEvent',
            $status->getLastEvent() > 0
                ? date('d.m.Y H:i:s', $status->getLastEvent())
                : ''
        );
    }

    private function resetAppleTVStatus(): void
    {
        $this->SetValue('AppleTVOnline', false);
        $this->SetValue('AppleTVPower', 'unknown');
        $this->SetValue('AppleTVState', 'offline');
    }

    /**
     * Wendet die App-Regeln abhängig vom Apple-TV-Zustand an.
     *
     * Reagiert ausschließlich auf Wechsel von Zustand oder App, damit
     * manuell gewählte Modi nicht bei jedem Ereignis übersteuert
     * werden. Bei Bridge-Ausfall (online = false) wird der letzte
     * Modus bewusst gehalten.
     */
    private function applyAppleTVAutomation(
        AppleTVStatus $status
    ): void {
        if (!(bool) $this->GetValue('AppleTVAutomation')) {
            return;
        }

        if (!$status->isOnline()) {
            return;
        }

        $state = $status->getState();
        $appId = $status->getAppId();

        $lastState = $this->ReadAttributeString('LastAppleTVState');
        $lastAppId = $this->ReadAttributeString('LastAppleTVAppId');

        if ($state === $lastState && $appId === $lastAppId) {
            return;
        }

        $this->WriteAttributeString('LastAppleTVState', $state);
        $this->WriteAttributeString('LastAppleTVAppId', $appId);

        if ($state === 'standby') {
            $this->runAppleTVScene(
                targetMode: self::MODE_OFF,
                busScene: $this->ReadPropertyInteger(
                    'AppleTVStandbyBusScene'
                ),
                state: $state,
                appId: $appId
            );

            return;
        }

        if ($state !== 'playing' && $state !== 'paused') {
            return;
        }

        $rule = $this->findAppleTVAppRule($appId);

        $targetMode = (int) (
            $state === 'playing'
                ? ($rule['PlayingMode'] ?? self::APP_MODE_NO_CHANGE)
                : ($rule['PausedMode'] ?? self::APP_MODE_NO_CHANGE)
        );

        $this->runAppleTVScene(
            targetMode: $targetMode,
            busScene: (int) ($rule['BusScene'] ?? self::BUS_SCENE_UNCHANGED),
            state: $state,
            appId: $appId
        );
    }

    /**
     * Sucht die passende App-Regel: exakter Treffer auf die App-ID
     * vor einer Fallback-Regel (leere App-ID). Ohne konfigurierte
     * Regeln gilt das bisherige Standardverhalten.
     *
     * @return array<string, mixed>
     */
    private function findAppleTVAppRule(string $appId): array
    {
        $rules = json_decode(
            $this->ReadPropertyString('AppleTVAppRules'),
            true
        );

        $fallback = null;

        if (is_array($rules)) {
            foreach ($rules as $rule) {
                if (!is_array($rule)) {
                    continue;
                }

                $ruleAppId = trim((string) ($rule['AppId'] ?? ''));

                if (
                    $ruleAppId !== ''
                    && strcasecmp($ruleAppId, $appId) === 0
                ) {
                    return $rule;
                }

                if ($ruleAppId === '' && $fallback === null) {
                    $fallback = $rule;
                }
            }
        }

        return $fallback ?? [
            'PlayingMode' => self::MODE_LIVE,
            'PausedMode'  => self::MODE_WARM_WHITE,
            'BusScene'    => self::BUS_SCENE_UNCHANGED
        ];
    }

    private function runAppleTVScene(
        int $targetMode,
        int $busScene,
        string $state,
        string $appId
    ): void {
        $this->getLogger()->info(
            'Apple-TV-Automatik wendet Szene an',
            [
                'state'      => $state,
                'appId'      => $appId,
                'targetMode' => $targetMode,
                'busScene'   => $busScene
            ]
        );

        try {
            if (
                $targetMode !== self::APP_MODE_NO_CHANGE
                && (int) $this->GetValue('AmbilightMode') !== $targetMode
            ) {
                $this->applyAmbilightMode($targetMode);
            }

            $this->applyBusScene($busScene);

            $this->SetValue('LastActionError', '');
        } catch (Throwable $exception) {
            $this->SetValue(
                'LastActionError',
                $exception->getMessage()
            );

            $this->logException(
                'Apple-TV-Automatik fehlgeschlagen',
                $exception
            );
        }
    }

    private function applyBusScene(int $busScene): void
    {
        if ($busScene === self::BUS_SCENE_UNCHANGED) {
            return;
        }

        $driver = $this->getWLEDDriver();
        $transaction = $driver->beginTransaction();

        for ($busNumber = 2; $busNumber <= 4; $busNumber++) {
            $update = $transaction->bus($busNumber);

            switch ($busScene) {
                case self::BUS_SCENE_OFF:
                    $update->power(false);
                    break;

                case self::BUS_SCENE_WARM_DIMMED:
                    [$red, $green, $blue, $white] = self::WARM_WHITE_RGBW;

                    $update
                        ->power(true)
                        ->brightness(self::BUS_SCENE_WARM_BRIGHTNESS)
                        ->rgbw($red, $green, $blue, $white)
                        ->effect(self::FX_SOLID);
                    break;

                case self::BUS_SCENE_NEUTRAL_WHITE:
                    [$red, $green, $blue, $white] = self::BUS_SCENE_NEUTRAL_RGBW;

                    $update
                        ->power(true)
                        ->brightness(self::BUS_SCENE_NEUTRAL_BRIGHTNESS)
                        ->rgbw($red, $green, $blue, $white)
                        ->effect(self::FX_SOLID);
                    break;

                default:
                    throw new InvalidArgumentException(
                        'Unbekannte Bus-Szene: ' . $busScene
                    );
            }
        }

        $transaction->commit(
            transition: 7,
            forceControllerOn: $busScene !== self::BUS_SCENE_OFF
        );
    }

    private function getAppleTVDriver(): AppleTVDriver
    {
        $logger = $this->getLogger();

        return new AppleTVDriver(
            client: new AppleTVClient(
                httpClient: new HttpClient(
                    timeout: 5,
                    logger: $logger
                ),
                logger: $logger,
                host: $this->ReadPropertyString('AppleTVHost'),
                port: $this->ReadPropertyInteger('AppleTVPort')
            ),
            logger: $logger
        );
    }

    private function handleHyperHDRComponentAction(
        string $ident,
        bool $enabled
    ): void {
        $component = match ($ident) {
            'HyperHDRLEDDeviceEnabled' => 'LEDDEVICE',
            'HyperHDRGrabberEnabled'   => 'VIDEOGRABBER',
            default => throw new InvalidArgumentException(
                'Unbekannte HyperHDR-Aktion: ' . $ident
            )
        };

        try {
            $driver = $this->getHyperHDRDriver();

            $driver->setComponentState(
                component: $component,
                enabled: $enabled
            );

            usleep(200000);

            $status = $driver->readStatus();
            $this->getStatusManager()->applyHyperHDR($status);

            $this->SetValue('LastActionError', '');
        } catch (Throwable $exception) {
            $this->SetValue(
                'LastActionError',
                $exception->getMessage()
            );

            $this->logException(
                sprintf(
                    'HyperHDR-Komponente %s konnte nicht geschaltet werden',
                    $component
                ),
                $exception
            );

            throw $exception;
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

        $this->RegisterPropertyBoolean('AppleTVEnabled', false);
        $this->RegisterPropertyString('AppleTVHost', '');
        $this->RegisterPropertyInteger('AppleTVPort', 8091);
        $this->RegisterPropertyString('AppleTVAppRules', '[]');
        $this->RegisterPropertyInteger('AppleTVStandbyBusScene', 0);
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

        $this->RegisterVariableString(
            'LastActionError',
            'Letzter Aktionsfehler',
            '',
            45
        );

        $this->RegisterVariableInteger(
            'AmbilightMode',
            'Ambilight-Modus',
            'AMBI.Mode',
            15
        );

        $this->EnableAction(
            'AmbilightMode'
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

        $this->EnableAction(
            'HyperHDRGrabberEnabled'
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

        $this->EnableAction(
            'HyperHDRLEDDeviceEnabled'
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

    private function registerDisplayProfiles(): void
    {
        $definitions = [
            ['MEDIA.Percent', ' %', 'Intensity'],
            ['MEDIA.FPS', ' fps', 'Graph'],
            ['MEDIA.mA', ' mA', 'Electricity'],
            ['MEDIA.Sekunden', ' s', 'Clock']
        ];

        foreach ($definitions as [$profile, $suffix, $icon]) {
            if (!IPS_VariableProfileExists($profile)) {
                IPS_CreateVariableProfile(
                    $profile,
                    VARIABLETYPE_INTEGER
                );
            }

            IPS_SetVariableProfileText($profile, '', $suffix);
            IPS_SetVariableProfileIcon($profile, $icon);
        }

        IPS_SetVariableProfileValues('MEDIA.Percent', 0, 100, 1);
    }

    private function registerWLEDVariables(): void
    {
        $this->registerDisplayProfiles();

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
            ['int', 'WLEDCurrentPower', 'WLED aktuelle Leistung', 'MEDIA.mA'],
            ['int', 'WLEDFPS', 'WLED FPS', 'MEDIA.FPS'],
            ['int', 'WLEDEffectCount', 'WLED Effekte'],
            ['int', 'WLEDPaletteCount', 'WLED Paletten'],
            ['int', 'WLEDUptime', 'WLED Laufzeit', 'MEDIA.Sekunden'],
            ['int', 'WLEDFreeHeap', 'WLED freier Speicher'],
            ['int', 'WLEDRSSI', 'WLED RSSI'],
            ['int', 'WLEDSignalQuality', 'WLED Signalqualität', 'MEDIA.Percent'],
            ['string', 'WLEDLiveMode', 'WLED Live-Modus'],
            ['string', 'WLEDLiveSourceIP', 'WLED Live-Quelle'],
            ['bool', 'WLEDPower', 'WLED eingeschaltet'],
            ['int', 'WLEDBrightness', 'WLED Helligkeit', '~Intensity.255'],
            ['bool', 'WLEDRealtime', 'WLED Realtime'],
            ['int', 'WLEDRealtimeMode', 'WLED Realtime-Override'],
            ['int', 'WLEDSegmentCount', 'WLED Segmente'],
            ['bool', 'WLEDUDPSend', 'WLED UDP senden'],
            ['bool', 'WLEDUDPReceive', 'WLED UDP empfangen'],
            ['bool', 'SegmentsInSync', 'WLED-Segmentlayout synchron'],
            ['bool', 'SyncSegments', 'WLED-Segmente synchronisieren'],
            ['bool', 'Bus1FollowMode', 'Bus 1 folgt Ambilight-Modus'],
            ['bool', 'Bus2FollowMode', 'Bus 2 folgt Ambilight-Modus'],
            ['bool', 'Bus3FollowMode', 'Bus 3 folgt Ambilight-Modus'],
            ['bool', 'Bus4FollowMode', 'Bus 4 folgt Ambilight-Modus'],
            ['string', 'LayoutHint', 'Layout-Hinweise']
        ];

        foreach ($definitions as $definition) {
            [$type, $ident, $name] = $definition;

            $profile = isset($definition[3])
                ? $definition[3]
                : ($type === 'bool' ? '~Switch' : '');

            $position += 10;

            match ($type) {
                'bool' => $this->RegisterVariableBoolean(
                    $ident,
                    $name,
                    $profile,
                    $position
                ),
                'int' => $this->RegisterVariableInteger(
                    $ident,
                    $name,
                    $profile,
                    $position
                ),
                default => $this->RegisterVariableString(
                    $ident,
                    $name,
                    $profile,
                    $position
                )
            };
        }

        $this->EnableAction('WLEDPower');
        $this->EnableAction('WLEDBrightness');
        $this->EnableAction('SyncSegments');
        $this->EnableAction('Bus1FollowMode');
        $this->EnableAction('Bus2FollowMode');
        $this->EnableAction('Bus3FollowMode');
        $this->EnableAction('Bus4FollowMode');

        if ($this->GetValue('Bus1FollowMode') === false
            && $this->GetValue('Bus2FollowMode') === false
            && $this->GetValue('Bus3FollowMode') === false
            && $this->GetValue('Bus4FollowMode') === false
        ) {
            $this->SetValue('Bus1FollowMode', true);
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

    private function registerWLEDControlVariables(): void
    {
        for ($busNumber = 1; $busNumber <= 4; $busNumber++) {
            $prefix = 'WLEDBus' . $busNumber;
            $caption = 'WLED Bus ' . $busNumber . ' ';
            $position = 2000 + ($busNumber * 100);

            $this->RegisterVariableBoolean(
                $prefix . 'Power',
                $caption . 'Ein/Aus',
                '~Switch',
                $position += 10
            );

            $this->EnableAction(
                $prefix . 'Power'
            );

            $this->RegisterVariableInteger(
                $prefix . 'Brightness',
                $caption . 'Helligkeit',
                '~Intensity.255',
                $position += 10
            );

            $this->EnableAction(
                $prefix . 'Brightness'
            );

            $this->RegisterVariableInteger(
                $prefix . 'Color',
                $caption . 'Farbe',
                '~HexColor',
                $position += 10
            );

            $this->EnableAction(
                $prefix . 'Color'
            );

            $this->RegisterVariableInteger(
                $prefix . 'White',
                $caption . 'Weißkanal',
                '~Intensity.255',
                $position += 10
            );

            $this->EnableAction(
                $prefix . 'White'
            );

            $this->RegisterVariableInteger(
                $prefix . 'Effect',
                $caption . 'Effekt',
                'AMBI.Effect',
                $position += 10
            );

            $this->EnableAction(
                $prefix . 'Effect'
            );
        }
    }

    /**
     * Prüft, ob das WLED-Segmentlayout zu den physischen Bussen passt
     * und ob das HyperHDR-LED-Layout mit Bus 1 übereinstimmt.
     *
     * WLED ist dabei die Quelle der Wahrheit: Die Buskonfiguration wird
     * ausschließlich aus dem Gerät gelesen, nie aus IP-Symcon vorgegeben.
     */
    private function updateLayoutConsistency(
        ?WLEDController $controller,
        ?HyperHDRStatus $status
    ): void {
        if ($controller === null) {
            $this->SetValue('SegmentsInSync', false);
            $this->SetValue('LayoutHint', '');

            return;
        }

        $inSync = true;
        $hints = [];

        foreach ($controller->getBuses() as $bus) {
            $segment = $controller
                ->getState()
                ->getSegment($bus->getIndex());

            if (
                $segment === null
                || $segment->getStart() !== $bus->getStart()
                || $segment->getStop() !== $bus->getStop()
            ) {
                $inSync = false;
                $hints[] = sprintf(
                    'Segment %d passt nicht zu Bus %d (%d–%d). '
                    . 'Bitte Segmente synchronisieren.',
                    $bus->getIndex(),
                    $bus->getNumber(),
                    $bus->getStart(),
                    $bus->getStop()
                );
            }
        }

        if (
            $status !== null
            && $status->isOnline()
            && $status->getLedCount() > 0
        ) {
            $busOne = null;

            foreach ($controller->getBuses() as $bus) {
                if ($bus->getNumber() === 1) {
                    $busOne = $bus;

                    break;
                }
            }

            if (
                $busOne !== null
                && $status->getLedCount() !== $busOne->getLength()
            ) {
                $hints[] = sprintf(
                    'HyperHDR-Layout (%d LEDs) weicht von '
                    . 'Bus 1 (%d LEDs) ab.',
                    $status->getLedCount(),
                    $busOne->getLength()
                );
            }
        }

        $this->SetValue('SegmentsInSync', $inSync);
        $this->SetValue('LayoutHint', implode(' ', $hints));
    }

    private function assertControllableBus(
        int $busNumber
    ): void {
        if ($busNumber < 1 || $busNumber > 4) {
            throw new InvalidArgumentException(
                'Nur die WLED-Busse 1 bis 4 sind direkt steuerbar.'
            );
        }
    }

    private function assertWLEDSegmentExists(
        WLEDDriver $driver,
        int $busNumber
    ): void {
        $this->assertControllableBus($busNumber);

        $controller = $driver->readController();
        $segmentId = $busNumber - 1;

        if (
            $controller
                ->getState()
                ->getSegment($segmentId) === null
        ) {
            throw new RuntimeException(
                sprintf(
                    'Für WLED Bus %d existiert noch kein Segment. '
                    . 'Bitte zuerst „WLED-Segmente mit '
                    . 'Buskonfiguration synchronisieren“ ausführen.',
                    $busNumber
                )
            );
        }
    }

    private function setWLEDBusColor(
        WLEDDriver $driver,
        int $busNumber,
        int $color,
        int $white,
        int $brightness
    ): void {
        $red = ($color >> 16) & 0xFF;
        $green = ($color >> 8) & 0xFF;
        $blue = $color & 0xFF;

        $driver->setBusRgbw(
            busNumber: $busNumber,
            red: $red,
            green: $green,
            blue: $blue,
            white: max(0, min(255, $white)),
            brightness: max(1, min(255, $brightness)),
            transition: 7
        );
    }

    private function readIntegerValue(
        string $ident
    ): int {
        $variableId = @$this->GetIDForIdent($ident);

        if ($variableId <= 0) {
            return 0;
        }

        return (int) GetValue($variableId);
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