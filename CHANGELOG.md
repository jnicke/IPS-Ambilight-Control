# Changelog

## 0.5.10

- Neue Variable `WLEDPowerUsage`: Auslastung des Strombudgets in
  Prozent, berechnet aus `WLEDCurrentPower` und `WLEDMaximumCurrent`
- Ohne gesetztes Limit sowie bei Werten über dem Limit bleibt die
  Anzeige in sinnvollen Grenzen (0 bzw. 100 %)
- Hinweis: WLED rechnet den Verbrauch aus den Kanalwerten hoch, es ist
  keine Messung. Der Weißkanal fließt dabei nicht ein, bei warmweißen
  Szenen liegt der tatsächliche Strom also höher

## 0.5.9

- Beim Wechsel in den Live-Modus werden die Segmente der folgenden
  Busse eingeschaltet. War ein Bus zuvor abgeschaltet – etwa durch die
  Standby-Szene der Apple-TV-Automatik –, blieb der Strip dunkel,
  obwohl der HyperHDR-Stream lief: Ein ausgeschaltetes Segment gibt
  ankommende Stream-Daten nicht aus
- Das Einschalten erfolgt vor dem Aktivieren des Grabbers, da Bus 1
  während des Realtime-Betriebs gesperrt ist

## 0.5.8

- App-Name wird nur noch übernommen, wenn der Apple TV dazu auch eine
  App-ID meldet. Beim Verlassen einer Wiedergabe liefert pyatv häufig
  den alten Namen ohne App-ID nach; dieser Wert galt bisher als aktuell
- Neue Variable `AppleTVAppCurrent` (Profil `~Alert.Reversed`): der
  zuletzt bekannte App-Name bleibt sichtbar, wird aber als nicht mehr
  aktuell gekennzeichnet
- Bridge (`app.py`): neues Statusfeld `app_current` nach derselben
  Regel; `PUSH_SIGNIFICANT_KEYS` um das Feld erweitert, damit ein
  Wechsel auch einen WebHook-Push auslöst
- Bridge (`config.json`): neuer optionaler Schlüssel
  `companion_credentials`. Ohne Companion-Pairing verbindet pyatv das
  Protokoll stillschweigend nicht und meldet dauerhaft keine App

## 0.5.7

- Auswahlprofil `AMBI.Effect` für die Effektvariablen aller Busse:
  Effekte werden im Klartext statt als ID ausgewählt
- Die Effektliste wird bei jedem Übernehmen aus `/json/effects` des
  Controllers gelesen und passt sich damit der installierten
  WLED-Version an; reservierte Platzhalter (`RSVD`) werden gefiltert
- Eingebaute Fallback-Liste für die erste Registrierung, solange noch
  keine Controller-Verbindung besteht
- WLED-Client um `getEffects()`, Treiber um `readEffects()` erweitert

## 0.5.6

- Segmente werden beim Verlassen des Live-Modus automatisch
  ent-freezet (`frz`). Ein von HyperHDR eingefrorenes Segment rendert
  nicht aus seinem eigenen Zustand, sondern wartet auf UDP-Daten und
  bleibt dunkel, obwohl Farbe, Helligkeit und Power korrekt gesetzt
  sind
- Zusätzlich sendet jede manuelle Bus-Aktion (Power, Helligkeit,
  Farbe, Effekt) `frz: false` mit, damit ein Segment auch bei direkter
  Ansteuerung sicher auftaut
- `BusUpdate` um Feld und Setter `freeze()` erweitert

## 0.5.5

- Bus 1 war nicht schaltbar: Der Befehl erreichte den Controller, aber
  die zweite Schleife in `StatusManager::applyWLED()` las die
  Segmentzustände erst ab Bus 2 zurück. Die Variablen von Bus 1
  fielen dadurch sofort wieder auf ihren alten Wert

## 0.5.4

- Live-Modus stoppt laufende Effekte auf den Deko-Bussen 2–4, die dem
  Modus nicht folgen. Kaminfeuer oder Regenbogen liefen dort bisher
  weiter und uebermalten manuell gesetzte Farben
- Bus 1 ist außerhalb des Live-Betriebs einzeln steuerbar
  (Ein/Aus, Helligkeit, Farbe, Weißkanal, Effekt)

## 0.5.3

- Vier Schalter `Bus<N>FollowMode`: je Bus lässt sich festlegen, ob er
  dem Ambilight-Modus folgt. Zuvor wirkten alle Modi außer Reinigung
  fest auf Bus 1
- Umschalten wirkt sofort, ohne Moduswechsel

## 0.5.2

- Taster `SyncSegments` zum Auslösen der Segment-Synchronisierung aus
  der Bedienoberfläche

## 0.5.1

- Master-Steuerung des Controllers: `WLEDPower` und `WLEDBrightness`

## 0.5.0

- App-Regeln für die Apple-TV-Automatik: je App konfigurierbarer
  Ambilight-Modus für Wiedergabe und Pause sowie eine Bus-Szene für
  die freien Busse 2–4 (unverändert, aus, warmweiß gedimmt,
  neutralweiß); Auflösung exakter Treffer → Fallback-Regel →
  eingebauter Standard
- Separate Bus-Szene für den Standby-Fall (z. B. Nachtlicht)
- Automatik reagiert jetzt auch auf App-Wechsel bei laufender
  Wiedergabe, nicht nur auf Zustandswechsel
- Bus-Szenen werden als einzelne WLED-Transaktion geschaltet und
  funktionieren auch parallel zum laufenden Live-Stream
- Bridge (`app.py`): veraltete Metadaten (Titel, Interpret, Album,
  Genre, Position) werden bei einem App-Wechsel zurückgesetzt;
  Shutdown-Race in der Prozessverwaltung behoben

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