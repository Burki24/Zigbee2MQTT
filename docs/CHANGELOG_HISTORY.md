# Historische Änderungsnotizen

Diese Aufzeichnung wurde aus der bisherigen Haupt-README übernommen. Sie beschreibt auch Zwischenstände und entfernte Funktionen; für den aktuellen Funktionsumfang gelten die Modul-READMEs und der [konsolidierte Changelog](../CHANGELOG.md). Die bisherigen 5.x-Einträge bleiben als Historie erhalten.

## Bisherige Chronik

**Version 6.00:**

Die Änderungen sind anhand der funktionalen Commits chronologisch gegliedert. Automatisch erzeugte Metadaten-Commits sowie reine Screenshot-Korrekturen werden nicht einzeln aufgeführt.

### 10. bis 15. Mai 2026: IPSModuleStrict und moderne Tile-Visualisierung

- Sämtliche Module wurden auf `IPSModuleStrict` migriert. Die Mindestversion wurde abschließend auf IP-Symcon 9.0 angehoben.
- Numeric-, Enum-, Temperatur- und Farbtemperatur-Exposes erhalten passendere moderne Variablendarstellungen, soweit die Exposes die dafür notwendigen Werte liefern.
- Die Kelvin-Farbtemperaturvariable `color_temp_kelvin` nutzt den aus dem Zigbee2MQTT-Mired-Bereich berechneten Kelvin-Bereich für die Symcon-Standardkachel Beleuchtung. Farbtemperatur-Presets bleiben als native Aufzählungsvariable verfügbar.
- Moderne HTML-SDK-Kacheln wurden schrittweise für Heizungen, Schaltaktoren mit Messwerten, Sensoren, Sicherheitsgeräte, Fenstergriffe und Aktionsgeräte ergänzt.
- Heizungs-Kacheln zeigen Ist- und Solltemperatur ohne Ringslider und bedienen die Solltemperatur per Plus-/Minus-Tasten. Später kamen breitere Preset-Tasten und pro Instanz konfigurierbare Solltemperaturen hinzu.
- Schaltaktoren mit Messwerten zeigen Energie, Leistung, Spannung und Strom in einer eigenen Ansicht. Archivierte Werte können direkt aus der Kachel als Graphen geöffnet werden.
- Mehrkanal-Schaltaktoren können mehrere Schaltausgänge in einer gemeinsamen Kachel darstellen. Der Messwertbereich unterstützt auch Frequenz, Leistungsfaktor, Schein-/Blindleistung und erzeugte Energie.
- Sensor-Kacheln unterstützen Temperatur, Luftfeuchtigkeit, Bodenfeuchtigkeit, Helligkeit und Batterie. Bei Pflanzensensoren kann `soil_moisture` als Hauptwert vor der Temperatur dargestellt werden.
- Rollladen und Jalousien verwenden die native Symcon-Shutter-Darstellung. Spezialkacheln werden nur dort ergänzt, wo Exposes eine zusammengefasste Ansicht wirklich benötigen.
- Diagramm-Schaltflächen in Messwert-Kacheln werden nur für tatsächlich archivierte Variablen angezeigt.

### 16. bis 18. Mai 2026: Struktur, Visualisierungsverwaltung und Variablenkatalog

- Die Visualisierungslogik wurde in wiederverwendbare Helper und einen eigenen Verzeichnisbaum unter `libs/Visualization` aufgeteilt.
- Die Verarbeitung in `ModulBase` wurde schrittweise für `RequestAction()`, Payloads, Standardvariablen, Sondervariablen, Farbaktionen, Wertkonvertierung, Presets, Variablendarstellungen und Variablenregistrierung refaktoriert.
- Die Geräte-Konfiguration erhielt einen Visualisierungsbereich, der nur die für die jeweilige Instanz fachlich passenden Kacheloptionen anbietet.
- Temperatur-Visualisierungen können einen konfigurierbaren Fallback-Bereich verwenden, wenn Zigbee2MQTT keine Werte für `value_min` und `value_max` liefert.
- Geräte-Instanzen erhielten einen lokalen Variablenkatalog. Anwender können steuern, welche bekannten Variablen automatisch angelegt werden dürfen. Gelöschte Variablen werden nicht ungefragt erneut erzeugt und können später gezielt wieder freigegeben werden.
- Composite-Exposes werden nur mit tatsächlich anlegbaren Untervariablen geführt. Nicht bedienbare Composite-Eltern erscheinen nicht mehr als eigenständige Variable.
- Automatische Versions- und Build-Anpassungen wurden für neue Commits eingerichtet.

### 19. bis 21. Mai 2026: Geräte-, Gruppen- und Bridge-Funktionen

- Bridge-Requests und Antworten wurden vereinheitlicht und robuster ausgewertet.
- Die Bridge unterstützt zusätzliche OTA-Befehle für Downgrade, Scheduling, Unschedule und eigene OTA-URLs.
- Geräte-Instanzen können Zigbee2MQTT-Geräteoptionen wie `transition`, `debounce`, `filtered_attributes`, `optimistic`, `retain` oder gerätespezifische `definition.options` direkt in der Konfiguration anzeigen und setzen.
- Binding und Reporting können in der Geräte-Konfiguration über Endpoint-, Cluster- und Attributdaten gepflegt werden.
- Reine Tunable-White-Leuchtmittel erhalten eine abgeleitete `color`-Variable ohne Modulprofil, die den aktuellen Weißton über eine passende Farbdarstellung visualisiert.
- Der Kelvin-Bereich der Farbtemperatur kann pro Device überschrieben werden, falls Zigbee2MQTT beziehungsweise dessen Device-Definitionen ungenaue Mired-Grenzen melden.
- Gruppen-Instanzen können Mitglieder einschließlich Endpoints verwalten, Gruppenoptionen setzen und Szenen speichern, hinzufügen, abrufen, umbenennen oder löschen.
- Die Bridge erhielt einen Diagnosebereich für Health Check, Coordinator Check, Bridge-Events, Warnungen und Fehler sowie nicht unterstützte oder unvollständig interviewte Geräte.
- Die Bridge-Wartung erhielt Zigbee2MQTT-Backups, einmaliges Senden von Zigbee-3.0-Install-Codes sowie Touchlink-Scan, Identify und Factory-Reset.
- Sicherheits-Kacheln unterstützen zusätzliche Kontakt- und Alarm-Exposes wie `opening_state`, `alarm_state`, Präsenz-, Sirenen-, Leck-, Gas- und Rauchmelderwerte.
- Eigene Kacheln übernehmen Grundfarben, Schriftfarben und Schriftgrößen vom aktiven Symcon Tile-Theme, damit Hell- und Dunkelmodus mit den Standardkacheln übereinstimmen.
- Fehlende Properties und Attribute werden während Modulupdates tolerant behandelt, damit Bestandsinstanzen sauber migrieren.

### 23. bis 26. Mai 2026: Komfort, Sicherheit und Binding/Reporting

