# Unveröffentlicht

[Deutsch](CHANGELOG.md) | [English](CHANGELOG.en.md)

Dieser Changelog beschreibt Version **6.1** für Symcon 9.1. Die Ausgangsbasis für die gemeinsame Entwicklung der 6.x-Reihe war die veröffentlichte Beta **5.43** (`ce8aba0de27c8e9a6ffbaa91759009fb34d11806`).

> [!CAUTION]
> **Beim Wechsel von 5.x wird „Erkennen“ als neue String-Variable angelegt.** Die neue Variable erwartet `identify` statt `true`. Bestehende Skripte, Links und Automationen müssen bei Bedarf auf die neue Variablen-ID umgestellt werden. Die alte Bool-Variable bleibt mit ihrer ID, ihrem Wert, ihrer bisherigen Sichtbarkeit und ihren Benutzeranpassungen erhalten; nur die veraltete Modul-Standardaktion wird deaktiviert. Benutzerdefinierte Aktionen bleiben erhalten. Es gibt keine automatische Weiterleitung alter Modulaufrufe.

Bereits durch ältere Testing-Versionen ausgeblendete Variablen oder entfernte Benutzeraktionen werden nicht automatisch wiederhergestellt: Die vorherigen Einstellungen sind nicht gespeichert und müssen bei Bedarf anhand eines Backups geprüft werden.

## 6.1 gegenüber dev_V6

- `Symcon-9.1` setzt Symcon 9.1 voraus. Unter Symcon 9.0 kann `dev_V6` weiter genutzt werden. Beim Wechsel zuerst Symcon aktualisieren und dann die vorhandene Modulbibliothek auf den neuen Zweig umstellen.
- Zeilenauswahl übernimmt das Gerät und den Endpoint für Gruppenmitglieder sowie die Angaben für Gruppenszenen, Gruppenoptionen, Geräteoptionen, Bindings, Reportings und Netzwerksicherheit. Änderungen werden über eigene Aktionen ausgeführt.
- Die Assistenten für Binding und Reporting senden erst nach der letzten Bestätigung. Erweiterte Reporting-Parameter in JSON werden nur bei eingeblendeten erweiterten Optionen mitgesendet.
- Der Binding-Assistent deaktiviert Cluster, die laut bekannten Endpoint-Daten nicht zur gewählten Geräteverbindung passen. Geräte- und Gruppenoptionen zeigen direkt das passende Eingabefeld für Zahl, Schalter, Auswahl, Attributliste oder Text und JSON.
- Die Netzwerkkarte nutzt natives Vollbild und zeigt die Verbindungen des ausgewählten Geräts in beiden Richtungen. Sie kann die zugehörige Geräteinstanz öffnen und bei unterstützten Routern eine Routing-Tabelle gezielt abfragen. Die Geräteauswahl verwendet gespeicherte Daten und startet keinen Scan.
- Der zuletzt beobachtete Weg zum Koordinator erscheint mit Zeitstempel in Gerätedetails und Netzwerkkarte, sobald die Extension einen Pfad erfasst hat. Z-Stack wird ab Extension 6.10 und Ember ab 6.11 unterstützt. Mitgeliefert sind 6.11 für Zigbee2MQTT 2.x und 6.09 für 1.x.
- Start und Planung eines OTA-Updates zeigen im selben Bestätigungsdialog das Gerät, die Aktion und die beiden Firmware-Versionen. Der Auftrag wird erst nach Bestätigung übertragen.
- Die Bridge speichert Zigbee2MQTT-Backups außerhalb des WebServer-Verzeichnisses. Sie übernimmt die bisherigen Modul-Backups beim Anwenden der Konfiguration und erhält gleichnamige Dateien.

## Neu

