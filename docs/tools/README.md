# Wartungstools

## SymconCleanupStaleVariables.php

`SymconCleanupStaleVariables.php` ist ein Wartungsscript fuer IP-Symcon. Es hilft dabei, alte Zigbee2MQTT-Variablen zu finden, die nicht mehr zu den aktuellen Exposes oder zum zuletzt bekannten Payload einer Zigbee2MQTT-Device- oder Gruppeninstanz passen.

Empfohlen ist die Variablen-Wartung in der Bridge-Konfiguration. Dort koennen klare Kandidaten direkt in Symcon geprueft und einzeln nach Popup-Bestaetigung geloescht werden. Archivierte oder referenzierte Variablen sind in dieser Oberflaeche geschuetzt.

Dieses Script bleibt als Experten- und Notfallwerkzeug erhalten, zum Beispiel fuer sehr grosse Wartungslaeufe oder wenn eine Ausgabe ausserhalb der Bridge-Oberflaeche benoetigt wird. Es kann Variablen loeschen. Werden falsche Variablen geloescht, koennen Visualisierungen, Ereignisse, Scripte, Links oder Archivdaten betroffen sein. Standardmaessig laeuft das Script deshalb immer im Dry-Run und loescht nichts.

### Wofuer ist das Tool gedacht?

Das Modul legt Variablen anhand von Zigbee2MQTT-Exposes und empfangenen Payloads an. Nach Device-Wechseln, geaenderten Zigbee2MQTT-Definitionen, alten Modulversionen oder manuell geloeschten bzw. umbenannten Objekten koennen Variablen uebrig bleiben, die vom aktuellen Geraet nicht mehr angeboten werden.

Das Tool prueft pro Instanz:

- aktuelle Exposes
- letztes bekanntes Payload
- automatisch abgeleitete Variablen des Moduls
- geschuetzte System- und Hilfsvariablen
- Archivierung im Archive Control
- Referenzen durch andere Symcon-Objekte

Danach gibt es Kandidaten aus, die man pruefen und gezielt loeschen kann.

### Was wird nicht geloescht?

Ohne ausdrueckliche Freigabe wird nichts geloescht. Auch im Loeschmodus gibt es Schutzmechanismen:

- archivierte Variablen werden standardmaessig nicht geloescht
- Variablen mit registrierten Referenzen werden standardmaessig nicht geloescht
- nachgelieferte Systemvariablen wie `last_seen`, `update` und alle `update__*`-Variablen werden immer behalten
- Instanzen ohne Expose- und Payload-Daten werden uebersprungen
- geloescht werden nur Kandidaten, die im aktuellen Scriptlauf wiedergefunden wurden

Payload-only-Variablen werden separat als Review-Kandidaten angezeigt. Das betrifft Variablen, die nicht in den Exposes stehen, aber im letzten Payload vorhanden waren.

### Dateien

Im Verzeichnis `docs/tools` liegen:

- `SymconCleanupStaleVariables.php`: das eigentliche Wartungsscript
- `SymconCleanupStaleVariables.config.example.json`: Beispiel fuer die externe Konfiguration
- `SymconCleanupStaleVariables.delete.example.txt`: Beispiel fuer eine rohe Copy/Paste-Loeschliste
- `README.md`: diese Anleitung

Die echten Arbeitsdateien sollen nicht im Modulverzeichnis liegen. Eigene Dateien im Modulordner werden von IP-Symcon als lokale Modulaenderungen erkannt und koennen Modulupdates blockieren.

### Empfohlener Ablageort fuer Arbeitsdateien

Das Script sucht die Arbeitsdateien bevorzugt im Symcon-User-Verzeichnis:

```text
/var/lib/symcon/user/IPSZigbee2MQTT
```

Unter Windows entsprechend:

```text
C:\ProgramData\Symcon\user\IPSZigbee2MQTT
```

Das Unterverzeichnis `IPSZigbee2MQTT` wird vom Script automatisch angelegt, wenn der uebergeordnete `user`-Ordner vorhanden ist.