- Gruppenmitglieder lassen sich über filterbare Gerätelisten und automatisch erkannte Endpoints auswählen. Nicht erreichbare Geräte erzeugen ein verständliches Popup.
- Geräte- und Gruppenoptionen verwenden typisierte Editoren für Boolean-, Enum-, Numeric-, Text-, Array- und Objektwerte. Attributfilter wie `filtered_attributes`, `filtered_cache` oder `debounce_ignore` bieten bekannte Payload-Attribute zur Auswahl an.
- Enum-basierte `state`-Variablen wie Rollladenbefehle senden ihre originalen Zigbee2MQTT-Werte wie `OPEN`, `CLOSE` und `STOP`. Binäre Schalter bleiben bei `ON` und `OFF`.
- Die Bridge kann globale Zigbee2MQTT-Blocklist und -Passlist verwalten. Die Auswahl nutzt bekannte Zigbee2MQTT-Geräte sowie vorhandene Device-Instanzen. Passlist-Änderungen werden wegen ihrer restriktiven Wirkung mit einer Sicherheitsabfrage geschützt.
- Farbtemperatur-Funktionen werden nur angeboten, wenn das Gerät tatsächlich ein passendes Expose liefert.
- Vorhandene Bindings und Reportings werden aus dem Zigbee2MQTT-`bridge/devices`-Cache gelesen und übersichtlich dargestellt. Die Bearbeitung erhielt Zielauswahl, unterstützte Cluster, Attributauswahl und besser dimensionierte Tabellen.
- Über **Endpoint-Daten aktualisieren** können Binding- und Reporting-Daten bewusst neu eingelesen werden.
- Das Öffnen der Geräte-Konfiguration wurde beschleunigt, da Binding-Zielauswahlen nicht mehr bei jedem Formularaufbau live über die Symcon-Extension geladen werden.
- Der Abruf von Geräteinformationen wartet länger auf die Symcon-Extension und zeigt bei Erfolg oder Nichterreichbarkeit verständliche Rückmeldungen im Formular.
- Übersetzungen, PHPDocs, READMEs und Screenshots wurden für die neuen Gerätefunktionen vollständig überarbeitet.
- Command-Payloads werden robuster validiert; nicht mehr verwendeter Tile-Code wurde entfernt.

### 27. bis 31. Mai 2026: Bridge-Wartung, Stabilität und zentrale OTA-Verwaltung

- Die Bridge erhielt eine Variablen-Wartung. Sie trennt verwaiste Variablen in klare Löschkandidaten und Review-Kandidaten und schützt archivierte oder referenzierte Variablen.
- Das zwischenzeitlich vorhandene externe Bereinigungsscript wurde entfernt. Die integrierte Variablen-Wartung ist der unterstützte Weg zum Aufräumen verwaister Variablen.
- Konfigurator und Geräteformulare laden schneller und nutzen bevorzugt den Bridge-Cache. Extension-Antworten ohne oder mit nicht passender Transaction-ID werden bei Bedarf über das Response-Topic zugeordnet.
- Die MQTT-Transaktionsverwaltung wurde deadline-basiert und stabiler aufgebaut. Lange Bridge-Antworten wie Zigbee2MQTT-Backups blockieren nicht mehr durch zu kurze Wartezeiten oder instabile Buffer.
- Zigbee2MQTT-Backups werden wegen der Symcon-Ausgabegrenze chunkweise als ZIP-Datei unter `user/IPSZigbee2MQTT/backups` gespeichert. Eine öffentliche Base64-Rückgabe wird bewusst nicht angeboten.
- Die Bridge erhielt eine zentrale OTA-Verwaltung. OTA-fähige Geräte können geprüft, verfügbare Updates gestartet oder geplant und laufende Updates mit Fortschritt, Restzeit und Ergebnisverlauf verfolgt werden. Zum Schutz des Zigbee-Netzes startet die Oberfläche nur ein aktives Update gleichzeitig.
- OTA-Fortschritte werden automatisch aktualisiert. Die Restzeitvariable `update__remaining` nutzt für den von Zigbee2MQTT gelieferten Sekundenwert die native Symcon-Dauerdarstellung.
- Die Bridge-Wartung erhielt einen optionalen lokalen Install-Code-Katalog. Install-Codes können mit einer Bezeichnung gespeichert, maskiert angezeigt, erneut gesendet, bearbeitet und nach Bestätigung gelöscht werden. Sensible MQTT-Payloads und Antworten erscheinen nicht im Debug-Protokoll.
- Die Bridge-Dokumentation wurde in eigene Funktionsblöcke für Diagnose, Netzwerksicherheit, OTA-Updates, Variablen-Wartung sowie Zigbee2MQTT-Wartung mit Backup, Install-Codes und Touchlink gegliedert.

### 1. bis 7. Juni 2026: Erweiterte Ablaufplan-Aktionen, Datenaktualisierung und Bedienkomfort

