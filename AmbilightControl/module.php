<?php

declare(strict_types=1);

class AmbilightControl extends IPSModule
{
    private const STATUS_ACTIVE = 102;
    private const STATUS_INACTIVE = 104;
    private const STATUS_HOST_MISSING = 201;
    private const STATUS_UNREACHABLE = 202;
    private const STATUS_INVALID_RESPONSE = 203;

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyString('WLEDHost', '');
        $this->RegisterPropertyInteger('WLEDPort', 80);
        $this->RegisterPropertyInteger('UpdateInterval', 10);
        $this->RegisterPropertyBoolean('EnableDebug', false);

        $this->RegisterVariableBoolean('Online', $this->Translate('Online'), '~Switch', 10);
        $this->RegisterVariableBoolean('Power', $this->Translate('Power'), '~Switch', 20);
        $this->EnableAction('Power');

        $this->RegisterVariableInteger('Brightness', $this->Translate('Brightness'), '~Intensity.255', 30);
        $this->EnableAction('Brightness');

        $this->RegisterVariableInteger('Preset', $this->Translate('Preset'), '', 40);
        $this->EnableAction('Preset');

        $this->RegisterVariableString('Version', $this->Translate('WLED version'), '', 50);
        $this->RegisterVariableString('DeviceName', $this->Translate('Device name'), '', 60);
        $this->RegisterVariableInteger('LastUpdate', $this->Translate('Last update'), '~UnixTimestamp', 70);

        $this->RegisterTimer('UpdateTimer', 0, 'AMBI_Update($_IPS[\'TARGET\']);');
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $host = trim($this->ReadPropertyString('WLEDHost'));
        $interval = max(0, $this->ReadPropertyInteger('UpdateInterval'));
        $this->SetTimerInterval('UpdateTimer', $interval * 1000);

        if ($host === '') {
            $this->SetStatus(self::STATUS_HOST_MISSING);
            $this->SetValue('Online', false);
            return;
        }

        $this->SetStatus(self::STATUS_ACTIVE);
        $this->Update();
    }

    public function RequestAction($Ident, $Value): void
    {
        switch ($Ident) {
            case 'Power':
                $this->SetPower((bool) $Value);
                break;

            case 'Brightness':
                $this->SetBrightness((int) $Value);
                break;

            case 'Preset':
                $this->SetPreset((int) $Value);
                break;

            default:
                throw new InvalidArgumentException('Invalid Ident: ' . $Ident);
        }
    }

    public function Update(): bool
    {
        $response = $this->Request('GET', '/json/si');
        if ($response === null) {
            return false;
        }

        if (!isset($response['state'], $response['info']) || !is_array($response['state']) || !is_array($response['info'])) {
            $this->SetStatus(self::STATUS_INVALID_RESPONSE);
            $this->SetValue('Online', false);
            $this->Debug('Update', 'WLED response does not contain state and info');
            return false;
        }

        $state = $response['state'];
        $info = $response['info'];

        $this->SetValue('Online', true);
        $this->SetValue('Power', (bool) ($state['on'] ?? false));
        $this->SetValue('Brightness', max(0, min(255, (int) ($state['bri'] ?? 0))));
        $this->SetValue('Preset', max(0, (int) ($state['ps'] ?? 0)));
        $this->SetValue('Version', (string) ($info['ver'] ?? ''));
        $this->SetValue('DeviceName', (string) ($info['name'] ?? ''));
        $this->SetValue('LastUpdate', time());
        $this->SetStatus(self::STATUS_ACTIVE);

        return true;
    }

    public function TestConnection(): bool
    {
        return $this->Update();
    }

    public function SetPower(bool $State): bool
    {
        return $this->SetState(['on' => $State]);
    }

    public function SetBrightness(int $Brightness): bool
    {
        $Brightness = max(0, min(255, $Brightness));
        return $this->SetState(['bri' => $Brightness]);
    }

    public function SetPreset(int $Preset): bool
    {
        $Preset = max(0, min(250, $Preset));
        return $this->SetState(['ps' => $Preset]);
    }

    private function SetState(array $State): bool
    {
        $response = $this->Request('POST', '/json/state', $State);
        if ($response === null) {
            return false;
        }

        return $this->Update();
    }

    private function Request(string $Method, string $Path, ?array $Payload = null): ?array
    {
        $host = trim($this->ReadPropertyString('WLEDHost'));
        if ($host === '') {
            $this->SetStatus(self::STATUS_HOST_MISSING);
            $this->SetValue('Online', false);
            return null;
        }

        $port = max(1, min(65535, $this->ReadPropertyInteger('WLEDPort')));
        $url = sprintf('http://%s:%d%s', $host, $port, $Path);

        $headers = ['Accept: application/json'];
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CUSTOMREQUEST => $Method,
            CURLOPT_HTTPHEADER => $headers
        ];

        if ($Payload !== null) {
            $json = json_encode($Payload, JSON_THROW_ON_ERROR);
            $options[CURLOPT_POSTFIELDS] = $json;
            $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
        }

        $this->Debug('HTTP Request', $Method . ' ' . $url . ($Payload === null ? '' : ' ' . json_encode($Payload)));

        $curl = curl_init();
        if ($curl === false) {
            $this->SetStatus(self::STATUS_UNREACHABLE);
            return null;
        }

        curl_setopt_array($curl, $options);
        $body = curl_exec($curl);
        $error = curl_error($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($body === false || $error !== '' || $statusCode < 200 || $statusCode >= 300) {
            $this->Debug('HTTP Error', sprintf('Code: %d; Error: %s; Body: %s', $statusCode, $error, (string) $body));
            $this->SetStatus(self::STATUS_UNREACHABLE);
            $this->SetValue('Online', false);
            return null;
        }

        try {
            $decoded = json_decode((string) $body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->Debug('JSON Error', $exception->getMessage());
            $this->SetStatus(self::STATUS_INVALID_RESPONSE);
            $this->SetValue('Online', false);
            return null;
        }

        if (!is_array($decoded)) {
            $this->SetStatus(self::STATUS_INVALID_RESPONSE);
            $this->SetValue('Online', false);
            return null;
        }

        $this->Debug('HTTP Response', json_encode($decoded));
        return $decoded;
    }

    private function Debug(string $Message, string $Data): void
    {
        if ($this->ReadPropertyBoolean('EnableDebug')) {
            $this->SendDebug($Message, $Data, 0);
        }
    }
}