Die echten Dateien sollten dann so liegen:

```text
/var/lib/symcon/user/IPSZigbee2MQTT/SymconCleanupStaleVariables.config.json
/var/lib/symcon/user/IPSZigbee2MQTT/SymconCleanupStaleVariables.delete.txt
```

### Script in Symcon ausfuehren

Am einfachsten wird ein normales IP-Symcon-Script angelegt, das nur das Wartungsscript aus dem Modul inkludiert:

```php
<?php
include '/var/lib/symcon/modules/Zigbee2MQTT/docs/tools/SymconCleanupStaleVariables.php';
```

Falls das Modul bei einer Store-Installation unter `.store` liegt, den Pfad entsprechend auf die installierte Datei anpassen.

Beim Start gibt das Tool unter anderem aus:

```text
Zigbee2MQTT Variablen-Cleanup
=============================
Script-Version: Modul 6.0, Build 1785, Commit abc1234
Konfiguration: ...
Empfohlener Config-Pfad: /var/lib/symcon/user/IPSZigbee2MQTT/SymconCleanupStaleVariables.config.json
Modus: DRY-RUN
```

Fehlt die Zeile `Script-Version`, wird noch eine alte Scriptkopie ausgefuehrt.

### Config anlegen

1. `SymconCleanupStaleVariables.config.example.json` kopieren.
2. Die Kopie im empfohlenen Config-Pfad ablegen.
3. Die Kopie in `SymconCleanupStaleVariables.config.json` umbenennen.
4. Das Script einmal im Dry-Run starten.

Beispiel:

```json
{
    "deleteMode": false,
    "confirmDeletion": "",
    "deleteVariableIDs": [],
    "deleteCandidateLines": [],
    "deleteCandidateFile": "SymconCleanupStaleVariables.delete.txt",
    "deleteAllClearCandidates": false,
    "includeGroups": true,
    "showPayloadOnlyReview": true,
    "protectArchivedVariables": true,
    "protectReferencedVariables": true
}
```

### Config-Optionen

`deleteMode`

Aktiviert den Loeschmodus. Standard ist `false`. Mit `false` wird nur analysiert und ausgegeben.

`confirmDeletion`

Zweite Sicherheitsabfrage. Geloescht wird nur, wenn `deleteMode` auf `true` steht und `confirmDeletion` exakt den Wert `DELETE` hat.

`deleteVariableIDs`

Liste einzelner Variablen-IDs oder kompletter Kandidatenzeilen. Das Script zieht aus einer kompletten Zeile automatisch die ID hinter `#`.

Beispiel:

```json
"deleteVariableIDs": [
    12345,
    "#39271 | Geraete\\Sicherheit\\Flur\\Rauchmelder Unten | old_ident | Alter Name | Nicht in aktuellen Exposes und nicht im letzten Payload | nicht geschuetzt"
]
```

Wichtig: In JSON muessen Backslashes innerhalb von Texten doppelt geschrieben werden (`\\`).

`deleteCandidateLines`

Alternative Liste fuer komplette Kandidatenzeilen direkt in der JSON-Datei.

`deleteCandidateFile`

Pfad zu einer Textdatei mit Loeschkandidaten. Relative Pfade werden relativ zur gefundenen Config-Datei aufgeloest. Fuer rohe Copy/Paste-Zeilen ist diese Variante am angenehmsten, weil Backslashes nicht fuer JSON escaped werden muessen.

Empfohlen:

```text
SymconCleanupStaleVariables.delete.txt
```

Dann liegt die Datei neben der Config:

```text
/var/lib/symcon/user/IPSZigbee2MQTT/SymconCleanupStaleVariables.delete.txt
```

`deleteAllClearCandidates`

Loescht alle klaren Loeschkandidaten aus dem aktuellen Lauf. Diese Option ist nur fuer sehr kontrollierte Wartungslaufe gedacht. Sie sollte normalerweise `false` bleiben.

`includeGroups`

Prueft neben Device-Instanzen auch Zigbee2MQTT-Gruppeninstanzen.

`showPayloadOnlyReview`