- Wiederverwendbare Ablaufplan-Aktionen wurden für Status-Umschaltung, Ein-/Ausschalten mit Übergangszeit, Kelvin-Farbtemperatur mit Übergangszeit und Zigbee-Szenenabruf ergänzt.
- Die Kelvin-Aktion verwendet die pro Geräteinstanz ermittelte beziehungsweise überschriebene Farbtemperaturdarstellung und rechnet den gewählten Kelvin-Wert für Zigbee2MQTT in Mired um.
- Bestehende Übergangsaktionen werden nur noch für Zigbee2MQTT-Geräte und -Gruppen angeboten. Die Aktionen sind in Symcon als zielspezifisch kategorisiert und erhielten ergänzte Beschreibungen sowie Übersetzungen.
- Farbübergänge werden für reine Tunable-White-Leuchtmittel nicht als native RGB-Befehle versendet. Deren abgeleitete Farbvariable bleibt eine reine Visualisierungsdarstellung.
- Übersetzungen verwenden während eines laufenden Modul-Updates einen sicheren Originaltext-Fallback. Kurzzeitig noch nicht verfügbare Sprachdateien oder Instanzschnittstellen unterbrechen dadurch keine OTA-Formularaktualisierung mehr.
- Geräte- und Bridge-Formulare erhielten gezielte Aktualisieren-Schaltflächen für den Variablenkatalog, verfügbare Netzwerksicherheitsgeräte und sämtliche OTA-Tabellen. Der manuelle Variablen-Refresh baut den Device-Katalog neu aus aktuellen Exposes und dem zuletzt empfangenen Geräte-Payload auf. Historische fachfremde Einträge verschwinden aus der Liste, ohne vorhandene Symcon-Variablen zu löschen. Bei laut Zigbee2MQTT OTA-fähigen Geräten bleiben stabile OTA-Metadaten erhalten; temporäre Fortschrittswerte werden nur geführt, solange Zigbee2MQTT sie aktuell liefert. Bestehende Symcon-Variablen bleiben für eine kontrollierte Prüfung über die integrierte Variablen-Wartung erhalten.
- Die Variablenverwaltung in Geräteinstanzen aktualisiert die Liste nach Einzelaktionen gezielt, ohne dass das Formular nach jedem Anlegen oder Deaktivieren an den Anfang der Konfiguration springt.
- Von Zigbee2MQTT berechnete oder nachgelieferte Werte können auch bei unvollständiger Expose-Kennung nachträglich angelegt werden. Numeric-, Binary- und Enum-Variablen ergänzen fehlende `name`- oder `property`-Felder typunabhängig aus der jeweils vorhandenen Kennung.
- Der von Zigbee2MQTT berechnete Taupunkt `dewpoint` wird bei nachträglicher Anlage als übersetzte Float-Variable `Taupunkt` ohne Modulprofil, aber mit passender Temperaturdarstellung angelegt.
- Geräteinstanzen prüfen eingehende MQTT-Topics zusätzlich zum Symcon-Datenfilter selbst. Fremde Geräte-Payloads können dadurch auch bei unerwarteter Zustellung keine Variablen in der falschen Instanz anlegen.
- Gruppeninstanzen erhielten **Gruppeninformationen aktualisieren**. Damit werden extern in Zigbee2MQTT geänderte Mitglieder, Gruppenoptionen und Szenen erneut eingelesen und direkt in der geöffneten Symcon-Konfiguration angezeigt.
- Die Discovery behält bei internen MQTT-Servern alle gleichzeitig gefundenen Zigbee2MQTT-Basen bei. Veraltete Konstanten, Locale-Einträge und irreführende PHPDocs aus der früheren dateibasierten Expose-Verwaltung wurden bereinigt.
- Die zentrale OTA-Verwaltung bietet nur noch Geräte an, die Zigbee2MQTT ausdrücklich mit `supports_ota` kennzeichnet. Historische `update__*`-Variablen allein führen nicht mehr zu falschen OTA-Angeboten.
- Die OTA-Zentrale kann geplante Updates weiterhin per `unschedule` aus der Planung nehmen und nutzt zusätzlich den neuen Zigbee2MQTT-Abbruch-Endpunkt, um angeforderte oder laufende OTA-Updates abzubrechen.
- Health Check und Coordinator Check zeigen bei nicht erreichbarem Zigbee2MQTT in der Bridge-Konfiguration eine lesbare Diagnosemeldung statt einer technischen Timeout-Notice.
- Weitere Zigbee2MQTT-Gerätelabels und Werte für Kalibrierung, Dimmverhalten, Helligkeitsschwellen, Impulssteuerung und Pulsaktionen wurden ins Deutsche übersetzt. Sprachneutrale numerische Werte wie `1x`, `2x` oder `3x` werden nicht mehr als fehlende Übersetzung gemeldet.
- Die Konfigurationsformulare wurden nach Nutzungshäufigkeit gegliedert: Geräte bündeln Optionen, Variablen sowie Binding und Reporting unter erweiterten Einstellungen; die Bridge zeigt zentrale Statusschaltflächen direkt und trennt Diagnose sowie OTA von administrativen und Expertenwerkzeugen; Gruppenoptionen liegen unter erweiterten Gruppeneinstellungen. Der Konfigurator zeigt Gruppen standardmäßig eingeklappt und blendet selten benötigte Gerätespalten aus.
- Die direkt sichtbaren Bridge-Statusschaltflächen übernehmen beim Öffnen zuverlässig den zuletzt ermittelten Zustand der Symcon-Erweiterung, der `last_seen`-Einstellung und der `permit_join`-Konfiguration.
- Fehlende deutsche Übersetzungen für Ventiladaption, Display-Einschaltdauer, Fehlerzustand sowie Temperatur- und Displayauswahlwerte von Thermostaten wurden ergänzt.
- Geräte- und Gruppeninformationen lassen sich über einheitlich benannte, direkt sichtbare Aktualisierungsaktionen neu aus Zigbee2MQTT einlesen; die doppelte Gruppenaktion unter den Expertenwerkzeugen wurde entfernt.
- Gruppenoptionen werden ohne unnötige Zwischenebene direkt neben Gruppenmitgliedern und Szenen angezeigt.
- Geräteinstanzen erhielten eine bestätigungspflichtige Gerätewartung. Ein erneutes Interview liest Endpoints, Cluster und Basisattribute neu ein; eine erneute Konfiguration stößt die gerätespezifische Zigbee2MQTT-Konfiguration für Bindings und Reporting erneut an. Lange Requests sowie nicht erreichbare oder nicht konfigurierbare Geräte werden mit verständlichen Ergebnisdialogen behandelt.
- Die Bridge erhielt eine erweiterte Pairing-Steuerung. Der Netzwerkbeitritt kann für eine wählbare Dauer über das gesamte Netzwerk, den Coordinator oder einen bestimmten Router geöffnet werden; Ziel, Endzeit und verbleibende Zeit werden in Statusvariablen und der Bridge-Konfiguration angezeigt.
- Geräteinstanzen bieten unter den Expertenwerkzeugen eine bestätigungspflichtige erweiterte Geräteentfernung. Geräte können regulär entfernt, nur aus der Zigbee2MQTT-Datenbank zwangsweise entfernt oder nach dem Entfernen zusätzlich blockiert werden; Symcon-Instanz und Variablen bleiben dabei erhalten.
- Das neue eigenständige Modul **Zigbee2MQTT Netzwerkkarte** analysiert die Netzwerktopologie asynchron als RAW-Daten, zeigt Geräte, gerichtete LQI-Verbindungen, Routen und Scanfehler an und bietet eine interaktive HTML-SDK-Kachel. RAW-, Graphviz- und PlantUML-Exporte werden lokal aus der letzten Analyse erzeugt und lösen keinen weiteren Zigbee-Netzwerkscan aus.
- Die Netzwerkkarten-Kachel reserviert den Symcon-Titelbereich, richtet Status, Zusammenfassung und Filter responsiv aus und blendet vor der ersten Analyse noch nicht nutzbare Bedienelemente aus.
- Live-Aktualisierungen der Netzwerkkarten-Kachel werden als JSON-Zeichenkette an das HTML-SDK übertragen und dort sicher normalisiert, sodass Scanstart, Fortschritt und Ergebnisse ohne Typkonvertierungswarnung dargestellt werden.
- Die grafische Netzwerkkarte filtert Verbindungen zu unbekannten oder nicht im Scan enthaltenen Knoten, lädt Cytoscape unabhängig von der HTML-SDK-Umgebung und verwendet für große Netzwerke ein schnelles konzentrisches Layout. Darstellungsfehler werden sichtbar in der Kachel ausgegeben.
- Die HTML-SDK-Netzwerkkarte reagiert auf Größen- und Sichtbarkeitsänderungen, verwendet die verfügbare Viewport-Höhe und fordert beim Öffnen den aktuell gespeicherten Zustand an. Das initiale HTML-Grundgerüst bleibt unabhängig von der Netzwerkgröße klein; die Topologie wird erst nach dem Laden sicher übertragen. Weil das Symcon-PHP-SDK keine eigene HTML-Darstellung für die maximierte Instanzansicht anbietet, stellt die Karte für die große grafische Ansicht einen eigenen Vollbildmodus bereit.
- Die Netzwerkkarten-Kachel bietet mehrere Layouts, eine Gerätesuche, fokussierte Umfeldansichten und optional ausblendbare Beschriftungen.
- Standardlayout und anfängliche Sichtbarkeit der Beschriftungen können im Abschnitt **Ansicht** der Netzwerkkarten-Instanz dauerhaft konfiguriert werden.
- Der eigene Vollbildmodus der Netzwerkkarte leitet aus der aktuellen Symcon-Schriftfarbe automatisch eine kontrastierende Vollbildfläche ab, sodass Beschriftungen sowohl im hellen als auch im dunklen Profil lesbar bleiben.

### 8. bis 12. Juni 2026: Dokumentation und Schnittstellenbereinigung

