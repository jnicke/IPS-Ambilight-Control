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

    private const MODE_OFF = 0;
    private const MODE_LIVE = 1;
    private const MODE_WARM_WHITE = 2;
    private const MODE_NIGHT = 3;
    private const MODE_CLEANING = 4;

    public function Create(): void
    {
        parent::Create();

        // WLED
        $this->RegisterPropertyString('WLEDHost', '');
        $this->RegisterPropertyInteger('WLEDPort', 80);
        $this->RegisterPropertyBoolean('WLEDHTTPS', false);
        $this->RegisterPropertyInteger('WarmWhitePreset', 1);
        $this->RegisterPropertyInteger('NightPreset', 2);
        $this->RegisterPropertyInteger('CleaningPreset', 3);

        // HyperHDR
        $this->RegisterPropertyString('HyperHDRHost', '');
        $this->RegisterPropertyInteger('HyperHDRPort', 8090);
        $this->RegisterPropertyBoolean('HyperHDRHTTPS', false);
        $this->RegisterPropertyString('HyperHDRToken', '');
        $this->RegisterPropertyInteger('HyperHDRInstance', 0);

        // Automation / external media state
        $this->RegisterPropertyBoolean('AutomationEnabled', false);
        $this->RegisterPropertyInteger('SourceVariableID', 0);
        $this->RegisterPropertyString('PlayingValues', 'playing,play,on,live');
        $this->RegisterPropertyString('PausedValues', 'paused,pause,idle');
        $this->RegisterPropertyString('OffValues', 'off,standby,stopped,unavailable,unknown');
        $this->RegisterPropertyInteger('PausedMode', self::MODE_WARM_WHITE);
        $this->RegisterPropertyInteger('UpdateInterval', 10);
        $this->RegisterPropertyBoolean('EnableDebug', false);

        $this->RegisterProfileInteger('AMBI.Mode', 'Bulb', '', '', [
            [self::MODE_OFF, $this->Translate('Off'), '', -1],
            [self::MODE_LIVE, $this->Translate('Live'), '', -1],
            [self::MODE_WARM_WHITE, $this->Translate('Warm white'), '', -1],
            [self::MODE_NIGHT, $this->Translate('Night'), '', -1],
            [self::MODE_CLEANING, $this->Translate('Cleaning'), '', -1]
        ]);

        $this->RegisterVariableInteger('Mode', $this->Translate('Mode'), 'AMBI.Mode', 10);
        $this->EnableAction('Mode');
        $this->RegisterVariableBoolean('Automation', $this->Translate('Automation'), '~Switch', 20);
        $this->EnableAction('Automation');
        $this->RegisterVariableString('MediaState', $this->Translate('Media state'), '', 30);

        $this->RegisterVariableBoolean('WLEDOnline', $this->Translate('WLED online'), '~Switch', 100);
        $this->RegisterVariableBoolean('WLEDPower', $this->Translate('WLED power'), '~Switch', 110);
        $this->EnableAction('WLEDPower');
        $this->RegisterVariableInteger('Brightness', $this->Translate('Brightness'), '~Intensity.255', 120);
        $this->EnableAction('Brightness');
        $this->RegisterVariableInteger('Preset', $this->Translate('Preset'), '', 130);
        $this->EnableAction('Preset');
        $this->RegisterVariableString('WLEDVersion', $this->Translate('WLED version'), '', 140);
        $this->RegisterVariableString('WLEDDeviceName', $this->Translate('WLED device name'), '', 150);

        $this->RegisterVariableBoolean('HyperHDROnline', $this->Translate('HyperHDR online'), '~Switch', 200);
        $this->RegisterVariableBoolean('HyperHDREnabled', $this->Translate('HyperHDR enabled'), '~Switch', 210);
        $this->EnableAction('HyperHDREnabled');
        $this->RegisterVariableBoolean('GrabberActive', $this->Translate('Grabber active'), '~Switch', 220);
        $this->RegisterVariableBoolean('LEDDeviceActive', $this->Translate('LED device active'), '~Switch', 230);
        $this->RegisterVariableFloat('FPS', $this->Translate('FPS'), '', 240);
        $this->RegisterVariableString('HyperHDRVersion', $this->Translate('HyperHDR version'), '', 250);

        $this->RegisterVariableInteger('LastUpdate', $this->Translate('Last update'), '~UnixTimestamp', 300);
        $this->RegisterVariableString('LastError', $this->Translate('Last error'), '', 310);

        $this->RegisterTimer('UpdateTimer', 0, 'AMBI_Update($_IPS[\'TARGET\']);');
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $interval = max(0, $this->ReadPropertyInteger('UpdateInterval'));
        $this->SetTimerInterval('UpdateTimer', $interval * 1000);
        $this->SetValue('Automation', $this->ReadPropertyBoolean('AutomationEnabled'));

        $sourceID = $this->ReadPropertyInteger('SourceVariableID');
        if ($sourceID > 0 && IPS_VariableExists($sourceID)) {
            $this->RegisterMessage($sourceID, VM_UPDATE);
        }

        if (!$this->HasWLEDConfiguration() && !$this->HasHyperHDRConfiguration()) {
            $this->SetStatus(self::STATUS_CONFIGURATION_MISSING);
            return;
        }

        $this->SetStatus(self::STATUS_ACTIVE);
        $this->Update();
        $this->EvaluateSourceVariable();
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data): void
    {
        if ($Message === VM_UPDATE && $SenderID === $this->ReadPropertyInteger('SourceVariableID')) {
            $this->EvaluateSourceVariable();
        }
    }

    public function RequestAction($Ident, $Value): void
    {
        switch ($Ident) {
            case 'Mode':
                $this->SetMode((int) $Value);
                break;
            case 'Automation':
                $this->SetValue('Automation', (bool) $Value);
                if ((bool) $Value) {
                    $this->EvaluateSourceVariable(true);
                }
                break;
            case 'WLEDPower':
                $this->SetWLEDPower((bool) $Value);
                break;
            case 'Brightness':
                $this->SetBrightness((int) $Value);
                break;
            case 'Preset':
                $this->SetPreset((int) $Value);
                break;
            case 'HyperHDREnabled':
                $this->SetHyperHDREnabled((bool) $Value);
                break;
            default:
                throw new InvalidArgumentException('Invalid Ident: ' . $Ident);
        }
    }

    public function Update(): bool
    {
        $success = false;
        if ($this->HasWLEDConfiguration()) {
            $success = $this->UpdateWLED() || $success;
        }
        if ($this->HasHyperHDRConfiguration()) {
            $success = $this->UpdateHyperHDR() || $success;
        }
        if ($success) {
            $this->SetValue('LastUpdate', time());
            $this->SetValue('LastError', '');
            $this->SetStatus(self::STATUS_ACTIVE);
        }
        return $success;
    }

    public function TestWLED(): bool
    {
        return $this->UpdateWLED();
    }

    public function TestHyperHDR(): bool
    {
        return $this->UpdateHyperHDR();
    }

    public function SetMode(int $Mode): bool
    {
        if (!in_array($Mode, [self::MODE_OFF, self::MODE_LIVE, self::MODE_WARM_WHITE, self::MODE_NIGHT, self::MODE_CLEANING], true)) {
            throw new InvalidArgumentException('Unsupported mode: ' . $Mode);
        }

        $ok = true;
        switch ($Mode) {
            case self::MODE_OFF:
                if ($this->HasHyperHDRConfiguration()) {
                    $ok = $this->SetHyperHDREnabled(false) && $ok;
                }
                if ($this->HasWLEDConfiguration()) {
                    $ok = $this->SetWLEDPower(false) && $ok;
                }
                break;
            case self::MODE_LIVE:
                if ($this->HasWLEDConfiguration()) {
                    $ok = $this->SetWLEDPower(true) && $ok;
                }
                if ($this->HasHyperHDRConfiguration()) {
                    $ok = $this->SetHyperHDREnabled(true) && $ok;
                }
                break;
            case self::MODE_WARM_WHITE:
                $ok = $this->ActivateWLEDPreset($this->ReadPropertyInteger('WarmWhitePreset')) && $ok;
                break;
            case self::MODE_NIGHT:
                $ok = $this->ActivateWLEDPreset($this->ReadPropertyInteger('NightPreset')) && $ok;
                break;
            case self::MODE_CLEANING:
                $ok = $this->ActivateWLEDPreset($this->ReadPropertyInteger('CleaningPreset')) && $ok;
                break;
        }

        if ($ok) {
            $this->SetValue('Mode', $Mode);
        }
        return $ok;
    }

    public function SetMediaState(string $State): bool
    {
        $state = trim(mb_strtolower($State));
        $this->SetValue('MediaState', $state);
        if (!(bool) $this->GetValue('Automation')) {
            return true;
        }

        if ($this->ValueMatches($state, $this->ReadPropertyString('PlayingValues'))) {
            return $this->SetMode(self::MODE_LIVE);
        }
        if ($this->ValueMatches($state, $this->ReadPropertyString('PausedValues'))) {
            return $this->SetMode($this->ReadPropertyInteger('PausedMode'));
        }
        if ($this->ValueMatches($state, $this->ReadPropertyString('OffValues'))) {
            return $this->SetMode(self::MODE_OFF);
        }

        $this->Debug('Automation', 'No mapping for media state: ' . $state);
        return false;
    }

    public function SetWLEDPower(bool $State): bool
    {
        return $this->SetWLEDState(['on' => $State]);
    }

    public function SetBrightness(int $Brightness): bool
    {
        return $this->SetWLEDState(['bri' => max(0, min(255, $Brightness))]);
    }

    public function SetPreset(int $Preset): bool
    {
        return $this->SetWLEDState(['ps' => max(0, min(250, $Preset)), 'on' => true]);
    }

    public function SetHyperHDREnabled(bool $State): bool
    {
        $response = $this->HyperHDRRequest('componentstate', [
            'componentstate' => ['component' => 'ALL', 'state' => $State]
        ]);
        if ($response === null || (($response['success'] ?? true) === false)) {
            return false;
        }
        $this->SetValue('HyperHDREnabled', $State);
        return true;
    }

    private function ActivateWLEDPreset(int $Preset): bool
    {
        $ok = true;
        if ($this->HasHyperHDRConfiguration()) {
            $ok = $this->SetHyperHDREnabled(false) && $ok;
        }
        if ($this->HasWLEDConfiguration()) {
            $ok = $this->SetPreset($Preset) && $ok;
        }
        return $ok;
    }

    private function EvaluateSourceVariable(bool $Force = false): void
    {
        if (!$Force && !(bool) $this->GetValue('Automation')) {
            return;
        }
        $sourceID = $this->ReadPropertyInteger('SourceVariableID');
        if ($sourceID <= 0 || !IPS_VariableExists($sourceID)) {
            return;
        }
        $formatted = (string) GetValueFormatted($sourceID);
        $raw = GetValue($sourceID);
        $state = is_string($raw) ? $raw : ($formatted !== '' ? $formatted : (string) $raw);
        $this->SetMediaState($state);
    }

    private function UpdateWLED(): bool
    {
        $response = $this->WLEDRequest('GET', '/json/si');
        if ($response === null || !isset($response['state'], $response['info'])) {
            $this->SetValue('WLEDOnline', false);
            return false;
        }
        $state = (array) $response['state'];
        $info = (array) $response['info'];
        $this->SetValue('WLEDOnline', true);
        $this->SetValue('WLEDPower', (bool) ($state['on'] ?? false));
        $this->SetValue('Brightness', max(0, min(255, (int) ($state['bri'] ?? 0))));
        $this->SetValue('Preset', max(0, (int) ($state['ps'] ?? 0)));
        $this->SetValue('WLEDVersion', (string) ($info['ver'] ?? ''));
        $this->SetValue('WLEDDeviceName', (string) ($info['name'] ?? ''));
        return true;
    }

    private function UpdateHyperHDR(): bool
    {
        $response = $this->HyperHDRRequest('serverinfo');
        if ($response === null || (($response['success'] ?? true) === false)) {
            $this->SetValue('HyperHDROnline', false);
            return false;
        }

        $info = (array) ($response['info'] ?? []);
        $components = (array) ($info['components'] ?? []);
        $all = false;
        $grabber = false;
        $ledDevice = false;
        foreach ($components as $component) {
            if (!is_array($component)) {
                continue;
            }
            $name = strtoupper((string) ($component['name'] ?? $component['component'] ?? ''));
            $enabled = (bool) ($component['enabled'] ?? $component['state'] ?? false);
            if ($name === 'ALL') {
                $all = $enabled;
            }
            if (str_contains($name, 'GRABBER') || str_contains($name, 'V4L')) {
                $grabber = $grabber || $enabled;
            }
            if ($name === 'LEDDEVICE' || str_contains($name, 'LED')) {
                $ledDevice = $ledDevice || $enabled;
            }
        }

        $fps = 0.0;
        foreach (['fps', 'videoFps', 'currentFps'] as $key) {
            if (isset($info[$key]) && is_numeric($info[$key])) {
                $fps = (float) $info[$key];
                break;
            }
        }
        if ($fps === 0.0 && isset($info['grabber']) && is_array($info['grabber'])) {
            foreach (['fps', 'currentFps'] as $key) {
                if (isset($info['grabber'][$key]) && is_numeric($info['grabber'][$key])) {
                    $fps = (float) $info['grabber'][$key];
                    break;
                }
            }
        }

        $version = (string) ($info['version'] ?? $info['hyperhdrVersion'] ?? $response['version'] ?? '');
        $this->SetValue('HyperHDROnline', true);
        $this->SetValue('HyperHDREnabled', $all || $ledDevice);
        $this->SetValue('GrabberActive', $grabber);
        $this->SetValue('LEDDeviceActive', $ledDevice);
        $this->SetValue('FPS', $fps);
        $this->SetValue('HyperHDRVersion', $version);
        return true;
    }

    private function SetWLEDState(array $State): bool
    {
        $response = $this->WLEDRequest('POST', '/json/state', $State);
        if ($response === null) {
            return false;
        }
        return $this->UpdateWLED();
    }

    private function WLEDRequest(string $Method, string $Path, ?array $Payload = null): ?array
    {
        if (!$this->HasWLEDConfiguration()) {
            return null;
        }
        $scheme = $this->ReadPropertyBoolean('WLEDHTTPS') ? 'https' : 'http';
        $url = sprintf('%s://%s:%d%s', $scheme, trim($this->ReadPropertyString('WLEDHost')), $this->ReadPropertyInteger('WLEDPort'), $Path);
        return $this->HTTPRequest($Method, $url, $Payload, [], self::STATUS_WLED_UNREACHABLE, 'WLED');
    }

    private function HyperHDRRequest(string $Command, array $Arguments = []): ?array
    {
        if (!$this->HasHyperHDRConfiguration()) {
            return null;
        }
        $scheme = $this->ReadPropertyBoolean('HyperHDRHTTPS') ? 'https' : 'http';
        $url = sprintf('%s://%s:%d/json-rpc', $scheme, trim($this->ReadPropertyString('HyperHDRHost')), $this->ReadPropertyInteger('HyperHDRPort'));
        $payload = array_merge([
            'command' => $Command,
            'tan' => random_int(1, 2147483647),
            'instance' => max(0, $this->ReadPropertyInteger('HyperHDRInstance'))
        ], $Arguments);
        $headers = [];
        $token = trim($this->ReadPropertyString('HyperHDRToken'));
        if ($token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }
        return $this->HTTPRequest('POST', $url, $payload, $headers, self::STATUS_HYPERHDR_UNREACHABLE, 'HyperHDR');
    }

    private function HTTPRequest(string $Method, string $URL, ?array $Payload, array $ExtraHeaders, int $ErrorStatus, string $Target): ?array
    {
        $headers = array_merge(['Accept: application/json'], $ExtraHeaders);
        $options = [
            CURLOPT_URL => $URL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 7,
            CURLOPT_CUSTOMREQUEST => $Method,
            CURLOPT_HTTPHEADER => $headers
        ];
        if ($Payload !== null) {
            try {
                $json = json_encode($Payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            } catch (JsonException $e) {
                $this->SetError($Target . ': ' . $e->getMessage(), self::STATUS_INVALID_RESPONSE);
                return null;
            }
            $options[CURLOPT_POSTFIELDS] = $json;
            $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
        }
        $this->Debug($Target . ' request', $Method . ' ' . $URL . ' ' . json_encode($Payload));
        $curl = curl_init();
        if ($curl === false) {
            $this->SetError($Target . ': cURL initialization failed', $ErrorStatus);
            return null;
        }
        curl_setopt_array($curl, $options);
        $body = curl_exec($curl);
        $error = curl_error($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        if ($body === false || $error !== '' || $statusCode < 200 || $statusCode >= 300) {
            $this->SetError(sprintf('%s: HTTP %d %s', $Target, $statusCode, $error), $ErrorStatus);
            $this->Debug($Target . ' error', (string) $body);
            return null;
        }
        try {
            $decoded = json_decode((string) $body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->SetError($Target . ': ' . $e->getMessage(), self::STATUS_INVALID_RESPONSE);
            return null;
        }
        if (!is_array($decoded)) {
            $this->SetError($Target . ': invalid response', self::STATUS_INVALID_RESPONSE);
            return null;
        }
        $this->Debug($Target . ' response', json_encode($decoded));
        return $decoded;
    }

    private function SetError(string $Message, int $Status): void
    {
        $this->SetValue('LastError', $Message);
        $this->SetStatus($Status);
    }

    private function HasWLEDConfiguration(): bool
    {
        return trim($this->ReadPropertyString('WLEDHost')) !== '';
    }

    private function HasHyperHDRConfiguration(): bool
    {
        return trim($this->ReadPropertyString('HyperHDRHost')) !== '';
    }

    private function ValueMatches(string $Value, string $CSV): bool
    {
        $values = array_filter(array_map(static fn(string $item): string => trim(mb_strtolower($item)), explode(',', $CSV)));
        return in_array(trim(mb_strtolower($Value)), $values, true);
    }

    private function RegisterProfileInteger(string $Name, string $Icon, string $Prefix, string $Suffix, array $Associations): void
    {
        if (!IPS_VariableProfileExists($Name)) {
            IPS_CreateVariableProfile($Name, VARIABLETYPE_INTEGER);
        }
        IPS_SetVariableProfileIcon($Name, $Icon);
        IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
        foreach ($Associations as $association) {
            IPS_SetVariableProfileAssociation($Name, $association[0], $association[1], $association[2], $association[3]);
        }
    }

    private function Debug(string $Message, string $Data): void
    {
        if ($this->ReadPropertyBoolean('EnableDebug')) {
            $this->SendDebug($Message, $Data, 0);
        }
    }
}
