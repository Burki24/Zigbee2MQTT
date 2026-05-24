# Tools

## SymconCleanupStaleVariables.php

Wartungsscript fuer IP-Symcon, um alte Zigbee2MQTT-Variablen zu finden, die nicht mehr durch aktuelle Exposes oder das zuletzt bekannte Payload abgedeckt sind.

Das Script ist standardmaessig ein Dry-Run und loescht nichts. Die Loeschfreigabe erfolgt ueber eine externe JSON-Datei, damit am PHP-Code nichts geaendert werden muss.

1. `SymconCleanupStaleVariables.config.example.json` als `SymconCleanupStaleVariables.config.json` in dasselbe Verzeichnis kopieren.
2. Script unveraendert starten und die ausgegebenen Kandidaten pruefen.
3. Zu loeschende Variablen-IDs in `deleteVariableIDs` eintragen.
4. `deleteMode` auf `true` und `confirmDeletion` auf `DELETE` setzen.

Beispiel:

```json
{
    "deleteMode": true,
    "confirmDeletion": "DELETE",
    "deleteVariableIDs": [12345, 23456],
    "deleteAllClearCandidates": false,
    "includeGroups": true,
    "showPayloadOnlyReview": true,
    "protectArchivedVariables": true,
    "protectReferencedVariables": true
}
```

Alternativ kann `deleteAllClearCandidates` fuer alle klaren Kandidaten aktiviert werden. Auch dann bleiben archivierte oder referenzierte Variablen standardmaessig geschuetzt.

Schutzmechanismen:

- archivierte Variablen werden standardmaessig nicht geloescht
- Variablen mit registrierten Referenzen werden standardmaessig nicht geloescht
- Payload-only-Variablen werden separat als Review-Kandidaten angezeigt
- Instanzen ohne Expose- und Payload-Daten werden uebersprungen