- Die READMEs aller enthaltenen Module wurden mit den tatsächlich verfügbaren Formularbereichen, Aktionen und öffentlichen Funktionen abgeglichen.
- Aktuelle und einheitlich nummerierte Screenshots erläutern die Konfiguration von Bridge, Konfigurator, Geräten, Gruppen und Netzwerkkarte.
- Die Bridge-Dokumentation beschreibt Netzwerksicherheit, Diagnose, Anlernen, OTA-Verwaltung und Variablen-Wartung anhand der aktuellen Bedienoberfläche.
- Veraltete Sprungmarken der Hauptdokumentation wurden korrigiert und sämtliche lokalen README-Verweise sowie Bilddateien geprüft.
- Die interne Cache-Funktion `Z2M_GetCachedNetworkDevices` wurde in der Bridge-Funktionsreferenz ergänzt.
- Ausschließlich intern genutzte Hilfsmethoden des Konfigurators sind nicht länger als öffentliche Modul-Funktionen sichtbar.
- Die in `library.json` hinterlegte Mindestversion wurde an die dokumentierte und erforderliche Mindestversion IP-Symcon 9.0 angeglichen.
- Der Konfigurator zeigt und verwaltet ausschließlich Instanzen mit demselben MQTT-Splitter und MQTT-Basistopic. Instanzen anderer Splitter bleiben vollständig unberücksichtigt; passende Geräte und Gruppen des aktuellen Zigbee2MQTT-Netzes werden regulär zur Erstellung angeboten.
- Gruppeninstanzen begrenzen auch ihre lokale Geräteauswahl auf denselben MQTT-Splitter und dasselbe MQTT-Basistopic. Mehrere Zigbee2MQTT-Systeme können dadurch keine Geräte des jeweils anderen Netzes als Gruppenmitglied anbieten.
- Eine beim Abruf von Geräteinformationen erkannte IEEE-Adresse wird in bestehenden Instanzen ausschließlich als noch nicht gespeicherter Formularwert eingetragen. Erst das reguläre **Übernehmen** der Instanzkonfiguration speichert die Adresse; das Modul ändert oder übernimmt die Eigenschaft nicht selbstständig.
- Fehlende Bridge-Instanzen werden ausschließlich über den regulären Symcon-Konfigurator erstellt. Formularskripte erzeugen oder konfigurieren keine Instanzen mehr direkt.
- MQTT-Befehle brechen während des kurzen Instanzschnittstellen-Wechsels eines Modul-Updates kontrolliert ab. Laufende Ereignisse erzeugen dadurch keine `InstanceInterface is not available`-Warnungen und senden keine unvollständigen MQTT-Topics.
- Gerätebilder werden modellbezogen unter `user/IPSZigbee2MQTT/icons` zwischengespeichert und nur beim Öffnen der Geräte-Konfiguration geladen. Bestehende Base64-Bildattribute werden automatisch migriert, wodurch `IPS_GetSnapshot()` und darauf aufbauende Visualisierungen deutlich weniger Arbeitsspeicher benötigen.

### 13. bis 15. Juni 2026: Instanzbezogene Variablen-Wartung

- Ausgehende MQTT-Befehle verwenden wieder den von Zigbee2MQTT abonnierten Topic-Baum ohne führenden Slash. Geräte- und Gruppenaktionen erreichen dadurch den konfigurierten Zigbee2MQTT-Basistopic wieder korrekt.
- Binäre Statusaktionen schreiben Zigbee2MQTT-Werte wie `ON` und `OFF` nach dem Senden wieder typgerecht als Boolean in die Symcon-Variable. Dadurch springt ein ausgeschalteter Status nicht mehr durch PHPs String-Konvertierung unmittelbar auf `Ein` zurück.
- Die Variablen-Wartung folgt den Instanz- und Systemgrenzen: Die Bridge zeigt nur noch eine kompakte, nach Geräten und Gruppen desselben MQTT-Splitters und MQTT-Basistopics zusammengefasste Übersicht. Prüfung und bestätigtes Löschen erfolgen direkt in der zuständigen Instanz, die ausschließlich ihre eigenen direkten Variablen verwalten darf.
- Expertenwerkzeuge in Geräte- und Gruppeninstanzen nutzen die verfügbare Formularbreite. Die instanzbezogene Variablen-Wartung erscheint bei Geräten direkt unterhalb der erweiterten Geräteentfernung.
- Dynamisch erzeugte Texte der instanzbezogenen Variablen-Wartung werden vollständig übersetzt. Die Dokumentation erläutert Suchlauf-Hinweise als diagnostische Meldungen für übersprungene oder unvollständig prüfbare Instanzen.
- Erkannte IEEE-Adressen werden review-konform nur noch in das Konfigurationsformular eingetragen. Sie werden ausschließlich durch das reguläre **Übernehmen** der Instanzkonfiguration gespeichert.
- Bridge-Suche, Binding-Ziele, OTA-Verwaltung und Netzwerksicherheitslisten berücksichtigen neben dem MQTT-Basistopic immer auch den tatsächlich verbundenen MQTT-Splitter. Mehrere Zigbee2MQTT-Systeme bleiben dadurch selbst bei identischem Basistopic vollständig voneinander getrennt.
- Der Konfigurator listet und verändert keine Zigbee2MQTT-Instanzen fremder MQTT-Splitter mehr. Der bisherige Reparaturdialog wurde entfernt; Geräte und Gruppen des aktuellen Netzes werden stattdessen ausschließlich über den regulären Symcon-Konfigurator zur Erstellung angeboten.
- Die Bridge-Erreichbarkeitsprüfung wartet bei ausgelasteten Zigbee2MQTT-Systemen bis zu 20 Sekunden auf den Options-Request und unterdrückt technische Zwischen-Notices. Die reine Konfigurator-Übersicht bleibt dagegen bewusst kurz wartend, damit frische Installationen und alte Extensions die Oberfläche nicht blockieren.
- Die Discovery lässt den Anwender über `mqtt://` oder `mqtts://` ausdrücklich zwischen unverschlüsseltem MQTT und TLS wählen. Bei TLS werden Zertifikat und Hostname standardmäßig geprüft; für lokale Broker mit selbstsignierten Zertifikaten können beide Prüfungen bewusst deaktiviert werden. Ein automatischer Rückfall auf eine unverschlüsselte Verbindung findet nicht statt.
- Bereits vorhandene Geräte- und Gruppeninstanzen werden im Konfigurator wieder korrekt als weiterhin von Zigbee2MQTT erkannte Einträge dargestellt. Die rote Symcon-Markierung bleibt damit ausschließlich tatsächlich nicht mehr gefundenen Instanzen vorbehalten.
- Die zwischenzeitliche Diagnose für alte Modulprofile wurde entfernt, weil das Modul keine neuen dynamischen Modulprofile mehr erzeugt oder repariert. Neue und erneut registrierte Variablen verwenden native Symcon-Darstellungen oder bleiben ohne Modulprofil.
- Die Variablen-Wartung zeigt pro Instanz erkannte Darstellungswechsel mit vorherigem Profil und neuer Darstellung an, damit die Umstellung nachvollziehbar bleibt.
- Die Testcenter von Bridge, Geräte- und Gruppeninstanzen befinden sich als eigenständige Bereiche auf der obersten Formularebene und sind nicht mehr in Erweiterungs- oder Expertenmenüs verschachtelt.
- Das dadurch leere Bridge-Untermenü **Expertenwerkzeuge** wurde entfernt; Dokumentation und Regressionstests wurden an die einheitliche Formularstruktur angepasst.
- Bestehende Variablenprofile werden bei der Umstellung weder verändert noch gelöscht. Benutzerdefinierte Profile und Darstellungen bleiben unangetastet; das Modul setzt nur seine neue Standarddarstellung über die reguläre Variablenregistrierung.
- Die öffentlichen Funktionsreferenzen für Geräte und Gruppen dokumentieren `Z2M_CommandExt()` und den Rückgabewert von `Z2M_ReadValue()` jetzt entsprechend den tatsächlich bereitgestellten Schnittstellen.
- Gerätebilder werden mit geprüftem TLS, einem Timeout von fünf Sekunden und einer Größenbegrenzung von zwei MiB geladen. Nur technisch lesbare PNG-Dateien mit begrenzten Bildabmessungen werden gespeichert oder aus einem bestehenden Cache übernommen.