- Mindestversion Symcon 9.1; alle sechs Module verwenden `IPSModuleStrict`. Vor einem Update von 5.x oder früherem Testing ist ein vollständiges Backup erforderlich; ein Downgrade erfolgt nur durch dessen Wiederherstellung.
- Native Symcon-Darstellungen für Schalter, Aufzählungen, Messwerte, Helligkeit, Farbe, Rollläden, Dauer und Datum/Uhrzeit ersetzen automatisch erzeugte Modul-Standardprofile. Benutzerdefinierte Profile und Darstellungen haben weiterhin Vorrang. Alte `Z2M.*`-Profile werden nicht global gelöscht; profilabhängige Skripte müssen geprüft werden.
- Eigene, an das Symcon-Theme angepasste Kacheln für Heizungen, Schaltaktoren mit optionalen Messwerten und mehreren Kanälen, Sensoren, Sicherheitsgeräte, Fenstergriffe und Aktionen. Archivdiagramme werden nur für aufgezeichnete Variablen angeboten. Leuchten und Rollläden verwenden native Symcon-Darstellungen; separate RGB-/Tunable-White-HTML-Kacheln gehören nicht zum aktuellen Lieferumfang.
- Heizungs-Vorgabewerte sowie Fallback-Temperaturbereiche und Kelvin-Grenzen lassen sich pro Geräteinstanz einstellen. Abgeleitete Kelvin- und Farbwerte unterstützen passende native Lichtdarstellungen und Voreinstellungen.
- Lokaler Variablenkatalog mit Einzel- und Mehrfachauswahl, gezielter Anlage, Deaktivierung, Wiederfreigabe manuell gelöschter Variablen und Anzeige von Darstellungswechseln. Ein manueller Neuaufbau berücksichtigt aktuelle Exposes und bekannte Payloads.
- Instanzbezogene Variablen-Wartung erkennt verwaiste Variablen. Archivierte oder referenzierte Variablen sind geschützt; jede Löschung wird erneut geprüft und einzeln bestätigt. Die Bridge liefert eine nach MQTT-Verbindung und Basistopic getrennte Übersicht, löscht aber nicht zentral.
- Geräteoptionen mit typisierten Editoren für Boolean, Enum, Zahl, Text, JSON und Attributlisten. Bekannte Werte und gerätespezifische Optionsdefinitionen werden zusammen angezeigt.
- Binding und Reporting mit Endpoint-, Cluster-, Attribut- und Zielauswahl, Anzeige bestehender Konfiguration und explizitem Aktualisieren der Endpoint-Daten aus dem passenden Bridge-Cache.
- Gerätewartung für erneutes Interview, Neukonfiguration und bestätigtes normales, erzwungenes oder blockierendes Entfernen aus Zigbee2MQTT. Die Symcon-Instanz wird dabei nicht automatisch gelöscht.
- Gruppenverwaltung für Mitglieder einschließlich Endpoints, Gruppenoptionen und Zigbee-Szenen. Ein Klick auf eine Zeile der Mitglieder- oder Geräteliste übernimmt Gerät und Endpoint. Gruppenauswahlen bleiben auf die passende MQTT-Verbindung beschränkt.
- Neues Netzwerkkarten-Modul mit asynchronem Scan, Topologie, Verbindungsqualität, Routing, Suche, Filtern, einstellbarer Ansicht, nativer Symcon-Vergrößerung und Export. Eine vollständige Netzwerkanalyse erfordert Bestätigung und kann das Zigbee-Netz vorübergehend belasten.
- Zentrale OTA-Verwaltung für ausdrücklich unterstützte Geräte: Prüfungen, Updates, Downgrades, eigene Firmware-URLs, Planung, Aufheben der Planung, Abbruch, Fortschritt, Restzeit und Ergebnisübersichten. Die Oberfläche verhindert mehrere gleichzeitig gestartete Updates.
- Erweiterte Bridge-Diagnose mit Health Check, Coordinator Check, Events, Warnungen, Fehlern sowie nicht unterstützten oder unvollständig interviewten Geräten.
- Erweiterte Anlernsteuerung mit Zielauswahl und Zeitbegrenzung sowie Verwaltung von Blocklist und Passlist. Restriktive Passlist-Änderungen erfordern eine Sicherheitsbestätigung.
- Zigbee2MQTT-Backups werden als ZIP-Datei auf dem Symcon-Server gespeichert. Große Antworten werden begrenzt und stückweise verarbeitet; ein direkter Base64-Export über die öffentliche PHP-Schnittstelle wird nicht angeboten.
- Install-Code-Katalog mit maskierter Anzeige, erneutem Senden und bestätigtem Löschen sowie Touchlink-Scan, Identify und bestätigtem Factory-Reset. Gespeicherte Install-Codes sind nicht verschlüsselt und können Bestandteil von Symcon-Backups sein.
- Bestätigte Bridge-Expertenaktionen für ausgewählte administrative Befehle, einschließlich klarer Rückmeldungen und bewusst gewählter JSON-Parameter.
- Zusätzliche Ablaufplan-Actions für Umschalten, Übergänge, Kelvin-Farbtemperatur und Szenenabruf; Übergangsaktionen sind auf passende Geräte- und Gruppeninstanzen begrenzt.
- Vollständige englische Haupt- und Modul-Anleitungen, synchronisierte Beta-/Migrationshinweise und ein konsolidierter Changelog. Frühere chronologische Entwicklungsnotizen bleiben als ausdrücklich gekennzeichnete Historie verfügbar.
- Entwicklungswerkzeuge für isolierte MQTT-Gerätesimulation und die Suche fehlender Übersetzungen sowie deutlich erweiterte Regressionsprüfungen und interne Helper-Diagnose.

