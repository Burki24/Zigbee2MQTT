# Beta 6.0 – Versionshinweise / Release notes

Diese Texte sind für die nächste Beta vorgesehen. Diese Datei veröffentlicht keine Version im Module Store.

## Deutsch

> [!CAUTION]
> **„ERKENNEN“ WIRD ALS NEUE VARIABLE ANGELEGT!** Skripte, Links und Automationen müssen bei Bedarf auf die neue Variablen-ID und den String-Befehlswert `identify` statt `true` umgestellt werden. Beispiel: `RequestAction($neueErkennenVariableID, 'identify')`.

- Symcon 9.0 oder neuer ist erforderlich. Vor dem Update von 5.x oder einer früheren Testing-Version ein vollständiges Symcon-Backup erstellen; ein Downgrade nur durch Wiederherstellung des Backups durchführen.
- Die alte boolesche Erkennen-Variable bleibt mit ID, Werten, Referenzen, bisheriger Sichtbarkeit und Benutzeranpassungen erhalten. Nur ihre veraltete Modul-Standardaktion wird deaktiviert. Benutzerdefinierte Aktionen bleiben erhalten; eigene Aktionen müssen auf ihre weitere Funktion geprüft werden. Alte Modulaufrufe werden nicht automatisch weitergeleitet.
- Die neue Erkennen-Variable ist eine String-Aufzählung. Die Migration löst keinen Gerätebefehl aus und kann nach einem Abbruch fortgesetzt werden.
- Einstellungen, die frühere Testing-Versionen bereits entfernt oder verändert haben, können nicht automatisch rekonstruiert werden. Sichtbarkeit und Benutzeraktionen gegebenenfalls anhand eines Backups manuell prüfen.
- Native Symcon-Darstellungen ersetzen bisherige Modul-Standardprofile. Skripte, die Profilnamen auswerten, müssen geprüft werden. Benutzerdefinierte Profile und Darstellungen bleiben erhalten.
- Die mitgelieferte Symcon-Extension ist 6.09. Das Extension-Update in der Bridge muss manuell bestätigt werden; insbesondere die aktuellen Gruppen- und OTA-Verbesserungen benötigen die aktuelle Extension.
- Im klassischen WebFront können neue Variablen bis zum Neuladen der Browserseite als Rohwerte erscheinen. Die Kachel-Visualisierung ist davon nicht betroffen.
- Weitere Funktionen und Änderungen: moderne Kacheln, Netzwerkkarte, Geräteoptionen, Binding/Reporting, Gruppenverwaltung, OTA und Variablen-Wartung; Einzelheiten in der Dokumentation.

Dokumentation für das deutsche und englische Store-Feld: <https://github.com/Burki24/Zigbee2MQTT/blob/dev_V6/README.md>. Die Dokumentation ist derzeit deutschsprachig; für 6.0 nicht mehr auf den alten 5.x-Hauptzweig verweisen.

## English

> [!CAUTION]
> **IDENTIFY IS CREATED AS A NEW VARIABLE!** Review scripts, links and automations and point them to the new variable ID using the string command `identify` instead of Boolean `true`. Example: `RequestAction($newIdentifyVariableID, 'identify')`.

- Requires Symcon 9.0 or newer. Create a complete Symcon backup before upgrading from 5.x or an earlier testing version. Downgrade by restoring that backup only.
- The old Boolean identify variable retains its ID, value, references, visibility and user customizations. Only its obsolete module default action is disabled. Custom actions remain assigned; review your own actions for continued compatibility. Old module commands are not forwarded automatically.
- The new identify variable is a string enumeration. Migration sends no device command and can resume after an interruption.
- Settings already removed or changed by earlier testing versions cannot be reconstructed automatically. Review visibility and custom actions against a backup if necessary.
- Native Symcon presentations replace former module default profiles. Review scripts that depend on profile names. Custom profiles and presentations are retained.
- The bundled Symcon extension is 6.09. Confirm its update manually in the bridge; current group and OTA improvements require the current extension.
- In classic WebFront, newly created variables may show raw values until the browser page is reloaded. Tile visualization is unaffected.
- Further features include modern tiles, a network map, device options, binding/reporting, group management, OTA and variable maintenance. See the documentation for details.

Documentation URL for both Store localizations: <https://github.com/Burki24/Zigbee2MQTT/blob/dev_V6/README.md>. The documentation is currently in German. Do not link version 6.0 to the old 5.x main-branch documentation.
