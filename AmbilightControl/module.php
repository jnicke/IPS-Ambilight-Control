<?php

declare(strict_types=1);

class AmbilightControl extends IPSModule
{
    private const STATUS_ACTIVE = 102;
    private const STATUS_INACTIVE = 104;
    private const STATUS_CONFIGURATION_MISSING = 201;
    private const STATUS_WLED_UNREACHABLE = 202;
    private const STATUS_HYPERHDR_UNREACHABLE = 203;
    private const STATUS_INVALID_RESPONSE = 204;
    private const STATUS_APPLETV_UNREACHABLE = 205;

    private const MODE_OFF = 0;
    private const MODE_LIVE = 1;
    private const MODE_WARM_WHITE = 2;
    private const MODE_NIGHT = 3;
    private const MODE_CLEANING = 4;

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyString('WLEDHost', '');
        $this->RegisterPropertyInteger('WLEDPort', 80);
        $this->RegisterPropertyBoolean('WLEDHTTPS', false);
        $this->RegisterPropertyInteger('WarmWhitePreset', 1);
        $this->RegisterPropertyInteger('NightPreset', 2);
        $this->RegisterPropertyInteger('CleaningPreset', 3);

        $this->RegisterPropertyString('HyperHDRHost', '');
        $this->RegisterPropertyInteger('HyperHDRPort', 8090);
        $this->RegisterPropertyBoolean('HyperHDRHTTPS', false);
        $this->RegisterPropertyString('HyperHDRToken', '');
        $this->RegisterPropertyInteger('HyperHDRInstance', 0);

        $this->RegisterPropertyBoolean('AppleTVEnabled', false);
        $this->RegisterPropertyString('AppleTVMonitorHost', '');
        $this->RegisterPropertyInteger('AppleTVMonitorPort', 8091);
        $this->RegisterPropertyBoolean('AppleTVMonitorHTTPS', false);
        $this->RegisterPropertyString('AppleTVMonitorPath', '/status');
        $this->RegisterPropertyInteger('AppleTVPollInterval', 2);

        $this->RegisterPropertyBoolean('AutomationEnabled', false);
        $this->RegisterPropertyInteger('SourceVariableID', 0);
        $this->RegisterPropertyString('PlayingValues', 'playing,play,on,live');
        $this->RegisterPropertyString('PausedValues', 'paused,pause,idle');
        $this->RegisterPropertyString('OffValues', 'off,standby,stopped,unavailable,unknown,offline');
        $this->RegisterPropertyInteger('PausedMode', self::MODE_WARM_WHITE);
        $this->RegisterPropertyInteger('UpdateInterval', 10);
        $this->RegisterPropertyBoolean('EnableDebug', false);

        $this->RegisterVariableInteger('Mode', 'Mode', 'AMBI.Mode', 10);
        $this->EnableAction('Mode');
        $this->RegisterVariableBoolean('AutomationActive', 'Automation active', '~Switch', 20);
        $this->EnableAction('AutomationActive');
        $this->RegisterVariableString('MediaState', 'Media state', '', 30);

        $this->RegisterVariableBoolean('WLEDOnline', 'WLED online', '~Switch', 100);
        $this->RegisterVariableBoolean('WLEDPower', 'WLED power', '~Switch', 110);
        $this->EnableAction('WLEDPower');
        $this->RegisterVariableInteger('WLEDBrightness', 'WLED brightness', '~Intensity.255', 120);
        $this->EnableAction('WLEDBrightness');
        $this->RegisterVariableString('WLEDName', 'WLED name', '', 130);
        $this->RegisterVariableString('WLEDVersion', 'WLED version', '', 140);

        $this->RegisterVariableBoolean('HyperHDROnline', 'HyperHDR online', '~Switch', 200);
        $this->RegisterVariableBoolean('HyperHDREnabled', 'HyperHDR enabled', '~Switch', 210);
        $this->EnableAction('HyperHDREnabled');
        $this->RegisterVariableBoolean('GrabberActive', 'Grabber active', '~Switch', 220);
        $this->RegisterVariableBoolean('LEDDeviceActive', 'LED device active', '~Switch', 230);
        $this->RegisterVariableFloat('FPS', 'FPS', '', 240);
        $this->RegisterVariableString('HyperHDRVersion', 'HyperHDR version', '', 250);

