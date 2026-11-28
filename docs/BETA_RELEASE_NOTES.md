# Beta 6.1 – Versionshinweise / Release notes

Diese Texte sind für die nächste Beta vorgesehen. Diese Datei veröffentlicht keine Version im Module Store.

## Deutsch

> [!CAUTION]
> **Beim Wechsel von 5.x wird „Erkennen“ als neue String-Variable angelegt.** Skripte, Links und Automationen müssen bei Bedarf auf die neue Variablen-ID und den String-Befehlswert `identify` statt `true` umgestellt werden. Beispiel `RequestAction($neueErkennenVariableID, 'identify')`.

- Symcon 9.1 oder neuer ist erforderlich. Vor dem Wechsel von Symcon 9.0 mit Modul 6.0 (`dev_V6`) ein vollständiges Symcon-Backup erstellen, zuerst Symcon auf 9.1 aktualisieren und anschließend die vorhandene Modulbibliothek auf `Symcon-9.1` umstellen. Für Symcon 9.0 bleibt `dev_V6` verfügbar. Für einen Rückwechsel das Backup wiederherstellen.
- Die alte boolesche Erkennen-Variable bleibt mit ID, Werten, Referenzen, bisheriger Sichtbarkeit und Benutzeranpassungen erhalten. Nur ihre veraltete Modul-Standardaktion wird deaktiviert. Benutzerdefinierte Aktionen bleiben erhalten; eigene Aktionen müssen auf ihre weitere Funktion geprüft werden. Alte Modulaufrufe werden nicht automatisch weitergeleitet.
- Die neue Erkennen-Variable ist eine String-Aufzählung. Die Migration löst keinen Gerätebefehl aus und kann nach einem Abbruch fortgesetzt werden.
- Einstellungen, die frühere Testing-Versionen bereits entfernt oder verändert haben, können nicht automatisch rekonstruiert werden. Sichtbarkeit und Benutzeraktionen gegebenenfalls anhand eines Backups manuell prüfen.
- Native Symcon-Darstellungen ersetzen bisherige Modul-Standardprofile. Skripte, die Profilnamen auswerten, müssen geprüft werden. Benutzerdefinierte Profile und Darstellungen bleiben erhalten.
- Mitgeliefert werden die Symcon-Extensions 6.11 für Zigbee2MQTT 2.x und 6.09 für Zigbee2MQTT 1.x. Das Extension-Update in der Bridge manuell bestätigen.
- Im klassischen WebFront können neue Variablen bis zum Neuladen der Browserseite als Rohwerte erscheinen. Die Kachel-Visualisierung ist davon nicht betroffen.
- Die Zeilenauswahl übernimmt Angaben für Gruppenzugehörigkeit, Szenen, Optionen, Bindings, Reportings und Netzwerksicherheit. Die jeweiligen Aktionen übernehmen Änderungen separat. Binding und Reporting bieten Assistenten, die erst bei der letzten Bestätigung senden.
- Der Binding-Assistent deaktiviert eindeutig inkompatible Cluster. Geräte- und Gruppenoptionen zeigen passende Eingabefelder für Zahlen, Schalter, Auswahlwerte, Attributlisten und JSON.
- Die Netzwerkkarte zeigt Verbindungen zum ausgewählten Gerät, öffnet dessen Symcon-Instanz und bietet einen nativen Vollbildmodus. Bei unterstützten Z-Stack- und Ember-Koordinatoren mit Zigbee2MQTT 2.x zeigen Netzwerkkarte und Geräteinstanz den zuletzt beobachteten Weg zum Koordinator mit Zeitstempel.
- Die OTA-Aktionen zum Starten und Planen zeigen vor dem Senden eine Bestätigung mit Gerät, Aktion, installierter Firmware und Zielversion.
- Die Bridge speichert ZIP-Backups außerhalb des WebServer-Verzeichnisses und verschiebt vorhandene Modul-Backups beim Anwenden der Konfiguration dorthin.

Die [deutsche Moduldokumentation](https://github.com/Burki24/Zigbee2MQTT/blob/Symcon-9.1/README.md) beschreibt das Update. Der [Changelog](../CHANGELOG.md#61-gegenüber-dev_v6) führt die Änderungen gegenüber `dev_V6` und der veröffentlichten Beta 5.43 auf.

## English

> [!CAUTION]
> **Upgrading from 5.x creates a new string Identify variable.** Review scripts, links and automations and point them to the new variable ID using the string command `identify` instead of Boolean `true`. For example, use `RequestAction($newIdentifyVariableID, 'identify')`.

- Requires Symcon 9.1 or newer. When upgrading from Symcon 9.0 with module 6.0 (`dev_V6`), create a complete Symcon backup, upgrade Symcon to 9.1 first, then switch the existing module library to `Symcon-9.1`. The `dev_V6` branch remains available for Symcon 9.0. Restore the backup to roll back.
- The old Boolean identify variable retains its ID, value, references, visibility and user customizations. Only its obsolete module default action is disabled. Custom actions remain assigned; review your own actions for continued compatibility. Old module commands are not forwarded automatically.
- The new identify variable is a string enumeration. Migration sends no device command and can resume after an interruption.
- Settings already removed or changed by earlier testing versions cannot be reconstructed automatically. Review visibility and custom actions against a backup if necessary.
- Native Symcon presentations replace former module default profiles. Review scripts that depend on profile names. Custom profiles and presentations are retained.
- The bundled Symcon extensions are 6.11 for Zigbee2MQTT 2.x and 6.09 for Zigbee2MQTT 1.x. Confirm the extension update manually in the bridge.
- In classic WebFront, newly created variables may show raw values until the browser page is reloaded. Tile visualization is unaffected.
- Selecting a row fills in fields for group members, scenes, options, bindings, reports, and network security. Each change has a separate action. The binding and reporting wizards send their requests only after final confirmation.
- The binding wizard disables confirmed incompatible clusters. Device and group options show matching inputs for numbers, switches, choices, attribute lists, and JSON.
- The network map shows connections for the selected device, opens its Symcon instance, and offers native fullscreen. With supported Z-Stack and Ember coordinators on Zigbee2MQTT 2.x, the map and device form show the last observed path to the coordinator with a timestamp.
- The OTA start and schedule actions display the device, action, installed firmware, and target version for confirmation before sending the request.
- The bridge stores ZIP backups outside the WebServer directory and moves existing module backups there when applying its configuration.

See the [English module documentation](https://github.com/Burki24/Zigbee2MQTT/blob/Symcon-9.1/README.en.md) for upgrade instructions and the [changelog](../CHANGELOG.en.md#61-compared-with-dev_v6) for changes since `dev_V6` and the published 5.43 beta.
