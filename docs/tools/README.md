# Tools

## SymconCleanupStaleVariables.php

Wartungsscript fuer IP-Symcon, um alte Zigbee2MQTT-Variablen zu finden, die nicht mehr durch aktuelle Exposes oder das zuletzt bekannte Payload abgedeckt sind.

Das Script ist standardmaessig ein Dry-Run und loescht nichts. Die Loeschfreigabe erfolgt ueber eine externe JSON-Datei, damit am PHP-Code nichts geaendert werden muss.

1. `SymconCleanupStaleVariables.config.example.json` als `SymconCleanupStaleVariables.config.json` in dasselbe Verzeichnis kopieren.
2. Script unveraendert starten und die ausgegebenen Kandidaten pruefen.
3. Zu loeschende Variablen-IDs oder komplette Kandidatenzeilen in `deleteVariableIDs` eintragen.
4. `deleteMode` auf `true` und `confirmDeletion` auf `DELETE` setzen.

Beispiel:

```json
{
    "deleteMode": true,
    "confirmDeletion": "DELETE",
    "deleteVariableIDs": [
        12345,
        "#39271 | Geraete\\Sicherheit\\Flur\\Rauchmelder Unten | update__installed_version | Update: Installierte Version | Nur im letzten Payload vorhanden, nicht in aktuellen Exposes | nicht geschuetzt"
    ],
    "deleteCandidateLines": [],
    "deleteCandidateFile": "SymconCleanupStaleVariables.delete.txt",
    "deleteAllClearCandidates": false,
    "includeGroups": true,
    "showPayloadOnlyReview": true,
    "protectArchivedVariables": true,
    "protectReferencedVariables": true
}
```

Das Script zieht aus einer kompletten Kandidatenzeile automatisch nur die Variable-ID hinter `#`. In JSON muessen Backslashes innerhalb von Texten doppelt geschrieben werden (`\\`). Fuer wirklich rohes Copy/Paste ohne JSON-Escaping kann stattdessen die Datei aus `deleteCandidateFile` genutzt werden. Dazu `SymconCleanupStaleVariables.delete.example.txt` als `SymconCleanupStaleVariables.delete.txt` kopieren und die Kandidatenzeilen zeilenweise einfuegen.

Wenn das PHP als IP-Symcon-Scriptobjekt ausgefuehrt wird, liegt `__DIR__` nicht zwingend im Modulverzeichnis. Das Script sucht die Config deshalb zusaetzlich ueber die installierte Zigbee2MQTT-`library.json`, unter `modules/Zigbee2MQTT/docs/tools` und unter `.store`-Installationen. Relative Angaben in `deleteCandidateFile` werden relativ zur gefundenen Config-Datei aufgeloest.

Die Ausgabe enthaelt eine Script-Version mit Modulversion, Build und Git-Commit, sofern der installierte Modulordner Git-Informationen enthaelt. Fehlt diese Zeile, wird noch eine alte Scriptkopie ausgefuehrt.

Alternativ kann `deleteAllClearCandidates` fuer alle klaren Kandidaten aktiviert werden. Auch dann bleiben archivierte oder referenzierte Variablen standardmaessig geschuetzt.

Schutzmechanismen:

- archivierte Variablen werden standardmaessig nicht geloescht
- Variablen mit registrierten Referenzen werden standardmaessig nicht geloescht
- nachgelieferte Systemvariablen wie `last_seen`, `update` und alle `update__*`-Variablen werden immer behalten
- Payload-only-Variablen werden separat als Review-Kandidaten angezeigt
- Instanzen ohne Expose- und Payload-Daten werden uebersprungen
- erfolgreich geloeschte Variablen werden mit ihrer uebergeordneten Kategorie ausgegeben