## Fixes

- Verspätete MQTT-Antworten mit Transaktions-ID können nach einem Timeout keine neue Anfrage mehr durch eine wiederverwendete ID abschließen. Die ID-Vergabe bleibt auch nach Konfigurationsänderungen und Neustarts fortlaufend.

- Die neue Erkennen-Migration wurde zusätzlich im echten Symcon-9.1-Testsystem mit sichtbaren und ausgeblendeten Altvariablen, Benutzerprofilen, Darstellungen, funktionierenden Benutzeraktionen und wiederholter Ausführung geprüft.
- Farb- und Helligkeitsbefehle sind unabhängig: Farbbefehle übernehmen nicht ungefragt die aktuelle Helligkeit. Schwarz und fehlende beziehungsweise unbrauchbare Helligkeitswerte werden korrekt behandelt; HSV-/RGB-Konvertierungen wurden abgesichert.
- Native Zahlenparameter werden passend zum Integer-/Float-Typ registriert und erfüllen die strengere Validierung in Symcon 9.1. Einheiten, Millivolt-/Volt-Umrechnung und nachgelieferte Temperatur-/Taupunktwerte werden konsistent behandelt.
- Schreibgeschützte Exposes erhalten keine Bedienaktion. Beschreibbare Textwerte nutzen die kompatible Werteingabe; Status-Texte erhalten keine aktionspflichtige Darstellung. Voreinstellungen behalten Bruchwerte und besondere Enum-Werte, vermeiden Dubletten und lassen sich gezielt wiederherstellen.
- Schaltwerte werden nicht vorzeitig als erfolgreich gesetzt; wiederholte empfangene Werte aktualisieren wieder den Schreibzeitpunkt. Partielle MQTT-Payloads werden zusammengeführt, verschachtelte Objekte bleiben erhalten und Listen werden vollständig ersetzt. GET-Abfragen berücksichtigen nur lesbare und nicht gefilterte Properties.
- Große Exposes-, Geräte-, Discovery- und Transaktionsdaten verwenden begrenzte beziehungsweise komprimierte Puffer. Formulare nutzen Caches, Discovery kann veraltete Daten asynchron aktualisieren und Katalogänderungen werden gesammelt statt mehrfach gespeichert.
- Konfiguratoren und Gruppenlisten unterscheiden MQTT-Verbindungen auch bei gleichem Basistopic. Bereits zugeordnete Instanzen bleiben bei fehlendem physischem Gerät sichtbar; Konfigurator-Anlage, Umzug und Eigenschaftsänderungen benötigen die entsprechende Anwenderauswahl.
- Gerätebild-Downloads prüfen TLS, Größe und PNG-Format. Direkte Discovery-Verbindungen über MQTT-TLS prüfen Zertifikat und Hostnamen, sofern dies nicht ausdrücklich deaktiviert wird; es erfolgt kein automatischer unverschlüsselter Rückfall. Zugangsdaten, Tokens und Install-Codes werden in den vorgesehenen Diagnoseausgaben maskiert.
- Exposes-Migrationen behalten Wiederherstellungsdaten, Benutzeranpassungen und kontrollierte Fehlerzustände. Kurzzeitig nicht verfügbare Instanzschnittstellen während Modulupdates führen nicht mehr zu den bekannten Formular-/Timer-Warnungen. OTA-Statusmeldungen verursachen keine rekursive MessageSink-Flut mehr.
- Weitere deutsche Geräte-, Energie-, Schutz-, Wetter- und Modusübersetzungen wurden ergänzt. Fehlende Enum-/Preset-Übersetzungen werden wieder erkannt; Diagnoseberichte enthalten die nötigen Informationen ohne die vorgesehenen Geheimnisse offenzulegen.
- Der Extension-Installationspfad wurde korrigiert; Lesefehler brechen die Installation ab. Zusätzliche MQTT-Abonnements der Extension wurden entfernt. Installation und Aktualisierung erfolgen ausschließlich nach Anwenderaktion.