### 20. Juni 2026: Review-konforme Variablendarstellungen

- Empfohlene moderne Variablendarstellungen werden ausschließlich als Modul-Standarddarstellung über die `RegisterVariable*`-Methoden gesetzt. Bestehende benutzerdefinierte Darstellungen bleiben bei `ApplyChanges`, Payloads und Expose-Aktualisierungen unverändert.
- Das Modul ruft produktiv kein `IPS_SetVariableCustomPresentation()` mehr auf und bietet keine Aktion mehr an, um vorhandene Custom-Presentations nachträglich zu überschreiben oder zu entfernen. Damit bleibt die Darstellungs-Hoheit nach der Erstellung vollständig beim Anwender.
- Neue Variablen verwenden bevorzugt moderne RegisterVariable-Darstellungen oder bleiben ohne Modulprofil. Symcon-Standardprofile werden nicht mehr als Modulvorgabe gesetzt.
- Geräte- und Gruppeninstanzen protokollieren in der lokalen Variablen-Wartung nachvollziehbar, bei welchen vorhandenen Variablen ein altes `Z2M.*`-Profil durch eine moderne Symcon-Darstellung ersetzt wurde. Benutzerdefinierte Profil- oder Darstellungsanpassungen werden dabei nur angezeigt und nicht überschrieben.
- Nicht beschreibbare Enum-/String-Statusvariablen erhalten keine interaktive Aufzaehlungsdarstellung mehr, weil diese in Symcon eine Variablenaktion voraussetzt. Schreibbare Enums bleiben weiterhin als bedienbare Aufzaehlung erhalten.
- Bestehende Variablen werden beim erneuten Registrieren nicht mehr kuenstlich an alte Modulprofile gebunden. Wenn eine passende moderne Darstellung verfuegbar ist, wird diese als Modul-Standarddarstellung gesetzt; andernfalls bleibt die Variable ohne Modulprofil. Benutzerdefinierte Profile und Darstellungen bleiben unangetastet.
- Die Geräte-Konfiguration zeigt keine eigene Aktion zum erneuten Anwenden empfohlener Darstellungen mehr. Die Dokumentation beschreibt stattdessen die Trennung zwischen initialer Modul-Empfehlung und späterer Benutzeranpassung.
- Die Hauptdokumentation trennt die Begriffe Spezialkachel/Visualisierung, Variablendarstellung und Variablenprofil ausdrücklich, damit eigene HTML-SDK-Kacheln nicht mit Symcon-Variablendarstellungen oder Profilen vermischt werden.
- Die Discovery kann `mqtts://`-Broker mit lokalen selbstsignierten Zertifikaten erreichen, wenn der Anwender die TLS-Zertifikats- oder Hostnamenprüfung bewusst deaktiviert. Sichere TLS-Prüfung bleibt der Standard und es gibt keinen automatischen Fallback auf `mqtt://`.

### 21. Juni 2026: Stabilisierung frischer Installationen

- Der Konfigurator nutzt für die reine Startübersicht wieder kurze Fünf-Sekunden-Abfragen wie in Version 5.43. Lange Timeouts bleiben Detail-, Backup- und Wartungsfunktionen vorbehalten, damit eine frische oder noch alte Symcon-Extension die Geräte- und Gruppenliste nicht minutenlang blockiert.
- Antworten der SymconExtension werden zusätzlich über ihr Response-Topic einer offenen Anfrage zugeordnet, wenn eine alte, fehlende oder nicht mehr passende `transaction` im Payload steht. Dadurch können Configurator, Geräte- und Gruppenformulare auch mit Legacy- oder retained Extension-Antworten wieder Geräte- und Gruppenlisten auswerten.
- Der Konfigurator verarbeitet nur noch die für ihn relevanten MQTT-Antworten und protokolliert große Geräte- und Gruppenlisten nicht mehr vollständig als Debug-JSON.
- Die Symcon-Extension stellt für den Konfigurator eine kompakte `getDevicesLight`-Listenabfrage bereit. Detaildaten wie Exposes, Endpoints, Geräteoptionen und OTA-Informationen bleiben in der regulären Geräteabfrage sowie beim gezielten Abruf der Geräteinformationen erhalten. Dadurch sinkt der Speicherbedarf bei frischen Installationen und großen Zigbee2MQTT-Netzen deutlich, ohne Binding-, OTA- oder Gruppenfunktionen zu beschneiden.
- Der Bridge-Formularaufbau fragt Geräte für Anlernen und Netzwerksicherheit nicht mehr mehrfach synchron über die Symcon-Extension ab. Beim Öffnen werden Cache und vorhandene Instanzen genutzt; die Live-Abfrage erfolgt nur noch über die explizite Aktualisieren-Aktion. Der automatische Bridge-Onlinecheck beim Übernehmen nutzt wieder einen kurzen Fünf-Sekunden-Timeout.

### 22. Juni 2026: Standarddarstellungen für bestehende Variablen

- Beim Übernehmen einer Geräte- oder Gruppeninstanz werden vorhandene Expose-Variablen erneut mit ihrer aktuellen Modul-Standarddefinition registriert. Dadurch können vorhandene Variablen von Legacy-Profilen auf passende Symcon-Standarddarstellungen wechseln, ohne benutzerdefinierte Darstellungen zu überschreiben.
- Schreibbare numerische Kalibrierungswerte wie `temperature_calibration` und `local_temperature_calibration` nutzen nun ebenfalls die native Symcon-Schiebereglerdarstellung mit Min-, Max- und Schrittwerten aus dem Expose.
- Preset-Variablen wie `color_temp_presets` nutzen nun eine native Symcon-Aufzählungsdarstellung und erzeugen keine dynamischen `Z2M.*_Presets`-Profile mehr.
- Abgeleitete Kelvin-Farbtemperaturvariablen übernehmen bei bestehenden Variablen aktualisierte Konfigurationswerte für den Kelvin-Bereich und bleiben damit mit den Geräteoptionen der Instanz synchron.
- Die letzten dynamischen Profil-Fallbacks fuer generische numerische Werte, Binary-Exposes, State-Aufzaehlungen, Verfuegbarkeitsstatus sowie Bridge-Loglevel und Bridge-Neustart wurden auf native Symcon-Darstellungen umgestellt. Neue Variablen erzeugen dadurch keine Profile wie `Z2M.ac_frequency`, `Z2M.target_distance`, `Z2M.state.*`, `Z2M.DeviceStatus` oder `Z2M.bridge.*` mehr.
- Bestehende Variablen mit alter Modulprofil-Zuweisung werden beim erneuten Registrieren nicht mehr auf dieses Profil zurueckgefuehrt. Wenn eine passende Darstellung verfuegbar ist, wird diese als Modul-Standarddarstellung gesetzt; andernfalls bleibt die Variable ohne Modulprofil. Benutzerdefinierte Profile und Darstellungen bleiben unangetastet.

### 27. bis 29. Juni 2026: Umstellung auf native Symcon-Darstellungen