        $this->RegisterVariableBoolean('AppleTVOnline', 'Apple TV online', '~Switch', 300);
        $this->RegisterVariableString('AppleTVPower', 'Apple TV power', '', 310);
        $this->RegisterVariableString('AppleTVState', 'Apple TV state', '', 320);
        $this->RegisterVariableString('AppleTVApp', 'Apple TV app', '', 330);
        $this->RegisterVariableString('AppleTVTitle', 'Apple TV title', '', 340);
        $this->RegisterVariableString('AppleTVMediaType', 'Apple TV media type', '', 350);
        $this->RegisterVariableInteger('AppleTVUpdated', 'Apple TV monitor updated', '~UnixTimestamp', 360);
        $this->RegisterVariableString('AppleTVError', 'Apple TV error', '', 370);

        $this->RegisterTimer('UpdateTimer', 0, 'AMBI_Update($_IPS["TARGET"]);');
        $this->RegisterTimer('AppleTVTimer', 0, 'AMBI_UpdateAppleTV($_IPS["TARGET"]);');
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        $this->RegisterProfiles();

        $this->SetTimerInterval('UpdateTimer', max(0, $this->ReadPropertyInteger('UpdateInterval')) * 1000);
        $appleInterval = $this->ReadPropertyBoolean('AppleTVEnabled')
            ? max(1, $this->ReadPropertyInteger('AppleTVPollInterval')) * 1000
            : 0;
        $this->SetTimerInterval('AppleTVTimer', $appleInterval);

        $this->SetValue('AutomationActive', $this->ReadPropertyBoolean('AutomationEnabled'));
        $this->RegisterSourceVariableMessages();

        if (!$this->HasAnyConfiguration()) {
            $this->SetStatus(self::STATUS_CONFIGURATION_MISSING);
            return;
        }