- Seit Extension 6.09 brechen fehlende Gerätedefinitionen und Fehler einzelner Mitglieder beim Ermitteln der Gruppen-Exposes die Gruppenabfrage nicht mehr ab. Gruppendaten und verfügbare Funktionen der übrigen Mitglieder werden weiterhin geliefert; betroffene Geräte werden protokolliert und bei der nächsten Abfrage erneut berücksichtigt.

- Beide Symcon-Extensions sind auf Version 6.08 angehoben. Gruppenabfragen verwenden die von Zigbee2MQTT für das jeweilige Gerät und seine Optionen aufgelösten Exposes. Dynamische Funktionen fehlen dadurch nicht mehr in den Gruppen; ältere Definitionen bleiben unterstützt.
- Die OTA-Erkennung beider Extensions berücksichtigt das aktuelle Definitionsfeld `ota`, einschließlich einer ausdrücklich deaktivierten Unterstützung. Nur bei fehlendem Feld wird das ältere `supports_ota` verwendet. Das Extension-Update wird weiterhin manuell über die Bridge bestätigt.

- Discovery bietet den Umzug falsch verbundener oder unverbundener Konfiguratoren wieder an. Vorhandener Konfigurator und Zielverbindung werden ausdrücklich ausgewählt; gleiche Basistopics auf verschiedenen MQTT-Verbindungen werden weiterhin nicht automatisch zusammengeführt.

- Das Schalter-Symbol bleibt bei Ein und Aus identisch. Der unpassende Symbolname für den eingeschalteten Zustand wurde korrigiert.

- Die Erkennen-Migration lässt die Sichtbarkeit, Namen, Icons, Beschreibungen, Positionen, Darstellungen, Profile und benutzerdefinierten Aktionen bestehender Bool-Variablen unverändert. Die neue Enum-Variable wird zusätzlich angelegt. Wiederholtes Übernehmen verändert auch nachträgliche Benutzeranpassungen an der alten Variable nicht. Die Migration sendet keinen Gerätebefehl und kann nach einem Abbruch erneut ausgeführt werden.

- Bei Leuchtmitteln erhalten nur die tatsächlichen Lichtstatusvariablen aus den `light`-Exposes die Ein/Aus-Rolle, auch bei mehreren Endpoints. Zusatzoptionen wie „Nicht stören“, verzögertes Einschalten und Impulssteuerung bleiben bedienbar, werden jedoch nicht mehr als Lichtstatus angeboten.

- Einmalige Befehle wie „Erkennen“ erhalten eine Schaltfläche statt einer Ein/Aus-Darstellung. Dadurch konkurrieren sie in nativen Kacheln nicht mehr mit dem eigentlichen Schaltstatus eines Leuchtmittels.

