# Tools

## SymconCleanupStaleVariables.php

Wartungsscript fuer IP-Symcon, um alte Zigbee2MQTT-Variablen zu finden, die nicht mehr durch aktuelle Exposes oder das zuletzt bekannte Payload abgedeckt sind.

Das Script ist standardmaessig ein Dry-Run und loescht nichts. Zum Loeschen muessen die ausgegebenen Variablen-IDs bewusst in `$deleteVariableIDs` eingetragen und `$deleteMode` auf `true` gesetzt werden. Alternativ kann `$deleteAllClearCandidates` fuer alle klaren Kandidaten aktiviert werden.

Schutzmechanismen:

- archivierte Variablen werden standardmaessig nicht geloescht
- Variablen mit registrierten Referenzen werden standardmaessig nicht geloescht
- Payload-only-Variablen werden separat als Review-Kandidaten angezeigt
- Instanzen ohne Expose- und Payload-Daten werden uebersprungen

