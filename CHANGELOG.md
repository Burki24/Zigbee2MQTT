# Unveröffentlicht

## Fixes

- Die Gruppenverwaltung verwendet für Geräte und Endpoints den vorhandenen Cache der passenden Bridge. Dadurch entfallen die wiederholten vollständigen Geräteabfragen und deren Timeouts beim Öffnen und Bedienen des Formulars.

- Eine vorübergehend belegte MQTT-Transaktionssperre führt nicht mehr zum fatalen Abbruch einer Anfrage oder Formularaktion. Bestehende Transaktionsdaten bleiben geschützt.
- Kann die Installation einer Extension nicht bestätigt werden, enthält die Bridge-Fehlermeldung Hinweise zur Prüfung von Verbindung und Versionsinformationen.
