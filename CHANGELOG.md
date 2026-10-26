# Unveröffentlicht

## Fixes

- Abgebrochene Migrationen stellen den vorherigen MQTT-Verarbeitungszustand wieder her. Alte Exposes-Dateien bleiben als Wiederherstellungsquelle erhalten; ungültige Altdaten brechen die Migration ausdrücklich ab.
- MQTT-Anfragen warten bei vorübergehend belegter Transaktionssperre innerhalb ihres Zeitlimits weiter. Fertige Antworten werden atomar entnommen; abgelaufene Transaktionen werden beim nächsten erfolgreichen Zugriff aufgeräumt.

- Die Gruppenverwaltung verwendet für Geräte und Endpoints den vorhandenen Cache der passenden Bridge. Dadurch entfallen die wiederholten vollständigen Geräteabfragen und deren Timeouts beim Öffnen und Bedienen des Formulars.

- Eine vorübergehend belegte MQTT-Transaktionssperre führt nicht mehr zum fatalen Abbruch einer Anfrage oder Formularaktion. Bestehende Transaktionsdaten bleiben geschützt.
- Kann die Installation einer Extension nicht bestätigt werden, enthält die Bridge-Fehlermeldung Hinweise zur Prüfung von Verbindung und Versionsinformationen.
