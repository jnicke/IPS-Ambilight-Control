# IPS-Ambilight-Control

IP-Symcon-Modul zur Steuerung und Überwachung eines WLED-Controllers.

## Funktionsumfang 0.2.0

- Verbindung zu WLED über die offizielle JSON API
- Statusabfrage über `/json/si`
- Ein-/Ausschalten
- Helligkeit von 0 bis 255
- Aufruf von Presets 0 bis 250
- Anzeige von Gerätename, WLED-Version und letztem Update
- konfigurierbares Abfrageintervall
- Verbindungsstatus und Debug-Ausgabe
- deutsche Übersetzung der Moduloberfläche

## Installation

1. Das Repository in IP-Symcon unter **Kerninstanzen > Modules** hinzufügen.
2. Eine Instanz **Ambilight Control** anlegen.
3. Hostname oder IP-Adresse des WLED-Controllers eintragen.
4. Port und Aktualisierungsintervall festlegen.
5. **Verbindung testen** ausführen.

## Öffentliche Modulbefehle

```php
AMBI_Update($InstanceID);
AMBI_TestConnection($InstanceID);
AMBI_SetPower($InstanceID, true);
AMBI_SetBrightness($InstanceID, 128);
AMBI_SetPreset($InstanceID, 1);
```

## Voraussetzungen

- IP-Symcon ab Version 8.0
- WLED mit aktivierter HTTP/JSON-Schnittstelle
- Netzwerkzugriff von IP-Symcon auf den WLED-Controller

## Noch nicht enthalten

HyperHDR, Hyperion, MQTT, Home Assistant und Apple TV sind für spätere Versionen vorgesehen.
