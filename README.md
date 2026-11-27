[![SDK](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Module Version](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2FBurki24%2FZigbee2MQTT%2Frefs%2Fheads%2FSymcon-9.1%2Flibrary.json&query=%24.version&label=Modul%20Version&color=blue)](https://community.symcon.de/t/modul-zigbee2mqtt-v6-0-testing/144007)
[![Symcon Version](https://img.shields.io/badge/Symcon%20Version-9.1%3E-green)](https://www.symcon.de/de/service/dokumentation/einfuehrung/systemvoraussetzungen/versionenuebersicht/#version-91)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![Check Style](https://github.com/Burki24/Zigbee2MQTT/actions/workflows/style.yml/badge.svg?branch=Symcon-9.1)](https://github.com/Burki24/Zigbee2MQTT/actions/workflows/style.yml?query=branch%3ASymcon-9.1)
[![Run Tests](https://github.com/Burki24/Zigbee2MQTT/actions/workflows/tests.yml/badge.svg?branch=Symcon-9.1)](https://github.com/Burki24/Zigbee2MQTT/actions/workflows/tests.yml?query=branch%3ASymcon-9.1)

# Zigbee2MQTT  <!-- omit in toc -->

[Deutsch](README.md) | [English](README.en.md)

Anbindung von [zigbee2mqtt](https://www.zigbee2mqtt.io) an Symcon.

## Inhaltsverzeichnis  <!-- omit in toc -->

- [1. Voraussetzungen](#1-voraussetzungen)
- [2. Enthaltene Module](#2-enthaltene-module)
- [3. Installation](#3-installation)
  - [3.1 Neuinstallation](#31-neuinstallation)
  - [3.2 Update von Modul Version 4.5 auf 5.x](#32-update-von-modul-version-45-auf-5x)
  - [3.3 Update von Modul Version 5.x auf 6.0](#33-update-von-modul-version-5x-auf-60)
  - [3.4 Installation der Symcon-Extension in Zigbee2MQTT](#34-installation-der-symcon-extension-in-zigbee2mqtt)
- [4. Konfiguration in Symcon](#4-konfiguration-in-symcon)
  - [4.1 Begriffe: Kacheln, Darstellungen und Profile](#41-begriffe-kacheln-darstellungen-und-profile)
  - [4.2 Tile-Visualisierung](#42-tile-visualisierung)
  - [4.3 Variablenverwaltung](#43-variablenverwaltung)
  - [4.4 Wartung verwaister Variablen](#44-wartung-verwaister-variablen)
  - [4.5 Symcon-Actions](#45-symcon-actions)
  - [4.6 Lokale Datenspeicherung und instanzübergreifende Prüfungen](#46-lokale-datenspeicherung-und-instanzübergreifende-prüfungen)
- [5. Changelog](#5-changelog)
- [6. Spenden](#6-spenden)
- [7. Drittanbieter-Komponenten](#7-drittanbieter-komponenten)
- [8. Lizenz](#8-lizenz)

## 1. Voraussetzungen

- mindestens Symcon Version 9.1
- MQTT-Broker (interner MQTT-Server von Symcon oder externer z.B. Mosquitto)
- installiertes und lauffähiges [zigbee2mqtt](https://www.zigbee2mqtt.io)

Für Symcon 9.0 steht der Zweig [dev_V6](https://github.com/Burki24/Zigbee2MQTT/tree/dev_V6) zur Verfügung.

## 2. Enthaltene Module

- [Zigbee2MQTT Discovery](Discovery/README.md)
- [Zigbee2MQTT Konfigurator](Configurator/README.md)
- [Zigbee2MQTT Bridge](Bridge/README.md)
- [Zigbee2MQTT Gerät](Device/README.md)
- [Zigbee2MQTT Gruppe](Group/README.md)
- [Zigbee2MQTT Netzwerkkarte](NetworkMap/README.md)

 Details zu jedem Typ sind direkt in der Dokumentation der jeweiligen Module beschrieben.

## 3. Installation

### 3.1 Neuinstallation

Zuerst ist eine funktionierende Zigbee2MQTT Umgebung gemäß der [Installationsanleitung von Zigbee2MQTT (Link)](https://www.zigbee2mqtt.io/guide/getting-started/) einzurichten.

Ein hierfür benötigter MQTT-Broker ist in Symcon verfügbar und muss entsprechend **vorher** [in Symcon als Instanz erstellt werden (Link)](https://www.symcon.de/de/service/dokumentation/modulreferenz/mqtt/mqtt-server/), sofern er nicht schon vorhanden ist.
Ein MQTT-Konfigurator wird für Zigbee2MQTT nicht benötigt!

Die Installation des Zigbee2MQTT Moduls erfolgt anschließend über den Module Store in der Symcon Konsole.
![Modul-Store](imgs/store.png)

Nach der Installation fragt die Konsole ob eine [Zigbee2MQTT-Discovery](Discovery/README.md)-Instanz erstellt werden soll.
![Module-Store](imgs/install.png)

Weitere Schritte zur Ersteinrichtung sind unter dem [Zigbee2MQTT-Discovery](Discovery/README.md)-Modul beschrieben.

---

### 3.2 Update von Modul Version 4.5 auf 5.x

> [!IMPORTANT]
> **Bitte diese Migrationsanleitung genau lesen und beachten, ein downgrade auf eine alte Modul Version ist nur mit einem Symcon-Backup möglich!**

### I. Vorbereitung <!-- omit in toc -->

- Bevor das Update über den Modul-Store durchgeführt werden kann, ist sicherzustellen das zuvor mindestens die Version 4.6 der [Extension in Zigbee2MQTT](#34-installation-der-ip-symcon-extension-in-zigbee2mqtt) installiert ist.
- Diese wird durch den Anwender über die [Bridge-Instanz](Bridge/README.md) installiert beziehungsweise aktualisiert.
- Alternativ muss die benötigte [Extension in Zigbee2MQTT](#34-installation-der-ip-symcon-extension-in-zigbee2mqtt) manuell ein Update auf Version 4.6 erhalten.

> [!CAUTION]
> Ohne aktuelle Extension wird das Modul Update mit Fehlermeldungen durchgeführt, welche zu unerwarteten Fehlverhalten führen kann.

### II. Modul-Update <!-- omit in toc -->

> [!TIP]
> **Meldungen kontrollieren**
>
> - Während des Updates wird empfohlen das Fenster [Meldungen](https://www.symcon.de/de/service/dokumentation/komponenten/verwaltungskonsole/meldungen/) geöffnet zu lassen um eventuelle Fehlermeldungen nachvollziehen zu können.
> - Das Update anschließend über den [Modul-Store](https://www.symcon.de/de/service/dokumentation/komponenten/verwaltungskonsole/module-store/) durchführen.

---

> [!WARNING]
> **Umstellung auf Symcon-Darstellungen**
>
> - Ab Version 6 werden neue und erneut registrierte Variablen nach Möglichkeit über native Symcon-Variablendarstellungen beschrieben. Das Modul legt dafür keine neuen dynamischen `Z2M.*`-Profile mehr an und gibt auch keine Symcon-Standardprofile mehr aktiv vor.
> - Bereits vorhandene benutzerdefinierte Darstellungen oder benutzerdefinierte Profile haben Vorrang und werden vom Modul nicht überschrieben.
> - Viele Werte bleiben in der Zigbee2MQTT-Skalierung gespeichert; Prozent- und Datumsanzeigen verwenden passende Darstellungen. Abgeleitete Werte wie `color_temp_kelvin`, native Hex-Farbwerte und gegebenenfalls von Millivolt in Volt umgerechnete Spannung werden dagegen bereits als konvertierter Variablenwert gespeichert. Vorhandene Alt-Helligkeitsvariablen können im Zuge der Migration auf die Expose-Skalierung umgestellt werden.
> - Alte Profilreste sollten nicht global per Script gelöscht werden. Verwenden Sie stattdessen die Variablen-Wartung der jeweiligen Geräte- oder Gruppeninstanz; die Bridge zeigt nur, welche Instanzen geprüft werden sollten.

---

> [!WARNING]
> **geänderte Variablen-Idents**
>
> - Die Version 5.0 ändert beim Update alle Ident aller Variablen welche zu einer ZigbeeMQTT-Instanz gehören.
> - Diese Änderung betrifft nur User welche mit Scripten auf Variablen per Ident (z.B. Z2M_Brightness) und nicht per ObjektID (z.B. 12345) zugreifen.
> - Die Variablen selbst bleiben dabei erhalten, so dass sich hier keine ObjektIDs ändern, und entsprechend auch keine Änderungen an Ereignissen, Links, Automationen etc... ergeben.

---

> [!CAUTION]
> **geänderte Variablentypen**
>
> Folgende Liste enthält alle Variablen wo zuvor eine Variable vom falschen Typ genutzt wurde.
> Diese werden nicht migriert, sondern bleiben erhalten.
> Es werden die neuen Variablen zusätzlich angelegt, so dass hier anschließend manuell z.B. Links oder Ereignisse, angepasst werden müssen.
>
> | Name                 | Ident Alt             | Type Alt | Ident Neu              | Typ neu |
> | :------------------- | :-------------------- | :------- | :--------------------- | ------- |
> | Aktion Übergangszeit | Z2M_ActionTransTime   | int      | action_transition_time | float   |
> | Aktion Transaktion   | Z2M_ActionTransaction | float    | action_transaction     | int     |
> | X Achse              | Z2M_XAxis             | float    | x_axis                 | int     |
> | Y Achse              | Z2M_YAxis             | float    | y_axis                 | int     |
> | Z Achse              | Z2M_ZAxis             | float    | Z_axis                 | int     |

### 3. Zigbee2MQTT Version <!-- omit in toc -->

- Ein Update auf Zigbee2MQTT Version 2.0 oder neuer kann nach dem Update des Moduls durchgeführt werden.
- Hierzu sind die Anleitungen unter [zigbee2mqtt.io](https://www.zigbee2mqtt.io/guide/installation/) zu beachten.
- In Symcon sollte eine [Bridge-Instanz](Bridge/README.md) eingerichtet sein, damit die korrekte [Extension in Zigbee2MQTT](#34-installation-der-ip-symcon-extension-in-zigbee2mqtt) nach Bestätigung durch den Anwender installiert werden kann.

---

<a id="33-update-von-modul-version-542-auf-60"></a>

### 3.3 Update von Modul Version 5.x auf 6.0

> [!CAUTION]
> **Beta 6.0: „Erkennen“ wird als neue String-Variable angelegt!** Bestehende Skripte, Links und Automationen müssen bei Bedarf auf die neue ID und den Befehlswert `identify` statt `true` umgestellt werden. Die bisherige Bool-Variable bleibt mit ihren Benutzeranpassungen und ihrer bisherigen Sichtbarkeit erhalten; nur ihre veraltete Modul-Standardaktion wird deaktiviert. Benutzerdefinierte Aktionen bleiben erhalten. Es gibt keine automatische Weiterleitung alter Modulaufrufe. Details stehen in der [Gerätedokumentation](Device/README.md) und im [Changelog](CHANGELOG.md).

> [!IMPORTANT]
> **Der Zweig Symcon-9.1 benötigt mindestens Symcon 9.1. Vor dem Update ist ein vollständiges Symcon-Backup zu erstellen. Ein Downgrade auf Version 5.x sollte nur durch Wiederherstellung dieses Backups erfolgen.**

Version 6.0 migriert die Module auf `IPSModuleStrict` und erweitert Geräte-, Gruppen- und Bridge-Instanzen deutlich. Bestehende Variablen werden beim Update nicht automatisch gelöscht. Objekt-IDs vorhandener Variablen bleiben erhalten. Trotzdem sollten die folgenden Schritte beachtet werden.

Die [Beta-Versionshinweise in Deutsch und Englisch](docs/BETA_RELEASE_NOTES.md) enthalten die Migrationshinweise und die bekannte WebFront-Einschränkung. Bereits durch ältere Testing-Versionen entfernte Benutzeraktionen oder geänderte Sichtbarkeit werden nicht automatisch wiederhergestellt; die ursprünglichen Einstellungen sind nicht gespeichert und müssen gegebenenfalls anhand eines Backups geprüft werden.

**Klassisches WebFront:** Bei neu angelegten Variablen können zunächst Rohwerte und Eingabefelder erscheinen. Ein Neuladen der Browserseite übernimmt die nativen Darstellungen korrekt. Das Verhalten wurde unter Symcon 9.1 auch unabhängig vom Modul reproduziert und für Symcon 9.0 im Forum gemeldet. Die Kachel-Visualisierung ist davon nicht betroffen.

#### Wichtiger Hinweis zu Variablenprofilen <!-- omit in toc -->

Version 6.0 ersetzt die bisherige automatische Anlage und Pflege dynamischer `Z2M.*`-Variablenprofile durch moderne Symcon-Variablendarstellungen. Das ist eine bewusste Kompatibilitätsänderung gegenüber Version 5.x: neue und erneut registrierte Variablen erhalten keine neu erzeugten Modulprofile mehr, sondern nach Möglichkeit eine native Symcon-Darstellung oder bleiben ohne Modulprofil.

Es gibt keine automatische Löschmigration für vorhandene `Z2M.*`-Profile. Bestehende Variablen, Ereignisse, Links und Objekt-IDs bleiben erhalten. Alte Profile werden nicht ungefragt gelöscht oder überschrieben, weil sie noch von bestehenden Variablen oder eigenen Skripten genutzt werden können. Beim Öffnen und Übernehmen einer Geräte- oder Gruppeninstanz werden vorhandene Variablen mit der aktuellen Modul-Standarddefinition erneut registriert; dadurch können passende Variablen auf native Darstellungen wechseln, ohne benutzerdefinierte Darstellungen oder Profile zu überschreiben.

Skripte, Visualisierungen oder externe Werkzeuge, die gezielt auf Profilnamen wie `Z2M.*`, `~Color`, `~Intensity.100` oder andere automatisch gesetzte Profile geprüft haben, sollten nach dem Update kontrolliert werden. Für neue Logik sollte nicht mehr der Profilname, sondern Ident, Variablentyp, Wert und gegebenenfalls die Symcon-Darstellung ausgewertet werden.

#### I. Vorbereitung <!-- omit in toc -->

1. **Symcon-Version prüfen**
   Vor dem Modulupdate muss Symcon mindestens in Version 9.1 installiert sein.

2. **Symcon-Backup erstellen**
   Vor dem Update ist ein vollständiges Symcon-Backup anzulegen. Dies ist insbesondere erforderlich, falls auf Version 5.x zurückgewechselt werden soll.

3. **Bridge-Instanz kontrollieren**
   Es sollte eine eingerichtete und erreichbare [Bridge-Instanz](Bridge/README.md) vorhanden sein. Das MQTT-Basistopic muss stimmen und Zigbee2MQTT muss laufen. Die Bridge bietet benötigte Extension-Updates zur manuellen Bestätigung an.

4. **Meldungsfenster öffnen**
   Während des Updates sollte das Fenster [Meldungen](https://www.symcon.de/de/service/dokumentation/komponenten/verwaltungskonsole/meldungen/) geöffnet bleiben. So lassen sich eventuelle Hinweise während der Migration nachvollziehen.

5. **TLS externer MQTT-Broker prüfen**
   Die Discovery akzeptiert für ihre direkte Suche an externen MQTT-Brokern weiterhin unverschlüsselte `mqtt://`-Verbindungen oder `mqtts://`-Verbindungen. Bei `mqtts://` werden Zertifikat und Hostname standardmäßig geprüft. Für lokale Broker mit selbstsignierten Zertifikaten kann die Zertifikats- und Hostnamenprüfung in der Discovery bewusst deaktiviert werden. Die Verbindung bleibt dann verschlüsselt, die Identität des Brokers wird aber nicht mehr vollständig geprüft. Ein automatischer Rückfall auf eine unverschlüsselte Verbindung findet nicht statt. Bereits konfigurierte Zigbee2MQTT-Instanzen verwenden weiterhin ihren vorhandenen Symcon-MQTT-Splitter und sind von dieser Discovery-Prüfung nicht betroffen.

#### II. Modulupdate <!-- omit in toc -->

Das Update kann anschließend über den [Modul-Store](https://www.symcon.de/de/service/dokumentation/komponenten/verwaltungskonsole/module-store/) durchgeführt werden.

Während des Updates können vorübergehend Warnungen auftreten, wenn andere Symcon-Module zeitgleich auf Zigbee2MQTT-Instanzen zugreifen, deren Konfiguration gerade neu angewendet wird. Bleiben Meldungen nach Abschluss bestehen, sind die Bridge-Instanz und die betroffenen Geräteinstanzen erneut zu öffnen und anzuwenden.

Bei älteren Migrationen bleiben vorhandene Exposes-Dateien als Wiederherstellungsquelle erhalten. Fehlerhafte Altdaten werden nicht stillschweigend durch leere Daten ersetzt. Nach einem Migrationsfehler wird der vorherige MQTT-Verarbeitungszustand wiederhergestellt; die im Meldungsfenster genannte Ursache muss vor einem erneuten Versuch behoben werden.

#### III. Symcon-Extension prüfen <!-- omit in toc -->

Nach dem Update ist die Bridge-Konfiguration zu öffnen und der Extension-Stand zu prüfen. Die Modulbibliothek enthält die Symcon-Extension **6.10 für Zigbee2MQTT 2.x** und **6.09 für Zigbee2MQTT 1.x**. Beide Extension-Varianten behandeln ungültige JSON-Anfragen kontrolliert und unterstützen mehrstufige MQTT-Basistopics. Dynamische Gerätefunktionen werden auch in Gruppen berücksichtigt. Die OTA-Unterstützung wird anhand aktueller sowie älterer Zigbee2MQTT-Gerätedefinitionen erkannt. Ab Version 6.09 berücksichtigt die Gruppenabfrage die verfügbaren Mitglieder auch dann, wenn einzelne Gerätedefinitionen oder Exposes fehlen. Version 6.10 ergänzt die Anzeige des beobachteten Pfads zum Koordinator für Z-Stack.

Die Bridge zeigt die Zigbee2MQTT-Version sowie die installierte und die mitgelieferte Extension-Version an. Eine separate Online-/Offline-Statuszeile weist bei Offline-Status auf den zuletzt gemeldeten Versionsstand hin; die Versionszeile bleibt bei Verbindungswechseln unverändert. Installation und Update starten ausschließlich durch den Anwender über **Symcon Erweiterung installieren oder updaten** oder anhand der [manuellen Anleitung](#34-installation-der-ip-symcon-extension-in-zigbee2mqtt). Ein Modulupdate installiert die Extension nicht automatisch.

> [!CAUTION]
> Wenn mehrere alte Symcon-Extensions gleichzeitig in Zigbee2MQTT hinterlegt sind, müssen Dubletten manuell entfernt werden. Mehrere aktive Erweiterungen können zu doppelten Antworten und Fehlverhalten führen.

#### IV. Bestehende Geräte kontrollieren <!-- omit in toc -->

1. **Visualisierung prüfen**
   Passende Geräte erhalten automatisch moderne Variablendarstellungen oder HTML-SDK-Kacheln. Die Erkennen-Migration und profilabhängige Skripte benötigen die oben beschriebene Prüfung; die Wahl einer Kachel allein ändert keine Automation. Falls eine automatisch gewählte Spezialkachel nicht gewünscht ist, kann sie in der jeweiligen Geräteinstanz unter **Visualisierung** deaktiviert werden.

2. **Farbtemperatur bei Leuchtmitteln prüfen**
   Der Kelvin-Bereich wird aus den von Zigbee2MQTT gelieferten Exposes berechnet. Einige Zigbee2MQTT-Device-Definitionen melden ungenaue Grenzen. Bei betroffenen Leuchtmitteln kann der Kelvin-Bereich in der Geräteinstanz unter **Farbtemperatur-Visualisierung** korrigiert werden.

3. **Neue Variablen beachten**
   Version 6.0 kann zusätzliche Variablen aus neuen oder präziser ausgewerteten Exposes sowie aus nachgelieferten Payloads anlegen. Bestehende Variablen werden dabei nicht automatisch entfernt.

4. **Variablenverwaltung beachten**
   Geräteinstanzen führen ab Version 6.0 einen lokalen Variablenkatalog. Wird eine bekannte Variable künftig manuell gelöscht, legt das Modul sie nicht ungefragt erneut an. Unter **Variablen** kann sie gezielt wieder angelegt oder für die automatische Anlage freigegeben werden.

5. **Skripte und Automationen prüfen**
   Die bisher dokumentierten Bridge-Funktionen bleiben verfügbar. Erweiterte Funktionen verwenden zusätzliche optionale Parameter. Trotzdem sollten eigene Skripte geprüft werden, wenn sie Variablenprofile, Darstellungen oder den exakten Bestand der automatisch angelegten Variablen auswerten.

#### V. Optionale neue Funktionen bewusst verwenden <!-- omit in toc -->

Die folgenden Funktionen werden durch das Update nur bereitgestellt und verändern bestehende Installationen nicht automatisch:

- **Variablen-Wartung:** Die Bridge kann verwaiste Variablen zentral suchen und zeigt betroffene Instanzen kompakt an. Die Prüfung und ein mögliches Löschen erfolgen ausschließlich in der jeweils zuständigen Geräte- oder Gruppeninstanz. Es wird nichts automatisch gelöscht.
- **Binding und Reporting:** Bestehende Bindings bleiben erhalten. Für eine aktuelle Anzeige in einer Geräteinstanz zunächst **Endpoint-Daten aktualisieren** verwenden. Batteriegeräte müssen gegebenenfalls aufgeweckt werden.
- **Blocklist und Passlist:** Die Passlist ist restriktiv und kann Geräte aus dem Zigbee-Netz entfernen, wenn diese nicht enthalten sind. Änderungen deshalb nur mit Bedacht durchführen.
- **Zigbee2MQTT-Backup:** Erstellte ZIP-Dateien werden unter `user/IPSZigbee2MQTT/backups` auf dem Symcon-Server gespeichert und nicht direkt im Browser heruntergeladen.
- **Install-Code-Katalog:** Gespeicherte Install-Codes werden maskiert dargestellt, liegen aber nicht verschlüsselt in einem Bridge-Attribut und können deshalb auch Bestandteil von Symcon-Backups sein.
- **OTA-Updates:** Zum Schutz des Zigbee-Netzes immer nur ein aktives OTA-Update gleichzeitig durchführen. Batteriegeräte müssen eventuell vor einer Prüfung oder Planung aufgeweckt werden.

---

<a id="34-installation-der-ip-symcon-extension-in-zigbee2mqtt"></a>

### 3.4 Installation der Symcon-Extension in Zigbee2MQTT

Für den fehlerfreien Betrieb des Moduls wird eine Erweiterung (Extension) in Zigbee2MQTT benötigt.

**Folgende Varianten zum Einrichten der Erweiterung sind möglich:**

**1.** Über die [Bridge](Bridge/README.md)-Instanz in Symcon (empfohlen)

**2.** Über das Z2M Frontend den Inhalt der passenden Datei unter dem Menüpunkt Erweiterungen hinzufügen.

**3.** Die passende Datei in das der Z2M Version entsprechende Verzeichnis auf dem Rechner, wo Z2M installiert ist ablegen. (Expertenwissen zu Z2M erforderlich)

Extension-Dateien und Pfade innerhalb Z2M:

- **Z2M bis Version 1.42**
  - [IPSymconExtension.js](libs/IPSymconExtension.js)
  - Z2M Pfad: **`data/extension`**
- **Z2M ab Version 2.0**
  - [IPSymconExtension2.js](libs/IPSymconExtension2.js)
  - Z2M Pfad: **`data/external_extensions`**

**Anleitungen zum Einrichten der Erweiterung:**

**zu 1.** Ist in der Dokumentation der [Bridge](Bridge/README.md)-Instanz beschrieben.

**zu 2.** Das Frontend von Z2M im Browser öffnen und den Punkt "Entwicklerkonsole" wählen.
   Den Reiter "Externe Erweiterungen" auswählen.
   Eine neue Erweiterung erstellen und den Namen z.B. symcon.js vergeben.
   ![Erweiterungen](imgs/z2m_extension_anlegen.png)
   Den Inhalt (Code) aus
   [IPSymconExtension.js für Z2M bis Version 1.42](libs/IPSymconExtension.js)
   oder
   [IPSymconExtension.js für Z2M ab Version 2.0](libs/IPSymconExtension2.js)
   im Code Bereich einfügen und speichern.
   ![Code Eingabe](imgs/z2m_extension_code.png)
   Danach sollte Z2M neu gestartet werden:
   ![Code Eingabe](imgs/z2m_extension_restart.png)

**zu 3.** Sollte nur von versierten Usern gemacht werden, da es aufgrund der vielzahl an Systemen unter welchen Z2M laufen kann, keine global gültige Anleitung gibt.

<a id="4-konfiguration-in-ip-symcon"></a>

## 4. Konfiguration in Symcon

Bitte den einzelnen Modulen entnehmen:

- [Bridge](Bridge/README.md)
- [Configurator](Configurator/README.md)
- [Device](Device/README.md)
- [Group](Group/README.md)

### 4.1 Begriffe: Kacheln, Darstellungen und Profile

In dieser Dokumentation werden die Begriffe bewusst getrennt:

- **Kachel** oder **Visualisierung** meint die Darstellung einer Instanz in der Symcon Tile-Visualisierung, insbesondere eigene HTML-SDK-Kacheln wie Schaltaktor-, Heizungs-, Sensor- oder Netzwerkkarten-Kacheln.
- **Variablendarstellung** meint die moderne Symcon-Darstellung einer einzelnen Variable, die beim Anlegen der Variable als Standarddarstellung über `RegisterVariable*()` beziehungsweise die Symcon-Maintenance-Mechanismen gesetzt wird.
- **Profil** meint ein Symcon-Variablenprofil. Neue und erneut registrierte Variablen werden bevorzugt ueber native Symcon-Variablendarstellungen oder ohne Modulprofil angelegt; Symcon-Standardprofile werden nicht mehr als Modulvorgabe gesetzt. Bestehende Legacy-Variablen koennen weiterhin ein altes Modulprofil besitzen; dieses wird nicht geloescht, aber bei der erneuten Registrierung nicht mehr als Modulstandard festgehalten.

Benutzerdefinierte Darstellungen und Profile haben in Symcon eine höhere Priorität und bleiben vollständig in der Hoheit des Anwenders. Deshalb setzt das Modul produktiv keine Custom-Presentations oder Custom-Profile über `IPS_SetVariableCustomPresentation()` oder `IPS_SetVariableCustomProfile()`.

### 4.2 Tile-Visualisierung

Geräte-Instanzen können automatisch eine moderne HTML-SDK-Kachel verwenden, wenn die von Zigbee2MQTT gelieferten Exposes eindeutig zu einem unterstützten Gerätetyp passen.

Unterstützt werden derzeit eigene Kacheln für:

- Heizungen und Heizventile
- Schaltaktoren mit Leistungsmessung, auch mit mehreren Schaltausgängen
- Sensoren wie Temperatur, Luftfeuchtigkeit, Bodenfeuchtigkeit, Helligkeit und Batterie
- Sicherheits-, Kontakt- und Präsenzsensoren inklusive Öffnungszustand, Alarm und Leck-/Gas-/Rauchmeldern
- Fenstergriffe
- Taster, Fernbedienungen und Szenen-Auslöser

Die Instanz-Konfiguration zeigt nur die Visualisierungsoptionen an, die für das jeweilige Gerät verfügbar sind. Dort kann eine automatisch gewählte Spezialkachel auch deaktiviert werden, wenn stattdessen die Standard-Visualisierung von Symcon genutzt werden soll.

Die eigenen HTML-SDK-Kacheln übernehmen Schrift- und Grundfarben vom aktiven Symcon Tile-Theme. Eigene Farben werden nur für Zustände wie Alarm, OK, Aktiv/Inaktiv oder Messwertverläufe verwendet.

Leuchten mit RGB/HS/XY-Farbe, Tunable White und Rollläden verwenden die nativen Symcon-Darstellungen und Standardkacheln. Separate RGB-/Tunable-White-HTML-Kacheln werden nicht mitgeliefert.

Details stehen in der [Dokumentation des Geräte-Moduls](Device/README.md#42-visualisierung-und-kacheln).

### 4.3 Variablenverwaltung

Geräte-Instanzen führen einen lokalen Variablenkatalog. Dadurch kann in der Instanz-Konfiguration gesteuert werden, welche bekannten Variablen automatisch angelegt werden dürfen. Vom Anwender gelöschte Variablen werden nicht automatisch wieder erzeugt und können später gezielt wieder angelegt werden.

Details stehen in der [Dokumentation des Geräte-Moduls](Device/README.md#49-variablenverwaltung).

Geräte- und Gruppenoptionen aus Zigbee2MQTT können ebenfalls direkt in Symcon gepflegt werden. Soweit Zigbee2MQTT Typinformationen liefert oder das Modul die Option kennt, werden passende Editoren für Schalter, Auswahllisten, Zahlen, Text, JSON-Objekte und Attributlisten angezeigt.

### 4.4 Wartung verwaister Variablen

Die [Bridge-Funktionen](Bridge/README.md) enthalten eine kompakte Variablen-Wartungsübersicht. Sie sucht innerhalb des zugehörigen MQTT-Splitters und MQTT-Basistopics nach alten Zigbee2MQTT-Variablen, die nicht mehr durch aktuelle Exposes oder das zuletzt bekannte Payload abgedeckt sind, und fasst betroffene Geräte- und Gruppeninstanzen zusammen.

Die Bridge löscht keine Variablen direkt, sondern öffnet die betroffene Instanz für die gezielte Prüfung. Die eigentliche Prüfung und ein mögliches Löschen erfolgen unter **Expertenwerkzeuge → Variablen-Wartung** in der zuständigen Geräte- oder Gruppeninstanz. Diese darf ausschließlich ihre eigenen direkten Variablen verwalten. Archivierte oder referenzierte Variablen sind geschützt, Archivstatus und letzter Schreibzeitpunkt sind sichtbar, und jede Löschung betrifft genau eine Variable, die vorher erneut geprüft und per Popup bestätigt werden muss.

### 4.5 Symcon-Actions

Die mitgelieferten Symcon-Actions sind keine eigenen Module, sondern Aktionsvorlagen für passende Zigbee2MQTT-Geräte- und Gruppeninstanzen. Sie können beispielsweise in Ereignissen, Ablaufplänen oder Automatisierungen genutzt werden.

Details stehen in der [Dokumentation der Zigbee2MQTT Actions](actions/README.md).

### 4.6 Lokale Datenspeicherung und instanzübergreifende Prüfungen

Das Modul greift nur für die nachfolgend beschriebenen Funktionen auf lokale Dateien zu. Dauerhaft erzeugte Dateien werden ausschließlich innerhalb des Symcon-Verzeichnisses unter `user/IPSZigbee2MQTT` gespeichert:

- Zigbee2MQTT-Backups werden nach einer ausdrücklich gestarteten Backup-Aktion unter `user/IPSZigbee2MQTT/backups` abgelegt.
- Exportierte Netzwerkkarten werden nach einer ausdrücklich gestarteten Export-Aktion unter `user/IPSZigbee2MQTT/networkmaps` gespeichert.
- Heruntergeladene Gerätebilder werden gemeinsam unter `user/IPSZigbee2MQTT/icons` zwischengespeichert. Downloads verwenden geprüftes TLS sowie feste Zeit-, Größen-, Format- und Abmessungsgrenzen.
- Große Backup-Antworten werden während der Verarbeitung kurzfristig im System-Temp-Verzeichnis unter `IPSZigbee2MQTT` ausgelagert. Die zugehörige temporäre Datei wird nach der Übernahme in das Backup-Verzeichnis wieder entfernt.

Die Bridge liest für Wartungs-, Diagnose- und OTA-Übersichten Informationen anderer Instanzen dieser Zigbee2MQTT-Bibliothek. Die Variablen-Wartung beschränkt ihre Suche zusätzlich auf Instanzen am selben MQTT-Splitter und mit demselben MQTT-Basistopic. Diese übergreifenden Prüfungen verändern weder fremde Instanzeigenschaften noch deren Variablen.

Bei der Suche nach verwaisten Variablen werden Symcon-Referenzen und der Archivierungsstatus systemweit ausschließlich gelesen, damit referenzierte oder aufgezeichnete Variablen vor einer versehentlichen Löschung geschützt werden. Eine Löschung erfolgt niemals zentral durch die Bridge, sondern nur nach erneuter Prüfung und ausdrücklicher Bestätigung innerhalb der Instanz, der die Variable direkt untergeordnet ist.

## 5. Changelog

Der [vollständige Changelog seit Beta 5.43](CHANGELOG.md) beschreibt die Änderungen gegenüber Commit `ce8aba0de27c8e9a6ffbaa91759009fb34d11806`. [English changelog](CHANGELOG.en.md). Er umfasst den aktuellen Funktionsumfang einschließlich der Änderungen bis zur Beta-Vorbereitung; eine Veröffentlichung wird dadurch nicht vorweggenommen.

Ältere chronologische Zwischenstände und die 5.x-Historie stehen in den [historischen Änderungsnotizen](docs/CHANGELOG_HISTORY.md). Sie ersetzen keine aktuelle Bedienungsanleitung.

## 6. Spenden

Dieses Modul ist für die nicht kommerzielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:

<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=EK4JRP87XLSHW" target="_blank"><img src="https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donate_LG.gif" border="0" /></a> <a href="https://www.amazon.de/hz/wishlist/ls/3JVWED9SZMDPK?ref_=wl_share" target="_blank">Amazon Wunschzettel</a>

## 7. Drittanbieter-Komponenten

Dieses Modul enthält folgende Drittanbieter-Komponenten:

- `libs/phpMQTT.php` basiert auf `phpMQTT` von Blue Rhinos Consulting / Andrew Milsted und steht unter MIT-Lizenz. Der vollständige Lizenztext ist direkt in der Datei enthalten.
- `NetworkMap/assets/cytoscape.min.js` basiert auf [Cytoscape.js](https://js.cytoscape.org/) und steht unter MIT-Lizenz. Der vollständige Lizenztext liegt unter `NetworkMap/assets/CYTOSCAPE-LICENSE.txt`.

Beide Komponenten werden lokal mit dem Modul ausgeliefert. Für die Netzwerkkarte werden keine externen Webressourcen nachgeladen.

## 8. Lizenz

[CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)
