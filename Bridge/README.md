[![SDK](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Module Version](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2FNall-chan%2FZigbee2MQTT%2Frefs%2Fheads%2Fmain%2Flibrary.json&query=%24.version&label=Modul%20Version&color=blue)](https://community.symcon.de/t/modul-zigbee2mqtt-version-5-x/139819)
[![Symcon Version](https://img.shields.io/badge/Symcon%20Version-9.0%3E-green)](https://www.symcon.de/de/service/dokumentation/einfuehrung/systemvoraussetzungen/versionenuebersicht/#version-90)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![Check Style](https://github.com/Nall-chan/Zigbee2MQTT/workflows/Check%20Style/badge.svg)](https://github.com/Nall-chan/Zigbee2MQTT/actions)
[![Run Tests](https://github.com/Nall-chan/Zigbee2MQTT/workflows/Run%20Tests/badge.svg)](https://github.com/Nall-chan/Zigbee2MQTT/actions)  

# Zigbee2MQTT-Bridge  <!-- omit in toc -->

   Modul für alle Systemweiten Funktionen von Zigbee2MQTT

## Inhaltsverzeichnis <!-- omit in toc -->

- [1. Funktionsumfang](#1-funktionsumfang)
- [2. Voraussetzungen](#2-voraussetzungen)
- [3. Software-Installation](#3-software-installation)
- [4. Konfiguration](#4-konfiguration)
- [5. Statusvariablen](#5-statusvariablen)
- [6. PHP-Funktionsreferenz](#6-php-funktionsreferenz)
- [7. Aktionen](#7-aktionen)
- [8. Anhang](#8-anhang)
  - [1. Changelog](#1-changelog)
  - [2. Spenden](#2-spenden)
  - [3. Lizenz](#3-lizenz)

## 1. Funktionsumfang

- Verfügbarkeit von Zigbee2MQTT in Symcon darstellen (Online-Variable)
- Verwaltung der für das Modul benötigten Extension in Zigbee2MQTT
- Systemweite Einstellungen in Zigbee2MQTT aus Symcon anpassen
- Netzwerkbeitritt aus Symcon steuern und darstellen
- Globale Zigbee2MQTT-Blocklist und -Passlist verwalten
- Diagnosebereich für Health Check, Coordinator Check, Bridge-Events, Warnungen/Fehler und auffällige Geräte
- Variablen-Wartung zum Finden und gezielten Löschen verwaister Zigbee2MQTT-Variablen
- Wartungsbereich für Zigbee2MQTT-Backup, Install-Code und Touchlink-Scan/Identify/Factory-Reset
- Viele PHP-Funktionen um interne Zigbee2MQTT Funktionen auszuführen (Gruppen verwalten, Geräte umbenennen usw...)
  
## 2. Voraussetzungen

- mindestens IP-Symcon Version 9.0
- MQTT-Broker (interner MQTT-Server von Symcon oder externer z.B. Mosquitto)
- installiertes und lauffähiges [zigbee2mqtt](https://www.zigbee2mqtt.io)  
  
## 3. Software-Installation

- Dieses Modul ist Bestandteil der [Zigbee2MQTT-Library](../README.md#3-installation).  

## 4. Konfiguration

   ![Konfiguration Device](imgs/config.png)

| **Nummer** | **Feld**            | **Beschreibung**                                                                                                                                                |
| ---------- | ------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **1**      | **MQTT Base Topic** | Dieses wird vom [Konfigurator](../Configurator/README.md) bei Anlage der Instanz automatisch auf den korrekten Wert gesetzt und sollte auch so belassen werden. |
| **2**      | **Erweiterung**     | Über diese Schaltfläche kann die Erweiterung in Z2M eingerichtet oder aktualisert werden, sofern dies nicht automatisch erfolgt ist.                            |
| **3**      | **last_seen**       | In Z2M muss die Einstellung `last_seen` auf den Wert `epoch` eingerichtet sein, da es sonst zu Fehlermeldungen bei den Variablen `Zuletzt gesehen` kommt.       |
| **4**      | **Testcenter**      | Hier sind die Schaltbaren Statusvariablen aufgeführt, so kann z.B. der Netzwerkbeitritt aktiviert werden.                                                       |

Zusätzlich enthält die Bridge-Konfiguration folgende Verwaltungsbereiche:

| Bereich | Beschreibung |
| ------- | ------------ |
| Diagnose | Führt Health Check und Coordinator Check aus, fordert die Netzwerkkarte an und zeigt fehlende Router, nicht unterstützte Geräte, Interview-Probleme, Bridge-Events sowie Warnungen und Fehler an. |
| Netzwerksicherheit | Verwaltet `blocklist` und `passlist` direkt über bekannte Zigbee2MQTT-Geräte oder manuelle IEEE-Adressen. |
| Variablen-Wartung | Sucht alte Zigbee2MQTT-Variablen, die nicht mehr zu aktuellen Exposes oder Payloads passen, und löscht einzelne klare Kandidaten erst nach Bestätigung. |
| Wartung | Erstellt ein Zigbee2MQTT-Backup, sendet Zigbee-3.0-Install-Codes und bietet Touchlink-Scan, Identify und Factory-Reset an. |

Die `blocklist` blockiert Geräte anhand ihrer IEEE-Adresse. Die `passlist` ist restriktiver: Zigbee2MQTT entfernt Geräte aus dem Netzwerk, die nicht in der Passlist stehen. Deshalb verlangt die Bridge-Konfiguration vor Passlist-Änderungen eine Bestätigung. Die Geräteauswahl wird als filterbare Liste aus bereits empfangenen Zigbee2MQTT-Gerätedaten, vorhandenen Device-Instanzen mit gleicher Bridge und bei Bedarf aus der Symcon-Extension aufgebaut.

Touchlink-Scan und Touchlink-Factory-Reset können die Zigbee-Kommunikation kurzfristig stören. Ein Factory-Reset ohne ausgewähltes Ziel kann das nächste per Touchlink erreichbare Gerät zurücksetzen und sollte daher nur bewusst genutzt werden.

Die Variablen-Wartung ist der empfohlene Weg, um alte Zigbee2MQTT-Variablen aufzuräumen. Über **Verwaiste Variablen suchen** werden klare Löschkandidaten, Review-Kandidaten und Hinweise getrennt angezeigt. Die Listen zeigen zusätzlich, ob eine Variable archiviert ist und wann sie zuletzt beschrieben wurde. Archivierte oder von anderen Symcon-Objekten referenzierte Variablen werden in der Bridge-Oberfläche nicht gelöscht. Jeder Löschvorgang betrifft genau eine Variable und muss über ein Popup bestätigt werden.

## 5. Statusvariablen

| Name                               | Typ     | Profil              | Beschreibung                                 |
| ---------------------------------- | ------- | ------------------- | -------------------------------------------- |
| Beitritt zum Netzwerk zulassen     | bool    | ~Switch             | Status und Steuern des Netzwerkbeitritt      |
| Erweiterung geladen                | bool    |                     | true wenn die Erweiterung geladen wurde      |
| Erweiterung ist aktuell            | bool    |                     | true wenn die Erweiterung aktuell ist        |
| Erweiterung Version                | string  |                     | Version der Erweiterung                      |
| Netzwerkkanal                      | integer |                     | Netzwerkkanal des Zigbee-Netzwerks           |
| Neustart durchführen               | integer | Z2M.bridge.restart  | Action Variable um einen Neustart auszulösen |
| Neustart erforderlich              | bool    |                     | true wenn eine Neustart von Z2M nötig ist    |
| Protokollierung                    | string  | Z2M.brigde.loglevel | Status der Softwareaktualisierung            |
| Status                             | bool    | ~Alert.Reversed     | Online Status von Zigbee2MQTT                |
| Version                            | string  |                     | Version von Zigbee2MQTT                      |
| Zigbee Herdsman Converters Version | string  |                     | Version des Zigbee Herdsman Converters       |
| Zigbee Herdsman Version            | string  |                     | Version vom Zigbee Herdsman-Modul            |

## 6. PHP-Funktionsreferenz

Die Bridge-Funktionen senden Zigbee2MQTT-Requests an das `bridge/request/...` Topic und werten die Antwort von Zigbee2MQTT aus.
Bei einer erfolgreichen Antwort wird `true` zurückgegeben, bei einem Fehler oder Timeout `false`.

Lange laufende Requests wie Netzwerkkarte und OTA-Aktualisierung werden nur angestoßen und laufen anschließend in Zigbee2MQTT weiter. In diesem Fall bedeutet `true`, dass der Request erfolgreich an Zigbee2MQTT übergeben wurde.

Viele Geräte- und Gruppenfunktionen werden auch von den Device- und Group-Konfigurationsformularen genutzt. In der Regel ist die Bedienung dort komfortabler, während die Bridge-Funktionen vor allem für Skripte, Abläufe und eigene Automationen gedacht sind.

### Z2M_InstallSymconExtension <!-- omit in toc -->

```php
bool Z2M_InstallSymconExtension(int $InstanzID);
```

Die aktuelle Symcon Erweiterung wird in Z2M installiert.  

---

### Z2M_RequestOptions <!-- omit in toc -->

```php
bool Z2M_RequestOptions(int $InstanzID);
```

Fordert die aktuellen Bridge-Optionen von Zigbee2MQTT an und aktualisiert die Bridge-Instanz anhand der Antwort.

---

### Z2M_SetLastSeen <!-- omit in toc -->

```php
bool Z2M_SetLastSeen(int $InstanzID);
```

Die Konfiguration der `last_seen` Einstellung in Z2M wird auf `epoch` verändert, damit die Instanzen in Symcon den Wert korrekt darstellen können.  

---

### Z2M_SetPermitJoinOption <!-- omit in toc -->

```php
bool Z2M_SetPermitJoinOption(int $InstanzID, bool $PermitJoin);
```

Setzt die globale Zigbee2MQTT-Option `permit_join`. Diese Option sollte aus Sicherheitsgründen normalerweise deaktiviert sein.

---

### Z2M_SetPermitJoin <!-- omit in toc -->

```php
bool Z2M_SetPermitJoin(int $InstanzID, bool $PermitJoin);
```

Aktiviert oder deaktiviert den Netzwerkbeitritt zur Laufzeit. Bei `true` wird Zigbee2MQTT angewiesen, neue Geräte temporär beitreten zu lassen; bei `false` wird der Beitritt beendet.

---

### Z2M_SetBlocklist <!-- omit in toc -->

```php
bool Z2M_SetBlocklist(int $InstanzID, string $DevicesJSON);
```

Setzt die globale Zigbee2MQTT-Option `blocklist` über `bridge/request/options`. `DevicesJSON` muss ein JSON-Array mit IEEE-Adressen enthalten, z. B. `["0x000b57fffec6a5b2"]`.

---

### Z2M_SetPasslist <!-- omit in toc -->

```php
bool Z2M_SetPasslist(int $InstanzID, string $DevicesJSON);
```

Setzt die globale Zigbee2MQTT-Option `passlist` über `bridge/request/options`. `DevicesJSON` muss ein JSON-Array mit IEEE-Adressen enthalten.

Wichtig: Zigbee2MQTT entfernt Geräte, die nicht in der Passlist enthalten sind. Die Bridge-Konfiguration zeigt deshalb vor Passlist-Änderungen eine Sicherheitsabfrage.

---

### Z2M_SetLogLevel <!-- omit in toc -->

```php
bool Z2M_SetLogLevel(int $InstanzID, string $LogLevel);
```

Setzt den Zigbee2MQTT-Loglevel. Übliche Werte sind `error`, `warning`, `info` und `debug`.

---

### Z2M_Restart <!-- omit in toc -->

```php
bool Z2M_Restart(int $InstanzID);
```

Fordert einen Neustart von Zigbee2MQTT an.

---

### Z2M_CreateGroup <!-- omit in toc -->

```php
bool Z2M_CreateGroup(int $InstanzID, string $GroupName);
```

Legt eine neue Zigbee2MQTT-Gruppe mit dem angegebenen Namen an.

---

### Z2M_DeleteGroup <!-- omit in toc -->

```php
bool Z2M_DeleteGroup(int $InstanzID, string $GroupName);
```

Löscht eine Zigbee2MQTT-Gruppe.

---

### Z2M_RenameGroup <!-- omit in toc -->

```php
bool Z2M_RenameGroup(int $InstanzID, string $OldName, string $NewName);
```

Benennt eine Zigbee2MQTT-Gruppe um.

---

### Z2M_AddDeviceToGroup <!-- omit in toc -->

```php
bool Z2M_AddDeviceToGroup(int $InstanzID, string $GroupName, string $DeviceName, string $Endpoint = '');
```

Fügt ein Gerät einer Gruppe hinzu. Bei Geräten mit mehreren Endpoints kann `Endpoint` mit dem Endpoint-Namen oder der Endpoint-ID gefüllt werden.

---

### Z2M_RemoveDeviceFromGroup <!-- omit in toc -->

```php
bool Z2M_RemoveDeviceFromGroup(int $InstanzID, string $GroupName, string $DeviceName, string $Endpoint = '', bool $SkipDisableReporting = true);
```

Entfernt ein Gerät aus einer Gruppe. `SkipDisableReporting` verhindert, dass Zigbee2MQTT beim Entfernen automatisch Reporting deaktiviert.

---

### Z2M_RemoveAllDevicesFromGroup <!-- omit in toc -->

```php
bool Z2M_RemoveAllDevicesFromGroup(int $InstanzID, string $GroupName);
```

---

### Z2M_RemoveDeviceFromAllGroups <!-- omit in toc -->

```php
bool Z2M_RemoveDeviceFromAllGroups(int $InstanzID, string $DeviceName, bool $SkipDisableReporting = true);
```

Entfernt ein Gerät aus allen Zigbee2MQTT-Gruppen.

---

### Z2M_SetGroupOptions <!-- omit in toc -->

```php
bool Z2M_SetGroupOptions(int $InstanzID, string $GroupName, string $OptionsJSON);
```

Setzt Zigbee2MQTT-Gruppenoptionen. `OptionsJSON` muss ein JSON-Objekt sein, z.B. `{"transition":1}`.

Typische Optionen sind `retain`, `transition`, `optimistic`, `qos`, `off_state`, `filtered_attributes` und `homeassistant`. Die Gruppeninstanz bietet dafür, soweit möglich, passende Editoren und eine Auswahl bekannter Payload-Attribute an.

---

### Z2M_StoreScene <!-- omit in toc -->

```php
bool Z2M_StoreScene(int $InstanzID, string $FriendlyName, int $SceneID, string $SceneName = '', int $GroupID = 0);
```

Speichert den aktuellen Zustand eines Geräts oder einer Gruppe als Szene. Optional kann ein Name und bei Geräteszenen eine Gruppen-ID mitgegeben werden.

---

### Z2M_AddScene <!-- omit in toc -->

```php
bool Z2M_AddScene(int $InstanzID, string $FriendlyName, string $SceneJSON);
```

Legt eine Szene mit vollständiger Szenendefinition an. `SceneJSON` muss ein JSON-Objekt enthalten, z.B. `{"ID":3,"name":"Abend","brightness":180}`.

---

### Z2M_RecallScene <!-- omit in toc -->

```php
bool Z2M_RecallScene(int $InstanzID, string $FriendlyName, int $SceneID);
```

Ruft eine gespeicherte Szene auf.

---

### Z2M_RemoveScene <!-- omit in toc -->

```php
bool Z2M_RemoveScene(int $InstanzID, string $FriendlyName, int $SceneID);
```

Entfernt eine Szene.

---

### Z2M_RemoveAllScenes <!-- omit in toc -->

```php
bool Z2M_RemoveAllScenes(int $InstanzID, string $FriendlyName);
```

Entfernt alle Szenen eines Geräts oder einer Gruppe.

---

### Z2M_RenameScene <!-- omit in toc -->

```php
bool Z2M_RenameScene(int $InstanzID, string $FriendlyName, int $SceneID, string $SceneName);
```

Benennt eine Szene um.

---

### Z2M_Bind <!-- omit in toc -->

```php
bool Z2M_Bind(int $InstanzID, string $SourceDevice, string $TargetDevice);
```

Erstellt ein Binding zwischen Quelle und Ziel ohne zusätzliche Cluster-Auswahl. Für neue Automationen ist `Z2M_BindWithOptions()` meistens flexibler.

---

### Z2M_BindWithOptions <!-- omit in toc -->

```php
bool Z2M_BindWithOptions(int $InstanzID, string $SourceDevice, string $TargetDevice, string $ClustersJSON, bool $SkipDisableReporting);
```

Erstellt ein Binding mit optionaler Cluster-Auswahl. `ClustersJSON` kann ein JSON-Array wie `["genOnOff"]` oder eine kommaseparierte Liste sein.

---

### Z2M_Unbind <!-- omit in toc -->

```php
bool Z2M_Unbind(int $InstanzID, string $SourceDevice, string $TargetDevice);
```

Entfernt ein Binding zwischen Quelle und Ziel ohne zusätzliche Cluster-Auswahl.

---

### Z2M_UnbindWithOptions <!-- omit in toc -->

```php
bool Z2M_UnbindWithOptions(int $InstanzID, string $SourceDevice, string $TargetDevice, string $ClustersJSON, bool $SkipDisableReporting);
```

Entfernt ein Binding mit optionaler Cluster-Auswahl. Mit `SkipDisableReporting` kann verhindert werden, dass Zigbee2MQTT automatisch zugehöriges Reporting entfernt.

---

### Z2M_ClearBinds <!-- omit in toc -->

```php
bool Z2M_ClearBinds(int $InstanzID, string $DeviceName);
```

Entfernt alle Bindings eines Geräts über `bridge/request/device/binds/clear`.

---

### Z2M_GetCachedDeviceEndpoints <!-- omit in toc -->

```php
string Z2M_GetCachedDeviceEndpoints(int $InstanzID, string $DeviceName);
```

Liefert die in der Bridge zwischengespeicherten Endpoint-Daten eines Geräts als JSON. Die Daten stammen aus dem retained Zigbee2MQTT-Topic `bridge/devices` und enthalten, sofern Zigbee2MQTT sie meldet, auch vorhandene `bindings` und `configured_reportings`.

Diese Funktion wird von den Device-Instanzen genutzt, um den Bereich **Binding und Reporting** zu aktualisieren. Zigbee2MQTT bietet keinen separaten Request zum Lesen vorhandener Bindings; die Anzeige basiert deshalb auf dem zuletzt empfangenen `bridge/devices` Cache.

---

### Z2M_ConfigureReporting <!-- omit in toc -->

```php
bool Z2M_ConfigureReporting(int $InstanzID, string $DeviceName, string $Endpoint, string $Cluster, string $Attribute, int $MinimumReportInterval, int $MaximumReportInterval, string $ReportableChange, string $OptionsJSON);
```

Konfiguriert Zigbee Attribute Reporting. `ReportableChange` kann leer bleiben, wenn das Attribut keinen Change-Wert unterstützt. `OptionsJSON` ist optional und muss bei Nutzung ein JSON-Objekt sein.

---

### Z2M_ReadReporting <!-- omit in toc -->

```php
string Z2M_ReadReporting(int $InstanzID, string $DeviceName, string $Endpoint, string $Cluster, string $AttributesJSON, string $ManufacturerCode);
```

Liest die Reporting-Konfiguration eines oder mehrerer Attribute. `AttributesJSON` kann ein JSON-Array oder eine kommaseparierte Attributliste sein. Rückgabe ist ein JSON-String mit den Antwortdaten oder leer bei Fehler.

---

### Z2M_RequestNetworkmap <!-- omit in toc -->

```php
bool Z2M_RequestNetworkmap(int $InstanzID);
```

Fordert die Zigbee-Netzwerkkarte in Zigbee2MQTT an. Die Anfrage wird asynchron gesendet, da die Erstellung der Netzwerkkarte länger dauern kann.
Das Ergebnis wird nach Eingang der Zigbee2MQTT-Antwort in der Bridge-Instanz als Variable `Netzwerkkarte` abgelegt.

---

### Z2M_HealthCheck <!-- omit in toc -->

```php
bool Z2M_HealthCheck(int $InstanzID);
```

Führt `bridge/request/health_check` aus und speichert das Ergebnis im Diagnosebereich der Bridge. `true` bedeutet, dass Zigbee2MQTT `healthy: true` gemeldet hat.

---

### Z2M_CoordinatorCheck <!-- omit in toc -->

```php
bool Z2M_CoordinatorCheck(int $InstanzID);
```

Führt `bridge/request/coordinator_check` aus und zeigt fehlende Router im Diagnosebereich der Bridge an. `true` bedeutet, dass keine fehlenden Router gemeldet wurden.

---

### Z2M_ClearBridgeDiagnostics <!-- omit in toc -->

```php
bool Z2M_ClearBridgeDiagnostics(int $InstanzID);
```

Leert die gesammelten Bridge-Events, Warnungen/Fehler und Gerätediagnosen. Die letzten Health- und Coordinator-Check-Ergebnisse bleiben erhalten.

---

### Z2M_CreateBackup <!-- omit in toc -->

```php
string Z2M_CreateBackup(int $InstanzID);
```

Erstellt über `bridge/request/backup` ein Zigbee2MQTT-Backup und gibt das von Zigbee2MQTT gelieferte Base64-kodierte ZIP zurück. Im Bridge-Wartungsbereich wird dieser Rückgabewert für den Download als ZIP dekodiert.

---

### Z2M_AddInstallCode <!-- omit in toc -->

```php
bool Z2M_AddInstallCode(int $InstanzID, string $Code);
```

Sendet einen Zigbee-3.0-Install-Code an Zigbee2MQTT. Der Code wird nicht in Symcon gespeichert.

---

### Z2M_TouchlinkScan <!-- omit in toc -->

```php
string Z2M_TouchlinkScan(int $InstanzID);
```

Startet einen Touchlink-Scan über Zigbee2MQTT. Der Scan kann bis zu etwa eine Minute dauern und die Zigbee-Kommunikation währenddessen stören. Die gefundenen Geräte werden im Bridge-Wartungsbereich angezeigt. Rückgabe ist ein JSON-String mit der Zigbee2MQTT-Antwort oder leer bei Fehler.

---

### Z2M_TouchlinkIdentify <!-- omit in toc -->

```php
bool Z2M_TouchlinkIdentify(int $InstanzID, string $IeeeAddress, int $Channel);
```

Lässt ein per Touchlink-Scan gefundenes Gerät identifizieren.

---

### Z2M_TouchlinkFactoryReset <!-- omit in toc -->

```php
bool Z2M_TouchlinkFactoryReset(int $InstanzID, string $IeeeAddress = '', int $Channel = 0);
```

Startet einen Touchlink-Factory-Reset. Mit IEEE-Adresse und Kanal wird ein konkretes Scan-Ergebnis adressiert. Ohne Ziel setzt Zigbee2MQTT das nächstgelegene gefundene Touchlink-Gerät zurück; diese Funktion sollte nur bewusst genutzt werden.

---

### Z2M_RenameDevice <!-- omit in toc -->

```php
bool Z2M_RenameDevice(int $InstanzID, string $OldDeviceName, string $NewDeviceName);
```

Benennt ein Zigbee2MQTT-Gerät um. Danach ändert sich auch das MQTT-Topic des Geräts.

---

### Z2M_RemoveDevice <!-- omit in toc -->

```php
bool Z2M_RemoveDevice(int $InstanzID, string $DeviceName);
```

Entfernt ein Gerät aus Zigbee2MQTT.

---

### Z2M_SetDeviceOptions <!-- omit in toc -->

```php
bool Z2M_SetDeviceOptions(int $InstanzID, string $DeviceName, string $OptionsJSON);
```

Setzt Zigbee2MQTT-Geräteoptionen über `bridge/request/device/options`. `OptionsJSON` muss ein JSON-Objekt sein, z. B. `{"transition":1}` oder `{"filtered_attributes":["battery"]}`.

Typische Optionen sind `transition`, `debounce`, `debounce_ignore`, `disable_automatic_update_check`, `disabled`, `filtered_attributes`, `filtered_cache`, `filtered_optimistic`, `icon`, `optimistic`, `qos`, `retain`, `retention`, `throttle`, `homeassistant` sowie gerätespezifische `definition.options`. Die Geräteinstanz zeigt hierfür passende Editoren an.

---

### Z2M_CheckOTAUpdate <!-- omit in toc -->

```php
bool Z2M_CheckOTAUpdate(int $InstanzID, string $DeviceName);
```

Prüft, ob für das angegebene Gerät ein OTA-Update verfügbar ist.
`true` bedeutet, dass Zigbee2MQTT ein verfügbares Update meldet. `false` bedeutet entweder kein verfügbares Update oder einen Fehler bei der Anfrage.

---

### Z2M_CheckOTAUpdateWithUrl <!-- omit in toc -->

```php
bool Z2M_CheckOTAUpdateWithUrl(int $InstanzID, string $DeviceName, string $Url);
```

Prüft ein OTA-Update gegen einen eigenen OTA-Index. `Url` kann eine erreichbare URL oder ein lokaler Pfad aus Sicht der Zigbee2MQTT-Installation sein.

---

### Z2M_CheckOTADowngrade <!-- omit in toc -->

```php
bool Z2M_CheckOTADowngrade(int $InstanzID, string $DeviceName);
```

Prüft, ob für das angegebene Gerät ein OTA-Downgrade verfügbar ist.

---

### Z2M_CheckOTADowngradeWithUrl <!-- omit in toc -->

```php
bool Z2M_CheckOTADowngradeWithUrl(int $InstanzID, string $DeviceName, string $Url);
```

Prüft ein OTA-Downgrade gegen einen eigenen OTA-Index. `Url` kann eine erreichbare URL oder ein lokaler Pfad aus Sicht der Zigbee2MQTT-Installation sein.

---

### Z2M_PerformOTAUpdate <!-- omit in toc -->

```php
bool Z2M_PerformOTAUpdate(int $InstanzID, string $DeviceName);
```

Startet ein OTA-Update für das angegebene Gerät. Die Anfrage wird asynchron gesendet, da der Updatevorgang in Zigbee2MQTT länger dauern kann.
`true` bedeutet, dass der Update-Request an Zigbee2MQTT übergeben wurde.

---

### Z2M_PerformOTAUpdateWithUrl <!-- omit in toc -->

```php
bool Z2M_PerformOTAUpdateWithUrl(int $InstanzID, string $DeviceName, string $Url);
```

Startet ein OTA-Update mit einer eigenen Firmware-Datei oder einem eigenen OTA-Index. Die Anfrage wird asynchron gesendet.

---

### Z2M_PerformOTADowngrade <!-- omit in toc -->

```php
bool Z2M_PerformOTADowngrade(int $InstanzID, string $DeviceName);
```

Startet ein OTA-Downgrade für das angegebene Gerät. Die Anfrage wird asynchron gesendet.

---

### Z2M_PerformOTADowngradeWithUrl <!-- omit in toc -->

```php
bool Z2M_PerformOTADowngradeWithUrl(int $InstanzID, string $DeviceName, string $Url);
```

Startet ein OTA-Downgrade mit einer eigenen Firmware-Datei oder einem eigenen OTA-Index. Die Anfrage wird asynchron gesendet.

---

### Z2M_ScheduleOTAUpdate <!-- omit in toc -->

```php
bool Z2M_ScheduleOTAUpdate(int $InstanzID, string $DeviceName);
```

Plant ein OTA-Update für die nächste OTA-Anfrage des Geräts. Das ist besonders für batteriebetriebene Geräte hilfreich.

---

### Z2M_ScheduleOTAUpdateWithUrl <!-- omit in toc -->

```php
bool Z2M_ScheduleOTAUpdateWithUrl(int $InstanzID, string $DeviceName, string $Url);
```

Plant ein OTA-Update mit eigener Firmware-Datei oder eigenem OTA-Index für die nächste OTA-Anfrage des Geräts.

---

### Z2M_ScheduleOTADowngrade <!-- omit in toc -->

```php
bool Z2M_ScheduleOTADowngrade(int $InstanzID, string $DeviceName);
```

Plant ein OTA-Downgrade für die nächste OTA-Anfrage des Geräts.

---

### Z2M_ScheduleOTADowngradeWithUrl <!-- omit in toc -->

```php
bool Z2M_ScheduleOTADowngradeWithUrl(int $InstanzID, string $DeviceName, string $Url);
```

Plant ein OTA-Downgrade mit eigener Firmware-Datei oder eigenem OTA-Index für die nächste OTA-Anfrage des Geräts.

---

### Z2M_UnscheduleOTAUpdate <!-- omit in toc -->

```php
bool Z2M_UnscheduleOTAUpdate(int $InstanzID, string $DeviceName);
```

Hebt eine geplante OTA-Aktualisierung oder ein geplantes OTA-Downgrade für das angegebene Gerät wieder auf.

## 7. Aktionen

Keine Aktionen verfügbar.

## 8. Anhang

### 1. Changelog

[Changelog der Library](../README.md#5-changelog)

### 2. Spenden

Dieses Modul ist für die nicht kommerzielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:

<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=EK4JRP87XLSHW" target="_blank"><img src="https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donate_LG.gif" border="0" /></a> <a href="https://www.amazon.de/hz/wishlist/ls/3JVWED9SZMDPK?ref_=wl_share" target="_blank">Amazon Wunschzettel</a>

### 3. Lizenz

[CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)