        $this->SetStatus(self::STATUS_ACTIVE);
        $this->Update();
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data): void
    {
        if ($Message !== VM_UPDATE || $SenderID !== $this->ReadPropertyInteger('SourceVariableID')) {
            return;
        }

        if ($this->GetValue('AutomationActive')) {
            $this->SetMediaState((string) GetValue($SenderID));
        }
    }

    public function RequestAction($Ident, $Value): void
    {
        switch ($Ident) {
            case 'Mode':
                $this->SetMode((int) $Value);
                break;
            case 'AutomationActive':
                $this->SetValue('AutomationActive', (bool) $Value);
                break;
            case 'WLEDPower':
                $this->SetWLEDPower((bool) $Value);
                break;
            case 'WLEDBrightness':
                $this->SetBrightness((int) $Value);
                break;
            case 'HyperHDREnabled':
                $this->SetHyperHDREnabled((bool) $Value);
                break;
            default:
                throw new InvalidArgumentException('Unknown ident: ' . $Ident);
        }
    }

    public function Update(): bool
    {
        $ok = true;
        if ($this->HasWLEDConfiguration()) {
            $ok = $this->UpdateWLED() && $ok;
        }
        if ($this->HasHyperHDRConfiguration()) {
            $ok = $this->UpdateHyperHDR() && $ok;
        }
        if ($this->ReadPropertyBoolean('AppleTVEnabled')) {
            $ok = $this->UpdateAppleTV() && $ok;
        }
        return $ok;
    }

    public function UpdateAppleTV(): bool
    {
        if (!$this->ReadPropertyBoolean('AppleTVEnabled')) {
            $this->ResetAppleTVStatus();
            return false;
        }

        $host = trim($this->ReadPropertyString('AppleTVMonitorHost'));
        if ($host === '') {
            $this->ResetAppleTVStatus('Monitor host missing');
            return false;
        }

        $scheme = $this->ReadPropertyBoolean('AppleTVMonitorHTTPS') ? 'https' : 'http';
        $path = '/' . ltrim(trim($this->ReadPropertyString('AppleTVMonitorPath')), '/');
        $url = sprintf('%s://%s:%d%s', $scheme, $host, $this->ReadPropertyInteger('AppleTVMonitorPort'), $path);
        $response = $this->HTTPRequest('GET', $url, null, [], 'AppleTV');

        if ($response === null) {
            $this->ResetAppleTVStatus('Monitor unavailable');
            return false;
        }

        $online = (bool) ($response['online'] ?? false);
        $state = strtolower(trim((string) ($response['state'] ?? 'offline')));
        $this->SetValue('AppleTVOnline', $online);
        $this->SetValue('AppleTVPower', (string) ($response['power'] ?? 'unknown'));
        $this->SetValue('AppleTVState', $state);
        $this->SetValue('AppleTVApp', (string) ($response['app'] ?? ''));
        $this->SetValue('AppleTVTitle', (string) ($response['title'] ?? ''));
        $this->SetValue('AppleTVMediaType', (string) ($response['media_type'] ?? ''));
        $this->SetValue('AppleTVUpdated', (int) ($response['updated'] ?? time()));
        $this->SetValue('AppleTVError', (string) ($response['error'] ?? ''));

        if ($this->GetValue('AutomationActive')) {
            $this->SetMediaState($online ? $state : 'offline');
        }
        return true;
    }

    public function TestWLED(): bool
    {
        return $this->UpdateWLED();
    }

    public function TestHyperHDR(): bool
    {
        return $this->UpdateHyperHDR();
    }

    public function TestAppleTV(): bool
    {
        return $this->UpdateAppleTV();
    }

    public function SetMode(int $Mode): bool
    {
        if (!in_array($Mode, [0, 1, 2, 3, 4], true)) {
            throw new InvalidArgumentException('Invalid mode');
        }

        $success = true;
        switch ($Mode) {
            case self::MODE_OFF:
                if ($this->HasHyperHDRConfiguration()) {
                    $success = $this->SetHyperHDREnabled(false) && $success;
                }
                if ($this->HasWLEDConfiguration()) {
                    $success = $this->SetWLEDPower(false) && $success;
                }
                break;
            case self::MODE_LIVE:
                if ($this->HasWLEDConfiguration()) {
                    $success = $this->SetWLEDPower(true) && $success;
                }
                if ($this->HasHyperHDRConfiguration()) {
                    $success = $this->SetHyperHDREnabled(true) && $success;
                }
                break;
            case self::MODE_WARM_WHITE:
                $success = $this->ActivatePreset($this->ReadPropertyInteger('WarmWhitePreset'));
                break;
            case self::MODE_NIGHT:
                $success = $this->ActivatePreset($this->ReadPropertyInteger('NightPreset'));
                break;
            case self::MODE_CLEANING:
                $success = $this->ActivatePreset($this->ReadPropertyInteger('CleaningPreset'));
                break;
        }

        if ($success) {
            $this->SetValue('Mode', $Mode);
        }
        return $success;
    }

    public function SetMediaState(string $State): void
    {
        $state = strtolower(trim($State));
        $this->SetValue('MediaState', $state);

        if (!$this->GetValue('AutomationActive')) {
            return;
        }

        if ($this->ValueMatches($state, $this->ReadPropertyString('PlayingValues'))) {
            $this->SetMode(self::MODE_LIVE);
        } elseif ($this->ValueMatches($state, $this->ReadPropertyString('PausedValues'))) {
            $this->SetMode($this->ReadPropertyInteger('PausedMode'));
        } elseif ($this->ValueMatches($state, $this->ReadPropertyString('OffValues'))) {
            $this->SetMode(self::MODE_OFF);
        }
    }

    public function SetWLEDPower(bool $State): bool
    {
        $result = $this->WLEDRequest('POST', '/json/state', ['on' => $State]);
        if ($result === null) {
            return false;
        }
        $this->SetValue('WLEDPower', $State);
        return true;
    }

    public function SetBrightness(int $Brightness): bool
    {
        $brightness = max(0, min(255, $Brightness));
        $result = $this->WLEDRequest('POST', '/json/state', ['bri' => $brightness, 'on' => $brightness > 0]);
        if ($result === null) {
            return false;
        }
        $this->SetValue('WLEDBrightness', $brightness);
        $this->SetValue('WLEDPower', $brightness > 0);
        return true;
    }

    public function SetPreset(int $Preset): bool
    {
        if ($Preset <= 0) {
            return false;
        }
        $result = $this->WLEDRequest('POST', '/json/state', ['ps' => $Preset, 'on' => true]);
        return $result !== null;
    }

    public function SetHyperHDREnabled(bool $Enabled): bool
    {
        $response = $this->HyperHDRRequest('componentstate', [
            'componentstate' => ['component' => 'ALL', 'state' => $Enabled]
        ]);
        if ($response === null || (($response['success'] ?? false) !== true)) {
            return false;
        }
        $this->SetValue('HyperHDREnabled', $Enabled);
        return true;
    }

    private function ActivatePreset(int $Preset): bool
    {
        $success = true;
        if ($this->HasHyperHDRConfiguration()) {
            $success = $this->SetHyperHDREnabled(false) && $success;
        }
        if ($this->HasWLEDConfiguration()) {
            $success = $this->SetWLEDPower(true) && $success;
            if ($Preset > 0) {
                $success = $this->SetPreset($Preset) && $success;
            }
        }
        return $success;
    }

    private function UpdateWLED(): bool
    {
        $data = $this->WLEDRequest('GET', '/json/si');
        if ($data === null) {
            $this->SetValue('WLEDOnline', false);
            return false;
        }

        $state = is_array($data['state'] ?? null) ? $data['state'] : [];
        $info = is_array($data['info'] ?? null) ? $data['info'] : [];
        $this->SetValue('WLEDOnline', true);
        $this->SetValue('WLEDPower', (bool) ($state['on'] ?? false));
        $this->SetValue('WLEDBrightness', (int) ($state['bri'] ?? 0));
        $this->SetValue('WLEDName', (string) ($info['name'] ?? ''));
        $this->SetValue('WLEDVersion', (string) ($info['ver'] ?? ''));
        return true;
    }

    private function UpdateHyperHDR(): bool
    {
        $response = $this->HyperHDRRequest('serverinfo');
        if ($response === null || (($response['success'] ?? false) !== true)) {
            $this->ResetHyperHDRStatus();
            return false;
        }

        $info = is_array($response['info'] ?? null) ? $response['info'] : [];
        $components = is_array($info['components'] ?? null) ? $info['components'] : [];
        $all = false;
        $grabber = false;
        $led = false;
        foreach ($components as $component) {
            if (!is_array($component)) {
                continue;
            }
            $name = strtoupper((string) ($component['name'] ?? $component['component'] ?? ''));
            $enabled = (bool) ($component['enabled'] ?? false);
            if ($name === 'ALL') {
                $all = $enabled;
            }
            if (in_array($name, ['GRABBER', 'V4L', 'VIDEOGRABBER'], true)) {
                $grabber = $enabled;
            }
            if (in_array($name, ['LEDDEVICE', 'LED'], true)) {
                $led = $enabled;
            }
        }

        $fps = (float) ($info['fps'] ?? $info['videoFps'] ?? $info['currentFps'] ?? ($info['grabber']['fps'] ?? 0.0));
        $version = (string) ($info['version'] ?? ($info['system']['hyperhdrVersion'] ?? ''));
        $this->SetValue('HyperHDROnline', true);
        $this->SetValue('HyperHDREnabled', $all || $grabber || $led);
        $this->SetValue('GrabberActive', $grabber);
        $this->SetValue('LEDDeviceActive', $led);
        $this->SetValue('FPS', $fps);
        $this->SetValue('HyperHDRVersion', $version);
        return true;
    }

    private function ResetHyperHDRStatus(): void
    {
        $this->SetValue('HyperHDROnline', false);
        $this->SetValue('HyperHDREnabled', false);
        $this->SetValue('GrabberActive', false);
        $this->SetValue('LEDDeviceActive', false);
        $this->SetValue('FPS', 0.0);
        $this->SetValue('HyperHDRVersion', '');
    }

    private function ResetAppleTVStatus(string $Error = ''): void
    {
        $this->SetValue('AppleTVOnline', false);
        $this->SetValue('AppleTVPower', 'unknown');
        $this->SetValue('AppleTVState', 'offline');
        $this->SetValue('AppleTVApp', '');
        $this->SetValue('AppleTVTitle', '');
        $this->SetValue('AppleTVMediaType', '');
        $this->SetValue('AppleTVUpdated', time());
        $this->SetValue('AppleTVError', $Error);
        if ($this->GetValue('AutomationActive')) {
            $this->SetMediaState('offline');
        }
    }

    private function WLEDRequest(string $Method, string $Path, ?array $Payload = null): ?array
    {
        if (!$this->HasWLEDConfiguration()) {
            return null;
        }
        $scheme = $this->ReadPropertyBoolean('WLEDHTTPS') ? 'https' : 'http';
        $url = sprintf('%s://%s:%d%s', $scheme, trim($this->ReadPropertyString('WLEDHost')), $this->ReadPropertyInteger('WLEDPort'), $Path);
        return $this->HTTPRequest($Method, $url, $Payload, [], 'WLED');
    }

    private function HyperHDRRequest(string $Command, array $Arguments = []): ?array
    {
        if (!$this->HasHyperHDRConfiguration()) {
            return null;
        }
        $scheme = $this->ReadPropertyBoolean('HyperHDRHTTPS') ? 'https' : 'http';
        $url = sprintf('%s://%s:%d/json-rpc', $scheme, trim($this->ReadPropertyString('HyperHDRHost')), $this->ReadPropertyInteger('HyperHDRPort'));
        $payload = array_merge(['command' => $Command, 'tan' => random_int(1, 2147483647)], $Arguments);
        $headers = [];
        $token = trim($this->ReadPropertyString('HyperHDRToken'));
        if ($token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }
        return $this->HTTPRequest('POST', $url, $payload, $headers, 'HyperHDR');
    }

    private function HTTPRequest(string $Method, string $Url, ?array $Payload, array $Headers, string $Label): ?array
    {
        $curl = curl_init($Url);
        if ($curl === false) {
            return null;
        }
        $requestHeaders = array_merge(['Accept: application/json'], $Headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($curl, CURLOPT_TIMEOUT, 5);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $Method);
        if ($Payload !== null) {
            $body = json_encode($Payload, JSON_UNESCAPED_SLASHES);
            if ($body === false) {
                curl_close($curl);
                return null;
            }
            $requestHeaders[] = 'Content-Type: application/json';
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
            $this->Debug($Label . ' request', $body);
        }
        curl_setopt($curl, CURLOPT_HTTPHEADER, $requestHeaders);
        $raw = curl_exec($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if (!is_string($raw) || $raw === '' || $httpCode < 200 || $httpCode >= 300) {
            $this->Debug($Label . ' error', ['httpCode' => $httpCode, 'error' => $error, 'response' => $raw]);
            return null;
        }
        $this->Debug($Label . ' response', $raw);
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function RegisterProfiles(): void
    {
        if (!IPS_VariableProfileExists('AMBI.Mode')) {
            IPS_CreateVariableProfile('AMBI.Mode', VARIABLETYPE_INTEGER);
        }
        IPS_SetVariableProfileAssociation('AMBI.Mode', 0, 'Off', '', -1);
        IPS_SetVariableProfileAssociation('AMBI.Mode', 1, 'Live', '', -1);
        IPS_SetVariableProfileAssociation('AMBI.Mode', 2, 'Warm white', '', -1);
        IPS_SetVariableProfileAssociation('AMBI.Mode', 3, 'Night', '', -1);
        IPS_SetVariableProfileAssociation('AMBI.Mode', 4, 'Cleaning', '', -1);
    }

    private function RegisterSourceVariableMessages(): void
    {
        foreach ($this->GetMessageList() as $senderID => $messages) {
            if (in_array(VM_UPDATE, $messages, true)) {
                $this->UnregisterMessage((int) $senderID, VM_UPDATE);
            }
        }
        $sourceID = $this->ReadPropertyInteger('SourceVariableID');
        if ($sourceID > 0 && IPS_VariableExists($sourceID)) {
            $this->RegisterMessage($sourceID, VM_UPDATE);
        }
    }

    private function HasAnyConfiguration(): bool
    {
        return $this->HasWLEDConfiguration()
            || $this->HasHyperHDRConfiguration()
            || ($this->ReadPropertyBoolean('AppleTVEnabled') && trim($this->ReadPropertyString('AppleTVMonitorHost')) !== '');
    }

    private function HasWLEDConfiguration(): bool
    {
        return trim($this->ReadPropertyString('WLEDHost')) !== '';
    }

    private function HasHyperHDRConfiguration(): bool
    {
        return trim($this->ReadPropertyString('HyperHDRHost')) !== '';
    }

    private function ValueMatches(string $Value, string $List): bool
    {
        $values = array_filter(array_map(static fn(string $item): string => strtolower(trim($item)), explode(',', $List)));
        return in_array(strtolower(trim($Value)), $values, true);
    }

    private function Debug(string $Message, $Data): void
    {
        if (!$this->ReadPropertyBoolean('EnableDebug')) {
            return;
        }
        $this->SendDebug($Message, is_string($Data) ? $Data : json_encode($Data, JSON_UNESCAPED_SLASHES), 0);
    }
}