- **Kompatibilitätsänderung gegenüber 5.42:** Die automatische Anlage und Pflege dynamischer `Z2M.*`-Variablenprofile ist entfernt. Neue und erneut registrierte Variablen verwenden nach Möglichkeit native Symcon-Variablendarstellungen oder bleiben ohne Modulprofil. Vorhandene Variablen, Objekt-IDs und bestehende Profile werden nicht automatisch gelöscht.
- RGB-, HS- und XY-Farb-Exposes registrieren ihre Farbvariable ohne Modulprofil mit der nativen Symcon-Farbdarstellung für Hex/sRGB-Farbwerte. Die Symcon-Standardkachel für RGB-Leuchtmittel kann dadurch den ColorHex-Wert verwenden, ohne dass `~Color` oder ein dynamisches `Z2M.*`-Profil benötigt wird.
- Reine Tunable-White-Leuchtmittel behalten ihre abgeleitete `color`-Variable ohne Modulprofil. Diese Variable nutzt nun ebenfalls die native Hex/sRGB-Farbdarstellung, damit der berechnete Weißton in der Tile-Visualisierung als Farbe nutzbar bleibt.
- Composite-Farb-Exposes wie `color_xy`, `color_hs` und `color_rgb` werden weiterhin auf die passende Farbvariable zusammengeführt. Technische Untervariablen werden dabei nicht angelegt.
- `last_seen` verwendet als Modul-Standard nun die native Symcon-Darstellung **Datum/Uhrzeit**. `update__remaining` bleibt eine Restdauer in Sekunden und verwendet die native Darstellung **Dauer**.
- Text-Exposes wie Heiz- und Wochenpläne verwenden nun die native Symcon-Darstellung **Textbox**, damit lange String-Werte in der Tile-Visualisierung besser lesbar und bearbeitbar sind.
- Bodenfeuchtewerte wie `soil_moisture` werden wie Luftfeuchtigkeit als Prozentwerte erkannt und in der Sensor-Kachel auch dann mit `%` dargestellt, wenn Zigbee2MQTT keine Einheit im Expose liefert.
- Helligkeitswerte (`brightness`) nutzen die native Symcon-Schiebereglerdarstellung mit der Verwendung **Intensität** nur noch, wenn das Expose schreibbar ist. Dadurch kann die Symcon-Standardkachel für RGB- und RGBW-Leuchtmittel Helligkeit zusätzlich zu Farbe und Farbtemperatur anbieten. Reine Statuswerte bleiben eine einfache Wertdarstellung.
- Die Heizungs-Kachel erkennt neben `occupied_heating_setpoint` nun auch `current_heating_setpoint` als Solltemperatur. Thermostate wie `TRV06`, die diesen Zigbee2MQTT-Ident verwenden, werden dadurch korrekt als Heizung dargestellt und geschaltet.
- Spezialkacheln und der Debug-Export formatieren Variablenwerte nur noch über Symcon, wenn hinterlegte Altprofile noch existieren. Dadurch lösen gelöschte alte `Z2M.*`-Profile keine Laufzeitwarnungen mehr aus; die Kacheln fallen stattdessen auf eine einfache Wertdarstellung zurück.
- Die lokale Variablen-Wartung löscht Kandidaten jetzt aus dem gespeicherten Suchlauf und aktualisiert die Tabellen fehlertolerant. Dadurch wird beim Löschen verwaister Variablen kein unmittelbarer Neu-Scan der Instanz ausgelöst und Symcon-Formularfehler durch inzwischen entfernte oder unerwartete Darstellungen werden abgefangen.

### 2. bis 6. Juli 2026: Bridge-Expertenaktionen

- Die Bridge-Wartung bietet eine gewarnte Expertenfunktion für dokumentierte Zigbee2MQTT-`bridge/request/action`-Actions.
- Der Payload wird als `action` plus `params` gesendet; die `transaction` wird weiterhin vom Modul verwaltet.
- Action-Name und JSON-Parameter werden geprüft und mit lokalisierten Rückmeldungen im Formular quittiert.
- Eine eigene Dokumentation für die mitgelieferten Symcon-Actions ergänzt. Sie beschreibt Verfügbarkeit, Voraussetzungen, Parameter, Anwendung in Symcon und passende PHP-Skriptbeispiele.
- Readme-Dateien der einzelnen Module überarbeitet.
- Transaktionsdaten werden intern in einem chunked Buffer gespeichert. Große Zigbee2MQTT-Antworten, zum Beispiel `getDeviceInfo` bei Installationen mit vielen Geräten, überschreiten dadurch nicht mehr die Symcon-Buffergrenze und erzeugen keine falschen Timeout-Meldungen mehr.
- OTA-Formularlisten werden während eines Modul-Updates nur noch aktualisiert, wenn die Symcon-Formularschnittstelle verfügbar ist. Dadurch erzeugen OTA-Statusänderungen während `VM_UPDATE` keine `InstanceInterface is not available`-Warnungen mehr.
- Verwaiste interne Variablenregistrierungen werden vor einer Neuanlage bereinigt. Dadurch führen bereits gelöschte Maintained-Variablen beim Update nicht mehr zu `Variable #... existiert nicht`-Warnungen.

### 18. September 2026: Unabhaengiger Farb- und Helligkeitsvertrag

- Farbaktionen veraendern eine separat exponierte Helligkeit nicht mehr implizit. CIE-, HS- und HSV-Payloads enthalten nur dann weiterhin einen abgeleiteten `brightness`-Wert, wenn das Geraet keine eigenstaendige Helligkeits-Property anbietet.
- Schwarz (`0x000000`) definiert keinen Farbton und wird bei separater Helligkeit in diesen Modi ohne MQTT-Befehl behandelt. Farbe und Helligkeit bleiben erhalten; zum Ausschalten dienen Schaltzustand oder Helligkeit. Das gilt auch fuer Gruppen und Farbaktionen mit Uebergangszeit.
- Ohne separates Helligkeits-Expose werden HS- und HSV-Helligkeiten ueber die vorhandene Bereichsumrechnung in Geraetewerte umgerechnet. Der HSV-Fallback liest dabei den tatsaechlich gelieferten `value`-Wert.
- Die XY-Rueckkonvertierung verwendet jetzt die inverse Matrix der vorhandenen RGB-zu-XY-Konvertierung und skaliert ausserhalb des RGB-Farbraums liegende Kanaele gemeinsam. Dadurch bleibt die gemeldete Chromatizitaet beim Roundtrip stabil, ohne sie mit der separaten Geraetehelligkeit zu vermischen.

### 9. bis 17. Juli 2026: Robuste Payload-Verarbeitung, ueckmeldebasierte Aktionsverarbeitung, Textdarstellungen und Übersetzungen, Sicherheit, Stabilität und Performance

