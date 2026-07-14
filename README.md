# IPS-Ambilight-Control

IP-Symcon-Modul zur gemeinsamen Steuerung und Überwachung von WLED und HyperHDR.

## Funktionsumfang v0.4.0

- WLED JSON API: Status, Ein/Aus, Helligkeit und Presets
- HyperHDR JSON-RPC: Serverstatus und Aktivieren/Deaktivieren aller Komponenten
- Statusvariablen für WLED, HyperHDR, Grabber, LED-Gerät und FPS
- Betriebsarten: Aus, Live, Warmweiß, Nacht und Reinigung
- Automatische Umschaltung anhand einer beliebigen IP-Symcon-Variable
- Geeignet für Medienstatus aus Home Assistant, MQTT, Apple-TV-Integrationen oder eigenen Skripten
- Deutsch/Englisch, Debug-Ausgabe und zyklische Statusabfrage

## Installation

Das Repository über die Modulverwaltung von IP-Symcon hinzufügen und anschließend eine Instanz **Ambilight Control** anlegen.

## WLED

Hostname/IP und Port eintragen. Die drei Szenen Warmweiß, Nacht und Reinigung werden über vorhandene WLED-Presets abgebildet.

## HyperHDR

Hostname/IP und den Webserver-Port eintragen. Standardmäßig wird Port 8090 und der Endpunkt `/json-rpc` verwendet. Bei geschützter API kann ein Bearer-Token hinterlegt werden.

## Automatik mit Home Assistant, MQTT oder Apple TV

Die Integration ist absichtlich quellenunabhängig. Wähle als **Variable für Medienstatus** eine IP-Symcon-Variable aus, die beispielsweise einen Home-Assistant-`media_player`-Status, ein MQTT-Topic oder den Zustand einer Apple-TV-Integration enthält.

Standardzuordnung:

- `playing`, `play`, `on`, `live` → Live
- `paused`, `pause`, `idle` → konfigurierbarer Pausenmodus
- `off`, `standby`, `stopped`, `unavailable`, `unknown` → Aus

Die Listen sind frei konfigurierbar.

## Öffentliche Modulfunktionen

```php
AMBI_Update($InstanceID);
AMBI_TestWLED($InstanceID);
AMBI_TestHyperHDR($InstanceID);
AMBI_SetMode($InstanceID, 1);             // 0 Aus, 1 Live, 2 Warmweiß, 3 Nacht, 4 Reinigung
AMBI_SetMediaState($InstanceID, 'playing');
AMBI_SetWLEDPower($InstanceID, true);
AMBI_SetBrightness($InstanceID, 128);
AMBI_SetPreset($InstanceID, 1);
AMBI_SetHyperHDREnabled($InstanceID, true);
```

## Hinweise

HyperHDR-Versionen können einzelne Felder in `serverinfo` unterschiedlich strukturieren. Das Modul wertet deshalb mehrere bekannte Feldvarianten defensiv aus. Eine direkte Apple-TV-Netzwerksteuerung ist nicht Bestandteil dieser Version; der Status wird über eine vorhandene Symcon-, Home-Assistant- oder MQTT-Variable eingebunden.
