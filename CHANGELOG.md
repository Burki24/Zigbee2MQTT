# Unveröffentlicht

## Fixes

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