Zeigt Variablen separat an, die nur im letzten Payload vorhanden sind, aber nicht in den aktuellen Exposes.

`protectArchivedVariables`

Schuetzt archivierte Variablen vor dem Loeschen.

`protectReferencedVariables`

Schuetzt Variablen, auf die andere Symcon-Objekte referenzieren.

### Ausgabe verstehen

Das Tool unterscheidet mehrere Bereiche.

`Klare Loeschkandidaten`

Variablen, die weder in aktuellen Exposes noch im letzten Payload gefunden wurden oder als nicht mehr unterstuetzte abgeleitete Variablen erkannt wurden.

`Review-Kandidaten`

Variablen, die nicht in den Exposes stehen, aber im letzten Payload vorhanden waren. Diese sollten besonders vorsichtig bewertet werden, weil manche Geraete Werte erst spaeter senden.

`Hinweise/Fehler`

Instanzen, die nicht sauber geprueft werden konnten, zum Beispiel weil Debugdaten fehlen.

`Ignorierte Loeschzeilen`

Zeilen aus der Config oder der Delete-Datei, aus denen keine Variable-ID gelesen werden konnte.

`Geloeschte Variablen`

Nur im Loeschmodus sichtbar. Erfolgreich geloeschte Variablen werden mit Instanz und uebergeordneter Kategorie ausgegeben.

`Uebersprungene Loeschungen`

IDs, die angefordert wurden, aber nicht geloescht wurden, zum Beispiel weil sie archiviert sind, Referenzen haben oder im aktuellen Lauf kein Kandidat waren.

### Sicherer Arbeitsablauf

1. Script im Dry-Run starten.
2. Ausgabe vollstaendig pruefen.
3. Nur Kandidaten uebernehmen, die wirklich geloescht werden sollen.
4. Kandidaten entweder als IDs in `deleteVariableIDs` eintragen oder komplette Zeilen in `SymconCleanupStaleVariables.delete.txt` kopieren.
5. Script erneut im Dry-Run starten und pruefen, ob `Vorgemerkte Loesch-IDs` korrekt ist.
6. Erst dann `deleteMode` auf `true` und `confirmDeletion` auf `DELETE` setzen.
7. Script ausfuehren.
8. Danach `deleteMode` wieder auf `false` setzen.

### Beispiel fuer rohe Copy/Paste-Loeschliste

Datei:

```text
/var/lib/symcon/user/IPSZigbee2MQTT/SymconCleanupStaleVariables.delete.txt
```

Inhalt:

```text
#39271 | Geraete\Sicherheit\Flur\Rauchmelder Unten | old_ident | Alter Name | Nicht in aktuellen Exposes und nicht im letzten Payload | nicht geschuetzt
#39272 | Geraete\Sicherheit\Flur\Rauchmelder Unten | another_old_ident | Alter Name 2 | Nicht in aktuellen Exposes und nicht im letzten Payload | nicht geschuetzt
```

Das Script nutzt daraus nur die IDs `39271` und `39272`.

### Warum nicht im Modulverzeichnis speichern?

IP-Symcon verwaltet installierte Module als Git-Arbeitskopien. Wenn im Modulordner eigene Dateien angelegt oder veraendert werden, erkennt Symcon lokale Aenderungen. Beim Modulupdate erscheint dann eine Rueckfrage, ob diese Aenderungen verworfen werden sollen.

Deshalb gehoeren echte Arbeitsdateien in:

```text
/var/lib/symcon/user/IPSZigbee2MQTT
```

Die Dateien im Modulordner sind nur Beispiele und Dokumentation.

### Wiederherstellung

Das Tool loescht Symcon-Variablenobjekte. Eine Wiederherstellung ist nur ueber ein Symcon-Backup oder durch erneutes Anlegen durch das Modul moeglich. Archivdaten, Ereignisse, Links und Referenzen koennen dabei verloren gehen oder manuell nachgearbeitet werden muessen.

Vor einem groesseren Loeschlauf sollte ein aktuelles Symcon-Backup vorhanden sein.