- Numerisch indizierte Root-Payloads ohne Zigbee2MQTT-Property werden beim Payload-Import jetzt ignoriert. Dadurch lösen Geräte, die einzelne Werte oder Listenfragmente ohne Variablen-Ident senden, keinen `TypeError` in der Variablenverarbeitung mehr aus.
- Lesbare Set-Aktionen wie Schalter, Helligkeit, Farbtemperatur und andere Statuswerte aktualisieren lokale Symcon-Werte erst nach einer Rueckmeldung von Zigbee2MQTT.
- Reine Schreib- und Befehlswerte ohne eigene Rueckmeldung, zum Beispiel Szenen, Presets, Effekte oder andere `access: 2`-Funktionen, merken nach erfolgreichem Senden weiterhin den zuletzt gewaehlten Wert lokal.
- Helligkeitsaktionen nutzen dieselbe Rueckmeldepruefung wie andere Standardaktionen und geben Sendefehler wieder korrekt an die Aktion zurueck.
- Auch unveraenderte Payload-Werte werden bei jeder empfangenen Zigbee2MQTT-Nachricht erneut in die zugehoerige Symcon-Variable geschrieben. Dadurch bleiben Automationen kompatibel, die auf eine Wertaktualisierung statt ausschliesslich auf eine Wertaenderung reagieren, beispielsweise bei Radar- und Bewegungsmeldern.
- Schreibgeschützte Textvariablen erhalten keine Darstellung mehr, die eine Variablenaktion voraussetzt. Dadurch entfallen die entsprechenden Kompatibilitätsfehler in der Variablenkonfiguration.
- Beschreibbare Textvariablen verwenden die native mehrzeilige Werteingabe anstelle der nicht vom klassischen WebFront konvertierbaren Text-Box-Darstellung.
- 26 Übersetzungen wurden ergänzt und eine bestehende Übersetzung überarbeitet. Dies umfasst insbesondere Wetterwerte wie Wind, Böen, Niederschlag, Taupunkt, gefühlte Temperatur, Hitze- und Luftfeuchtigkeitsindex sowie zusätzliche Geräte-, Betriebs- und Zeitmodi.
- Fehlende Übersetzungen von Enum- und Preset-Beschriftungen werden auch bei den nativen Symcon-Darstellungen wieder erkannt. Sobald ein nicht übersetzter Wert gefunden wurde, erscheint in den Expertenwerkzeugen der Geräte- beziehungsweise Gruppeninstanz erneut die Schaltfläche **Fehlende Übersetzungen anzeigen**.
- Zugangsdaten, Installcodes, Tokens, Schlüssel und eingebettete URL-Zugangsdaten werden in Discovery-Debugausgaben rekursiv maskiert. Die unveränderte Übermittlung dieser Werte an Zigbee2MQTT bleibt davon unberührt.
- Variablenaktionen werden vollständig mit den aktuellen Schreibrechten eines Exposes synchronisiert. Nicht mehr beschreibbare Variablen verlieren ihre Standardaktion; explizite Aktionskonfigurationen haben weiterhin Vorrang.
- `LastPayload` führt partielle MQTT-Nachrichten nun korrekt zusammen: neue Werte ersetzen alte Werte, nicht erneut gesendete Felder verschachtelter Objekte bleiben erhalten und Listen werden vollständig ersetzt.
- Die laufende OTA-Anzeige wird bei Statusänderungen wieder automatisch aktualisiert. Schutzprüfungen verhindern Fehler, wenn Formular oder Instanz während eines Reloads vorübergehend nicht erreichbar sind.
- Die MQTT-Transaktionsverwaltung verhindert ID-Kollisionen und das Überschreiben offener Anfragen, validiert Antwort-IDs und schützt sämtliche Pufferzugriffe mit zuverlässig freigegebenen Sperren.
- `SendGetCommand()` fordert nur noch Properties an, die laut Zigbee2MQTT-Expose tatsächlich per `GET` lesbar und nicht gefiltert sind. Leere oder ausschließlich schreibbare Anfragen werden nicht gesendet.
- Änderungen am Variablenkatalog werden während Payload-, Expose-, Aktualisierungs- und Wiederaufbauvorgängen gesammelt und höchstens einmal persistiert. Unveränderte Kataloge verursachen keinen erneuten Attributschreibzugriff.
- Die Discovery zeigt sofort den zuletzt bekannten Stand an und aktualisiert einen älter als 60 Sekunden gewordenen Cache asynchron. Eine zusätzliche Schaltfläche ermöglicht weiterhin eine manuelle Aktualisierung.
- Die automatisierten Regressionstests wurden für die neuen Schutzmechanismen und für die bereits entfernte Profil-Diagnose des Bridge-Formulars angepasst und erweitert.
- Umfangreiche Verantwortlichkeiten wurden ohne beabsichtigte Funktionsänderung aus `Bridge/module.php` und `libs/ModulBase.php` in spezialisierte Helper ausgelagert.
- Die Bridge-Helper kapseln Konfiguration, Geräte, Gruppen und Szenen, Diagnose, Requests, OTA, Netzwerksicherheit, Installcodes, Backups, Pairing, veraltete Variablen und Touchlink.
- Die Modul-Helper kapseln Gerätebefehle und -aktionen, Payload-Verarbeitung und -struktur, Expose-Registrierung, Variablenwerte, Variablenlaufzeit und sichere Runtime-Zugriffe.
- Für eine einheitliche Verzeichnisstruktur liegen die Bridge-Helper unter `Bridge/Helper` und die Helper der Modulbasis unter `libs/ModulHelper`.
- Ein einheitliches Helper-Tracing wurde in `Bridge/module.php` und `libs/ModulBase.php` ergänzt. Im Symcon-Debug erscheinen diese Einträge unter der Meldung `HelperTrace` und nennen den tatsächlich zuständigen Helper sowie dessen Operation, beispielsweise `PayloadVariableHelper::processVariable` oder `BridgeOTAFormHelper::CheckOTAUpdate`.
- Jeder instrumentierte Aufruf beginnt mit `Helper::Operation [START]` und endet mit `Helper::Operation [END] | Result=...`. Tritt eine Exception auf, wird stattdessen `Helper::Operation [ERROR] | Exception=... | Code=...` ausgegeben und der Fehler anschließend unverändert weitergereicht. Damit ist erkennbar, in welchem Verarbeitungsschritt ein Ablauf begonnen, erfolgreich beendet oder abgebrochen wurde.
- Das Tracing umfasst insbesondere Bridge-Aktionen, Konfigurationsformulare, MQTT-Antworten, OTA- und Wartungsfunktionen sowie Katalog-, Expose- und Payload-Verarbeitung. Verschachtelte Payload-Abläufe kennzeichnen zusätzlich den jeweils aufgerufenen Unter-Helper, sodass sich Fehler nicht nur auf die allgemeine Payload-Verarbeitung, sondern beispielsweise auf Struktur-, Sonderwert- oder Variablenverarbeitung eingrenzen lassen.
- Als Kontext werden ausschließlich begrenzte technische Angaben wie Ident, Aktionsname, Topic-Kategorie oder Anzahl von Elementen protokolliert. Vollständige Payloads, Variablenwerte, Zugangsdaten, Passwörter und Installcodes werden vom Helper-Tracing bewusst nicht ausgegeben; lange Kontextangaben werden gekürzt. Das Tracing verändert weder MQTT-Payloads noch die fachliche Verarbeitung.
- Die PHPDocs des gesamten eigenen PHP-Codes wurden vereinheitlicht und auf Deutsch inhaltlich überarbeitet. Fehlende Beschreibungen in Bridge-, Payload-, Variablen-, Wartungs-, Farb-, Netzwerkkarten- und Simulatorfunktionen wurden ergänzt; eingebundener Fremdcode und Symcon-Test-Stubs bleiben unverändert.

**Version 5.42:**

- Bridge Instanz konnte den Namen der bereits installierten Erweiterung nicht korrekt erkennen und übernehmen.
- Diverse Übersetzungen ergänzt.
- Neue Funktion Z2M_ReadValue und Z2M_SendGetCommand.
- Diverse spezielle Funktionen im globalen Namensraum aufrufen, um Compiler-Optimierung zu ermöglichen.

**Version 5.40:**

- Einheiten in Profilen wurden teilweise nicht als UTF8 String an Symcon übergeben.
- Explizites Token-Mapping für häufige Zeichenketten bei booleschen Werten. Verhindert false positives bei Erkennung von Strings, wie z.B. `OFF` welches zu true umgewandelt wurde.
- Fehlerhafte Typisierung bei mehrdeutigen Features wie der Position (z.B. `position` numerischer vs. Enum-/String-Geräte) wird verhindert.
- Gefilterte Attribute aus Z2M werden in Symcon nicht mehr als Variablen angelegt. (Danke an JosVanHaag für den PR)
- Fehlende Übersetzungen vom Gerät S8 ergänzt.