- Die Online-Anzeige der Bridge berücksichtigt zusätzlich die aktuelle MQTT-Verbindung und aktualisiert sich auch in geöffneten Formularen bei Verbindungsabbruch und Wiederverbindung. Gemeldete Versionsinformationen bleiben erhalten.
- RAW-Netzwerkkarten werden in der Bridge als JSON-Text gespeichert. Der bisherige Typfehler beim Empfang einer Netzwerkanalyse entfällt; Graphviz- und PlantUML-Ausgaben bleiben Text.

- Geräte und Gruppen verarbeiten Availability-Meldungen älterer Zigbee2MQTT-Versionen wieder als Klartext (`online`/`offline`). Die Prüfung ungültiger Payloads bleibt für andere Nachrichten aktiv.
- Manuell hinzugefügte MQTT-Broker bleiben in Discovery auch bei gleichem Basistopic auf anderen Servern sichtbar. Dubletten werden anhand von Broker-Verbindung und Basistopic erkannt.

- Verspätete MQTT-Antworten mit abweichender Transaktions-ID schließen keine neueren Anfragen mehr ab. Antworten ohne ID werden nur bei einer eindeutig passenden offenen Anfrage übernommen.
- Geräte und Gruppen verwerfen skalare und fehlerhafte JSON-Payloads ohne TypeError. Leere Availability-Nachrichten bleiben unterstützt.
- Die Netzwerkkarte übernimmt ausschließlich Antworten mit passender Transaktions-ID für den laufenden Scan; alte und unaufgeforderte Antworten bleiben ohne Wirkung.
- Discovery ordnet vorhandene Konfiguratoren anhand von Basistopic und MQTT-Verbindung zu. Gleiche Basistopics auf unterschiedlichen Servern werden nicht mehr verwechselt.

- Beide Symcon-Extensions sind auf Version 6.07 angehoben. Ungültige JSON-Anfragen werden kontrolliert verworfen; Geräte- und Gruppenanfragen funktionieren auch mit mehrstufigen MQTT-Basistopics. Das Extension-Update wird weiterhin manuell gestartet.

- Abgebrochene Migrationen stellen den vorherigen MQTT-Verarbeitungszustand wieder her. Alte Exposes-Dateien bleiben als Wiederherstellungsquelle erhalten; ungültige Altdaten brechen die Migration ausdrücklich ab.
- MQTT-Anfragen warten bei vorübergehend belegter Transaktionssperre innerhalb ihres Zeitlimits weiter. Fertige Antworten werden atomar entnommen; abgelaufene Transaktionen werden beim nächsten erfolgreichen Zugriff aufgeräumt.

- Die Gruppenverwaltung verwendet für Geräte und Endpoints den vorhandenen Cache der passenden Bridge. Dadurch entfallen die wiederholten vollständigen Geräteabfragen und deren Timeouts beim Öffnen und Bedienen des Formulars.

- Eine vorübergehend belegte MQTT-Transaktionssperre führt nicht mehr zum fatalen Abbruch einer Anfrage oder Formularaktion. Bestehende Transaktionsdaten bleiben geschützt.
- Kann die Installation einer Extension nicht bestätigt werden, enthält die Bridge-Fehlermeldung Hinweise zur Prüfung von Verbindung und Versionsinformationen.

## Bekannte Einschränkungen

- Das klassische WebFront kann neue native Darstellungen erst nach einem Neuladen der Browserseite korrekt anzeigen. Unter Symcon 9.1 wurde das Verhalten unabhängig vom Modul reproduziert; für Symcon 9.0 liegt eine Nutzermeldung vor. Die Kachel-Visualisierung ist nicht betroffen.
- Ein automatischer Rückweg auf 5.x ist nicht vorgesehen. Besonders profilabhängige Skripte sowie Verweise auf die frühere Erkennen-Variable müssen nach dem Update geprüft werden.

Frühere Entwicklungszwischenstände und ältere 5.x-Einträge: [Historische Änderungsnotizen](docs/CHANGELOG_HISTORY.md). Der technische Abgleich mit der letzten Beta steht im [Dokumentations- und Migrationsnachweis](docs/VERIFICATION.md).
