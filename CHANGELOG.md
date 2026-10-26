# Unveröffentlicht

## Fixes

- Das Schalter-Symbol bleibt bei Ein und Aus identisch. Der unpassende Symbolname für den eingeschalteten Zustand wurde korrigiert.

- „Erkennen“ wird als String-Enum mit dem Befehlswert `identify` angelegt. Achtung: Bisherige Bool-Variablen bleiben mit ihrer alten ID und ihren Daten ausgeblendet erhalten, besitzen aber keine Aktion mehr. Für Skripte und Automationen müssen die neue Erkennen-Variable und der Enum-Wert verwendet werden. Die Migration sendet keinen Gerätebefehl und kann nach einem Abbruch erneut ausgeführt werden.

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
