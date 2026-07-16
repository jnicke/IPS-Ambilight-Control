# Changelog

## 0.4.0

- Apple-TV-Integration über die mitgelieferte pyatv-Bridge:
  Statusvariablen für Online, Betriebszustand, Wiedergabestatus,
  App, Titel und letztes Ereignis
- Ereignisempfang per WebHook `/hook/medialight`: Zustandswechsel
  des Apple TV erreichen IP-Symcon in unter einer Sekunde;
  zyklischer Bridge-Abruf dient als Fallback
- Apple-TV-Automatik (schaltbar): playing → Live, paused → Warmweiß,
  standby → Aus; reagiert nur auf Zustandswechsel und hält bei
  Bridge-Ausfall den letzten Modus
- Neuer Instanzstatus 204 „Apple-TV-Bridge nicht erreichbar“,
  Statuslogik auf beliebige Gerätekombinationen erweitert
- Bridge (`app.py`): stdin-Endlosschleife unter systemd behoben
  (offene stdin-Pipe für `atvscript push_updates`) und
  WebHook-Push bei Änderungen von online/power/state/app ergänzt
- Konfigurationsformular um Apple-TV-Bereich und Testschaltfläche
  erweitert

## 0.3.1

- Neue Statusvariable `LastActionError`: Fehler aus Benutzeraktionen
  (Presets, Schalter, Tests, Synchronisierung) werden getrennt vom
  Aktualisierungsfehler `LastError` gehalten und nicht mehr vom
  Statustimer überschrieben
- README und CHANGELOG auf den tatsächlichen Funktionsumfang
  bereinigt, Versionsangaben in `library.json` korrigiert

## 0.3.0

- HyperHDR-Komponentensteuerung: `LED-Gerät aktiv` und
  `Videoaufnahme aktiv` als schaltbare Variablen
  (JSON-RPC `componentstate`)
- Ambilight-Modus-Presets (Profil `AMBI.Mode`): Aus, Live, Warmweiß,
  Nacht, Reinigung, Kaminfeuer, Regenbogen
- Reinigungsmodus sichert den Zustand der Busse 2–4 vor dem
  Übersteuern in einem Snapshot und stellt ihn beim Verlassen wieder
  her (unabhängig von der Statusvariablen-Synchronisierung)
- Moduswechsel warten auf das Ende des WLED-Realtime-Modus, bevor
  Segmente beschrieben werden
- HTTP-Client wiederholt Anfragen bei WLED-Antwort 503 (busy)
  bis zu dreimal
- Bus-Wiederherstellung in einer einzigen WLED-Transaktion

## 0.2.0

- Steuerung der WLED-Busse 2–4: Ein/Aus, Helligkeit, Farbe,
  Weißkanal, Effekt-ID über schaltbare Variablen mit `RequestAction`
- Segment-Synchronisierung aus der physischen WLED-Buskonfiguration
  (Bus 1 → Segment 0 usw.); WLED bleibt Quelle der Wahrheit
- Testfunktion je Bus
- Transaktionsbasierte WLED-Schreibzugriffe mit Schutz von Bus 1
  während des Realtime-Betriebs
- Layout-Konsistenzprüfung: Abgleich Segmente ↔ Busse sowie
  HyperHDR-LED-Anzahl ↔ Länge von Bus 1
  (`SegmentsInSync`, `LayoutHint`)
- cURL auf IPv4 festgelegt, um Timeouts bei Hostnamen zu vermeiden

## 0.1.0

- Installierbares Grundgerüst nach IP-Symcon-SDK
  (`library.json`, `module.json`, PSR-4-Autoloader)
- Konfigurationsformular für WLED und HyperHDR
- Statusvariablen für WLED (Controller, Busse) und HyperHDR
  (Instanz, Grabber, Komponenten, Prioritäten)
- Zyklische Aktualisierung mit Timer und Fehlerbehandlung
- Debug-Protokollierung
- GitHub Action für PHP- und JSON-Prüfung, MIT-Lizenz