**Version 5.39:**

- Fehlende Übersetzungen vom Gerät Senoro.Win v2 ergänzt.

**Version 5.38:**

- Fehlende Übersetzungen von den Geräten 501.40, BMCT-SLZ, S4SW-001P8EU und WT-A03E ergänzt
- Discovery Instanz liefert die ganze Kette für Symcon 9.x

**Version 5.37:**

- Bridge Instanz erkannte aktuelle Z2M Versionen falsch.

**Version 5.36:**

- phpMQTT Bibliothek aktualisiert um Verbindungsprobleme der Discovery-Instanz zu beheben.

**Version 5.35:**

- Fehlende Übersetzungen von den Geräten PS-S04D und MTD285-ZB ergänzt.
- interne Modul Tests erweitert um fehlende Übersetzungen zu erkennen.

**Version 5.34:**

- Das `&` Zeichen wird bei feature / Property zu `_and_` ersetzt.

**Version 5.33:**

- Bei composite wurde versucht für eine nicht vorhandene Hauptvariable eine Aktion zu setzen.
- Das `&` Zeichen wird bei Profilen gefiltert.
- Readme aktualisiert.

**Version 5.31:**

- Fehlermeldung Profil Z2M.AutoLock existiert nicht behoben
- Bridge Instanz erkennt ZH Version 6.X
- Alle Instanzen mit einer "Occupancy"/"Bewegung" Variable unterstützen, sofern in Z2M eingerichtet, auch die "No Occupancy Since"/"Keine Bewegung seit" Variable
- interne Modul Tests erweitert

**Version 5.26:**

- Diverse Fixes betreffend der Fehlermeldungen Undefined array key
- Die Aktion "Helligkeit mit Übergang" war defekt
- Geändertes Verhalten beim schalten der Farbe, basierend auf dem aktiven Farbmodus
- Color Datenempfang um Hue / Saturation ergänzt
- Bridge Instanz erkennt ZH Version 5.X

**Version 5.25:**

- Erste Version als stable im Store erhältlich
- Letzte Änderung war nun das Entfernen von Debug Meldungen aus dem Logfile

**Version 5.22:**

- Durch das aktiveren von Include device information in Z2M werden keine Variablen mehr in Symcon angelegt

**Version 5.20:**

- Diverse Übersetzungen ergänzt (Nachträglich werden diese bei Variablen nicht angepasst!)
- Fix für Smoke Profile (~Alert)
- Fix für Boolean Profile, wo Variablen als Boolean und Profile als String angelegt wurden
- Dateiname des Debug Download enthält den Modelnamen

**Version 5.19:**

- Diverse Übersetzungen ergänzt (Nachträglich werden diese bei Variablen nicht angepasst!)
- contact, tamper Variablen erhalten korrekte Standard-Profile (~Window.Reserved bzw ~Alert)
- Fix für color_temp_kelvin Variable

**Version 5.18:**

- Preset Variablen (Voreinstellungen) zeigen den zuletzt empfangenen / gesendeten Wert an
- Übersetzungen von Profil zu Voreinstellungen geändert. (Hat keinen Einfluss auf vorhandenen Variablen)

**Version 5.17:**

- Das Debug Download war teilweise defekt

**Version 5.16:**

- Instanzen welche als Topic einen Anfang von anderen Topics enthielten, haben falsche Daten empfangen und verarbeitet (z.B. Topic "Flur" hat auch Daten von Topics "Flur 01", "Flur 02", "Flur hinten" verarbeitet)

**Version 5.15:**

- Erweiterung bei Update Variablen
- Einführung der Instanz-Funktionen Z2M_WriteValueBoolean, Z2M_WriteValueInteger, Z2M_WriteValueFloat und Z2M_WriteValueString für PHP-Skripte

**Version 5.13:**

- Erweiterung der Variablen-Erstellung auf die ‚list‘-Exposes, welche vorher nicht beachtet wurden
- fehlende Übersetzungen ergänzt
- Fehler bei Discovery Instanz sollte behoben sein

**Version 5.12:**

- Array und Composite Variablen (z.b. Update, Level-Config usw.) sind Variablen verfügbar

**Version 5.11:**

- Child Lock konnte nicht geschaltet werden
- einige Text Variablen wurden nicht angelegt (z.B. die Schedule Variablen)
- Fehlende Übersetzungen ergänzt (werden nur beim neu Anlegen von Variablen/Profilen berücksichtigt)
- Debug Download bei Gruppen war defekt
- JSON Datei für fehlende Übersetzungen konnte kaputt gehen
- Fehlende Übersetzungen werden im Debug Download einbezogen
- Fehlende Übersetzungen können in der Instanz-Konfig angezeigt werden (nur wenn es welche gibt)

**Version 5.10:**

- Fix für nicht vorhandene Profile bei Text Datentypen

**Version 5.09:**

- Fix für 32-Bit Int zu Float Überlauf bei last_seen behoben

**Version 5.08:**

- diverse fixes für die Migration → einige Idents konnten nicht übertragen werden (z.B. Z2M_SmokeDensityDBM, Z2M_Window_OpenFeature, Z2M_PiHeatingDemand etc)
- Variablen welche aufgrund eines (früher) falschen Variablentyps nicht migriert werden können, werden übersprungen
- last_seen wird immer als integer behandelt.
- calibration_time wird immer auf float und countdown* immer auf int gemappt
- Debug JSON um unnötige Verschachtlungen reduziert

**Version 5.05:**

- Debug Download eingeführt
- Discovery Instanz verfügbar
- Konfigurator erkennt falsch zugeordnete MQTT-Server/Clients

**Version 5.01:**

- diverse Profile von float zu int umgestellt
- Extension filtert Gruppen ohne Namen aus (vermutlich Reste aus alten Z2M Versionen)
- Migrate hat State Variablen nicht korrekt verarbeitet

**Version 5.00:**

- Kompatibilität mit Zigbee2MQTT Version 2.0 hergestellt
- Geräte erkennen automatisch die Features und Exposes und erstellen die benötigten Variablen mit den entsprechenden Profilen eigenständig
  - Somit keine missing exposes Debugs mehr nötig!
- Nutzung von Standard-Symcon Profilen, soweit möglich
- Presets und Effekte als Variablen verfügbar
- Array und Composite Variablen (z.b. Update, Level-Config usw.) sind Variablen verfügbar.
- Geräte speichern die IEEE um umbenannte Geräte (= geändertes Topic) zu erkennen
- Z2M Prefix bei VariablenIdents entfernt
- Konfigurator übernimmt die MQTT Topic-Struktur beim Anlegen von Geräten als Kategorien
- Konfigurator erkennt fehlende Bridge-Instanz
- Konfigurator erkennt falsche Topics (anhand der IEEE Adresse der Geräte)
- Bridge installiert die Extension nicht mehrfach
- Bridge installiert automatisch die benötigte Extension
- Komplettes Code-Rework für Geräte und Gruppen von Bruki24
- Diverse Aktionen für die Instanzen der Geräte und Gruppen:
  - Relatives Dimmen der Helligkeit
  - Schrittweises Dimmen der Helligkeit
  - Relatives Dimmen der Farbtemperatur
  - Schrittweises Dimmen der Farbtemperatur
  - Ein-/Ausschaltverzögerung

