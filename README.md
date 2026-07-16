# IPS-Ambilight-Control

IP-Symcon-Modul zur gemeinsamen Steuerung von **WLED** und **HyperHDR** –
gedacht für ein Ambilight hinter dem Fernseher, dessen LED-Controller
zusätzlich weitere, frei nutzbare LED-Stränge versorgt.

Aktuelle Version: **0.3.1**

## Konzept

Das Modul folgt zwei Grundsätzen:

1. **WLED ist die Quelle der Wahrheit.** Die physischen LED-Busse
   (Anzahl, Start, Länge, GPIO) werden ausschließlich aus der
   WLED-Konfiguration gelesen. Ändert man die LED-Zahlen im
   WLED-Webinterface, folgt das Modul – es gibt keine doppelte
   Pflege in IP-Symcon.
2. **Bus 1 gehört dem Ambilight, Bus 2–4 sind frei.** HyperHDR streamt
   per UDP auf das WLED-Hauptsegment (Segment 0 = Bus 1). Die Segmente
   der Busse 2–4 bleiben unabhängig über IP-Symcon steuerbar –
   auch während der Live-Stream läuft.

Jedem physischen Bus wird ein gleich großes WLED-Segment zugeordnet
(Bus 1 → Segment 0, Bus 2 → Segment 1 usw.). Die Zuordnung erledigt
die Aktion **„WLED-Segmente mit Buskonfiguration synchronisieren“**;
eine laufende Konsistenzprüfung meldet, wenn Segmente und Busse
auseinanderlaufen oder das HyperHDR-LED-Layout nicht zur Länge von
Bus 1 passt.

## Funktionsumfang

### WLED

- Statusüberwachung (Firmware, LED-Anzahl, Busse, Segmente,
  Realtime-Status, Leistung, Signalqualität u. v. m.)
- Steuerung der Busse 2–4: Ein/Aus, Helligkeit, Farbe (RGB),
  Weißkanal, Effekt-ID – je Bus als schaltbare Variablen
- Segment-Synchronisierung aus der WLED-Buskonfiguration
- Testfunktion je Bus
- Layout-Konsistenzprüfung (`SegmentsInSync`, `LayoutHint`)

### HyperHDR

- Statusüberwachung (Instanz, Grabber, Videomodus, FPS, Komponenten,
  Prioritäten, LED-Anzahl, WLED-Session)
- Schaltbare Komponenten:
  - **LED-Gerät aktiv** – Ambilight-Ausgabe an/aus, der Stream läuft weiter
  - **Videoaufnahme aktiv** – Grabber an/aus (spart CPU; HyperHDR legt
    dabei das LED-Gerät automatisch schlafen, sobald keine Quelle mehr
    aktiv ist)

### Ambilight-Modi (Presets)

Zentrale Variable **„Ambilight-Modus“** mit Profil `AMBI.Mode`:

| Modus | Verhalten |
| --- | --- |
| Aus | Grabber aus, TV-Segment aus |
| Live | Grabber und LED-Gerät an, HyperHDR übernimmt das TV-Segment |
| Warmweiß | Grabber aus, TV-Segment statisch warmweiß (ca. 60 %) |
| Nacht | wie Warmweiß, stark gedimmt (ca. 6 %) |
| Reinigung | alle Segmente Vollweiß 100 % |
| Kaminfeuer | WLED-Effekt „Fire 2012“ auf dem TV-Segment |
| Regenbogen | WLED-Regenbogen-Effekt auf dem TV-Segment |

Besonderheiten:

- Vor dem Beschreiben des TV-Segments wartet das Modul, bis WLED den
  Realtime-Modus verlassen hat (max. 6 s).
- Der Reinigungsmodus sichert vorher den Zustand der Busse 2–4 in
  einem Snapshot und stellt ihn beim Verlassen wieder her –
  einschließlich laufender Effekte.
- Die Farb-, Helligkeits- und Effektwerte der Modi liegen als
  Konstanten in `module.php` und lassen sich dort leicht anpassen.

## Voraussetzungen

- IP-Symcon ab Version 8.0
- WLED (getestet mit 0.14/0.15) mit aktivierter Option
  **Config → Sync Interfaces → Realtime → „Use main segment only“**.
  Ohne diese Option übersteuert der HyperHDR-Stream alle Segmente.
- HyperHDR (getestet mit v22) mit WLED als LED-Gerät (UDP) und
  einem LED-Layout, das ausschließlich den Bereich von Bus 1 abdeckt
- Der Realtime-Override in WLED (`lor`) sollte auf `0` stehen

## Installation

1. In IP-Symcon unter *Kern Instanzen → Modules* das Repository
   hinzufügen: `https://github.com/jnicke/IPS-Ambilight-Control`
2. Instanz **Ambilight Control (MediaLight)** anlegen
3. Hosts konfigurieren (siehe unten) und übernehmen

## Konfiguration

| Eigenschaft | Beschreibung |
| --- | --- |
| Aktiv | Modul und Aktualisierungstimer ein/aus |
| Aktualisierungsintervall | Statusabfrage in Sekunden (Standard 10) |
| Debug | ausführliche Protokollierung in den Debug-Bereich |
| HyperHDR Host/Port/Pfad/Token | JSON-RPC-Zugang (Standard Port 8090, Pfad `/json-rpc`) |
| WLED Host | Hostname oder IP des WLED-Controllers |

## Wichtige Statusvariablen

| Variable | Bedeutung |
| --- | --- |
| `Online`, `Mode`, `LastUpdate` | Gesamtstatus des Moduls |
| `LastError` | letzter Verbindungs-/Aktualisierungsfehler (wird bei erfolgreichem Update geleert) |
| `LastActionError` | letzter Fehler einer Benutzeraktion (bleibt bis zur nächsten erfolgreichen Aktion stehen) |
| `AmbilightMode` | aktives Preset |
| `SegmentsInSync`, `LayoutHint` | Ergebnis der Layout-Konsistenzprüfung |
| `WLEDBus<N>…` | Buskonfiguration und Steuerung je Bus |
| `HyperHDR…` | HyperHDR-Status und Komponentenschalter |

## Roadmap

- **v0.4** Apple-TV-Anbindung: automatische Modusumschaltung bei
  Play/Pause/Standby
- Konfigurierbare Preset-Werte über das Instanzformular
- Diagnose des Grabber-Hosts (CPU, Temperatur, FPS)

## Lizenz

MIT – siehe [LICENSE](LICENSE).