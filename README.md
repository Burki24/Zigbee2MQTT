[![SDK](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Module Version](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2FNall-chan%2FZigbee2MQTT%2Frefs%2Fheads%2Fmain%2Flibrary.json&query=%24.version&label=Modul%20Version&color=blue)](https://community.symcon.de/t/modul-zigbee2mqtt-version-5-x/139819)
[![Symcon Version](https://img.shields.io/badge/Symcon%20Version-9.0%3E-green)](https://www.symcon.de/de/service/dokumentation/einfuehrung/systemvoraussetzungen/versionenuebersicht/#version-90)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![Check Style](https://github.com/Nall-chan/Zigbee2MQTT/workflows/Check%20Style/badge.svg)](https://github.com/Nall-chan/Zigbee2MQTT/actions)
[![Run Tests](https://github.com/Nall-chan/Zigbee2MQTT/workflows/Run%20Tests/badge.svg)](https://github.com/Nall-chan/Zigbee2MQTT/actions)  

# Zigbee2MQTT  <!-- omit in toc -->  

Anbindung von [zigbee2mqtt](https://www.zigbee2mqtt.io) an IP-Symcon.

## Inhaltsverzeichnis  <!-- omit in toc -->

- [1. Voraussetzungen](#1-voraussetzungen)
- [2. Enthaltene Module](#2-enthaltene-module)
- [3. Installation](#3-installation)
  - [3.1 Neuinstallation](#31-neuinstallation)
  - [3.2 Update von Modul Version 4.5 auf 5.x](#32-update-von-modul-version-45-auf-5x)
  - [3.3 Update von Modul Version 5.42 auf 6.0](#33-update-von-modul-version-542-auf-60)
  - [3.4 Installation der IP-Symcon Extension in Zigbee2MQTT](#34-installation-der-ip-symcon-extension-in-zigbee2mqtt)
- [4. Konfiguration in IP-Symcon](#4-konfiguration-in-ip-symcon)
  - [4.1 Begriffe: Kacheln, Darstellungen und Profile](#41-begriffe-kacheln-darstellungen-und-profile)
  - [4.2 Tile-Visualisierung](#42-tile-visualisierung)
  - [4.3 Variablenverwaltung](#43-variablenverwaltung)
  - [4.4 Wartung verwaister Variablen](#44-wartung-verwaister-variablen)
- [5. Changelog](#5-changelog)
- [6. Spenden](#6-spenden)
- [7. Lizenz](#7-lizenz)

## 1. Voraussetzungen

- mindestens IP-Symcon Version 9.0
- MQTT-Broker (interner MQTT-Server von Symcon oder externer z.B. Mosquitto)
- installiertes und lauffähiges [zigbee2mqtt](https://www.zigbee2mqtt.io)

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
- Diese wird automatisch ab Version 4.5 durch die [Bridge-Instanz](Bridge/README.md)  installiert, sofern diese Instanz angelegt wurde.
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
> **geänderte Variablen-Profile**  
>
> - Die Variablen welche bei `Helligkeit` vorher einen Wertebereich von 0 - 254 hatten, werden auf das Profil `~Intensity.100` angepasst. Das Modul rechnet ab sofort automatisch den Wertebereich aus Z2M in Prozent um.  
> - Entsprechende Aktion auf oder Auswertungen des Rohwertes der Variablen sind zu prüfen und gglfs. anzupassen.  

---

> [!TIP]  
> **Alte Variablenprofile löschen**  
> Folgendes Script kann in Symcon ausgeführt werden, um veraltete Variablenprofile zu löschen.
>
> ```php
>$Z2M_Profile = array_filter(IPS_GetVariableProfileList(),function($Profil)
>{
>    return substr($Profil, 0, 4) === 'Z2M.';
>});
>
>foreach (IPS_GetVariableList() as $VariableId)
>{
>    $Variable = IPS_GetVariable($VariableId);
>    $Found = array_search($Variable['VariableProfile'],$Z2M_Profile);
>    if($Found !== false){
>        unset($Z2M_Profile[$Found]);
>    }
>}
>
>foreach ($Z2M_Profile as $Profile)
>{
>    IPS_DeleteVariableProfile($Profile);
>    echo 'Delete: '.$Profile.PHP_EOL;
>}
>```

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
- In Symcon sollte eine [Bridge-Instanz](Bridge/README.md) eingerichtet sein, damit beim Update automatisch die korrekte [Extension in Zigbee2MQTT](#34-installation-der-ip-symcon-extension-in-zigbee2mqtt) installiert wird.

---

### 3.3 Update von Modul Version 5.42 auf 6.0

> [!IMPORTANT]
> **Version 6.0 benötigt mindestens IP-Symcon 9.0. Vor dem Update ist ein vollständiges Symcon-Backup zu erstellen. Ein Downgrade auf Version 5.42 sollte nur durch Wiederherstellung dieses Backups erfolgen.**

Version 6.0 migriert die Module auf `IPSModuleStrict` und erweitert Geräte-, Gruppen- und Bridge-Instanzen deutlich. Bestehende Variablen werden beim Update nicht automatisch gelöscht. Objekt-IDs vorhandener Variablen bleiben erhalten. Trotzdem sollten die folgenden Schritte beachtet werden.

#### I. Vorbereitung <!-- omit in toc -->

1. **IP-Symcon-Version prüfen**
   Vor dem Modulupdate muss IP-Symcon mindestens in Version 9.0 installiert sein.

2. **Symcon-Backup erstellen**
   Vor dem Update ist ein vollständiges Symcon-Backup anzulegen. Dies ist insbesondere erforderlich, falls auf Version 5.42 zurückgewechselt werden soll.

3. **Bridge-Instanz kontrollieren**
   Es sollte eine eingerichtete und erreichbare [Bridge-Instanz](Bridge/README.md) vorhanden sein. Das MQTT-Basistopic muss stimmen und Zigbee2MQTT muss laufen. Die Bridge aktualisiert die benötigte Symcon-Extension normalerweise automatisch.

4. **Meldungsfenster öffnen**
   Während des Updates sollte das Fenster [Meldungen](https://www.symcon.de/de/service/dokumentation/komponenten/verwaltungskonsole/meldungen/) geöffnet bleiben. So lassen sich eventuelle Hinweise während der Migration nachvollziehen.

5. **TLS externer MQTT-Broker prüfen**
   Die Discovery akzeptiert für ihre direkte Suche an externen MQTT-Brokern weiterhin unverschlüsselte `mqtt://`-Verbindungen oder `mqtts://`-Verbindungen. Bei `mqtts://` werden Zertifikat und Hostname standardmäßig geprüft. Für lokale Broker mit selbstsignierten Zertifikaten kann die Zertifikats- und Hostnamenprüfung in der Discovery bewusst deaktiviert werden. Die Verbindung bleibt dann verschlüsselt, die Identität des Brokers wird aber nicht mehr vollständig geprüft. Ein automatischer Rückfall auf eine unverschlüsselte Verbindung findet nicht statt. Bereits konfigurierte Zigbee2MQTT-Instanzen verwenden weiterhin ihren vorhandenen Symcon-MQTT-Splitter und sind von dieser Discovery-Prüfung nicht betroffen.

#### II. Modulupdate <!-- omit in toc -->

Das Update kann anschließend über den [Modul-Store](https://www.symcon.de/de/service/dokumentation/komponenten/verwaltungskonsole/module-store/) durchgeführt werden.

Während des Updates können vorübergehend Warnungen auftreten, wenn andere Symcon-Module zeitgleich auf Zigbee2MQTT-Instanzen zugreifen, deren Konfiguration gerade neu angewendet wird. Bleiben Meldungen nach Abschluss bestehen, sind die Bridge-Instanz und die betroffenen Geräteinstanzen erneut zu öffnen und anzuwenden.

#### III. Symcon-Extension prüfen <!-- omit in toc -->

Nach dem Update ist die Bridge-Konfiguration zu öffnen. Dort muss **Symcon-Erweiterung ist aktuell** angezeigt werden. Version 6.0 benötigt die Symcon-Extension in Version `6.03`.

Die Bridge installiert beziehungsweise aktualisiert die Extension im Normalfall automatisch. Falls Zigbee2MQTT während des Modulupdates nicht erreichbar war oder keine Bridge-Instanz existiert, muss die Extension später über die Bridge oder anhand der [manuellen Anleitung](#34-installation-der-ip-symcon-extension-in-zigbee2mqtt) aktualisiert werden.

> [!CAUTION]
> Wenn mehrere alte Symcon-Extensions gleichzeitig in Zigbee2MQTT hinterlegt sind, müssen Dubletten manuell entfernt werden. Mehrere aktive Erweiterungen können zu doppelten Antworten und Fehlverhalten führen.

#### IV. Bestehende Geräte kontrollieren <!-- omit in toc -->

1. **Visualisierung prüfen**
   Passende Geräte erhalten automatisch moderne Variablendarstellungen oder HTML-SDK-Kacheln. Das verändert die Darstellung, nicht die vorhandenen Automationen. Falls eine automatisch gewählte Spezialkachel nicht gewünscht ist, kann sie in der jeweiligen Geräteinstanz unter **Visualisierung** deaktiviert werden.

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

### 3.4 Installation der IP-Symcon Extension in Zigbee2MQTT

Für den fehlerfreien Betrieb des Moduls wird eine Erweiterung (Extension) in Zigbee2MQTT benötigt.

**Folgende Varianten zum Einreichten der Erweiterung sind möglich:**  

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

## 4. Konfiguration in IP-Symcon

Bitte den einzelnen Modulen entnehmen:

- [Bridge](Bridge/README.md)
- [Configurator](Configurator/README.md)
- [Device](Device/README.md)
- [Group](Group/README.md)

### 4.1 Begriffe: Kacheln, Darstellungen und Profile

In dieser Dokumentation werden die Begriffe bewusst getrennt:

- **Kachel** oder **Visualisierung** meint die Darstellung einer Instanz in der Symcon Tile-Visualisierung, insbesondere eigene HTML-SDK-Kacheln wie Schaltaktor-, Heizungs-, Sensor- oder Netzwerkkarten-Kacheln.
- **Variablendarstellung** meint die moderne Symcon-Darstellung einer einzelnen Variable, die beim Anlegen der Variable als Standarddarstellung über `RegisterVariable*()` beziehungsweise die Symcon-Maintenance-Mechanismen gesetzt wird.
- **Profil** meint ein Symcon-Variablenprofil. Profile werden weiterhin dort genutzt, wo Symcon-Standardprofile oder kompatible Modulprofile fuer Assoziationen, Presets oder alte Visualisierungen fachlich noetig sind. Neue dynamische Z2M-Profile sind nur noch ein Fallback, wenn eine moderne Variablendarstellung oder ein Standardprofil den Wert nicht passend abbilden kann.

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

Details stehen in der [Dokumentation des Geräte-Moduls](Device/README.md#42-visualisierung-und-kacheln).

### 4.3 Variablenverwaltung

Geräte-Instanzen führen einen lokalen Variablenkatalog. Dadurch kann in der Instanz-Konfiguration gesteuert werden, welche bekannten Variablen automatisch angelegt werden dürfen. Vom Anwender gelöschte Variablen werden nicht automatisch wieder erzeugt und können später gezielt wieder angelegt werden.

Details stehen in der [Dokumentation des Geräte-Moduls](Device/README.md#49-variablenverwaltung).

Geräte- und Gruppenoptionen aus Zigbee2MQTT können ebenfalls direkt in Symcon gepflegt werden. Soweit Zigbee2MQTT Typinformationen liefert oder das Modul die Option kennt, werden passende Editoren für Schalter, Auswahllisten, Zahlen, Text, JSON-Objekte und Attributlisten angezeigt.

### 4.4 Wartung verwaister Variablen

Die [Bridge-Funktionen](Bridge/README.md#56-variablen-wartung) enthalten eine kompakte Variablen-Wartungsübersicht. Sie sucht innerhalb des zugehörigen MQTT-Splitters und MQTT-Basistopics nach alten Zigbee2MQTT-Variablen, die nicht mehr durch aktuelle Exposes oder das zuletzt bekannte Payload abgedeckt sind, und fasst klare Löschkandidaten, Review-Kandidaten und Suchlauf-Hinweise pro betroffener Instanz zusammen.

Die eigentliche Prüfung und ein mögliches Löschen erfolgen unter **Expertenwerkzeuge → Variablen-Wartung** in der zuständigen Geräte- oder Gruppeninstanz. Diese darf ausschließlich ihre eigenen direkten Variablen verwalten. Archivierte oder referenzierte Variablen sind geschützt, Archivstatus und letzter Schreibzeitpunkt sind sichtbar, und jede Löschung betrifft genau eine Variable, die vorher erneut geprüft und per Popup bestätigt werden muss.

## 5. Changelog  

**Version 6.00:**

Die Änderungen sind anhand der funktionalen Commits chronologisch gegliedert. Automatisch erzeugte Metadaten-Commits sowie reine Screenshot-Korrekturen werden nicht einzeln aufgeführt.

### 10. bis 15. Mai 2026: IPSModuleStrict und moderne Tile-Visualisierung

- Sämtliche Module wurden auf `IPSModuleStrict` migriert. Die Mindestversion wurde abschließend auf IP-Symcon 9.0 angehoben.
- Numeric-, Enum-, Temperatur- und Farbtemperatur-Exposes erhalten passendere moderne Variablendarstellungen, soweit die Exposes die dafür notwendigen Werte liefern.
- Die Kelvin-Farbtemperaturvariable `color_temp_kelvin` nutzt den aus dem Zigbee2MQTT-Mired-Bereich berechneten Kelvin-Bereich für die Symcon-Standardkachel Beleuchtung.
- Moderne HTML-SDK-Kacheln wurden schrittweise für Heizungen, Schaltaktoren mit Messwerten, Sensoren, Sicherheitsgeräte, Fenstergriffe und Aktionsgeräte ergänzt.
- Heizungs-Kacheln zeigen Ist- und Solltemperatur ohne Ringslider und bedienen die Solltemperatur per Plus-/Minus-Tasten. Später kamen breitere Preset-Tasten und pro Instanz konfigurierbare Solltemperaturen hinzu.
- Schaltaktoren mit Messwerten zeigen Energie, Leistung, Spannung und Strom in einer eigenen Ansicht. Archivierte Werte können direkt aus der Kachel als Graphen geöffnet werden.
- Mehrkanal-Schaltaktoren können mehrere Schaltausgänge in einer gemeinsamen Kachel darstellen. Der Messwertbereich unterstützt auch Frequenz, Leistungsfaktor, Schein-/Blindleistung und erzeugte Energie.
- Sensor-Kacheln unterstützen Temperatur, Luftfeuchtigkeit, Bodenfeuchtigkeit, Helligkeit und Batterie. Bei Pflanzensensoren kann `soil_moisture` als Hauptwert vor der Temperatur dargestellt werden.
- Rollladen und Jalousien verwenden die native Symcon-Shutter-Darstellung. Spezialkacheln werden nur dort ergänzt, wo Exposes eine zusammengefasste Ansicht wirklich benötigen.
- Diagramm-Schaltflächen in Messwert-Kacheln werden nur für tatsächlich archivierte Variablen angezeigt.

### 16. bis 18. Mai 2026: Struktur, Visualisierungsverwaltung und Variablenkatalog

- Die Visualisierungslogik wurde in wiederverwendbare Helper und einen eigenen Verzeichnisbaum unter `libs/Visualization` aufgeteilt.
- Die Verarbeitung in `ModulBase` wurde schrittweise für `RequestAction()`, Payloads, Standardvariablen, Sondervariablen, Farbaktionen, Wertkonvertierung, Presets, Profile und Variablenregistrierung refaktoriert.
- Die Geräte-Konfiguration erhielt einen Visualisierungsbereich, der nur die für die jeweilige Instanz fachlich passenden Kacheloptionen anbietet.
- Temperatur-Visualisierungen können einen konfigurierbaren Fallback-Bereich verwenden, wenn Zigbee2MQTT keine Werte für `value_min` und `value_max` liefert.
- Geräte-Instanzen erhielten einen lokalen Variablenkatalog. Anwender können steuern, welche bekannten Variablen automatisch angelegt werden dürfen. Gelöschte Variablen werden nicht ungefragt erneut erzeugt und können später gezielt wieder freigegeben werden.
- Composite-Exposes werden nur mit tatsächlich anlegbaren Untervariablen geführt. Nicht bedienbare Composite-Eltern erscheinen nicht mehr als eigenständige Variable.
- Automatische Versions- und Build-Anpassungen wurden für neue Commits eingerichtet.

### 19. bis 21. Mai 2026: Geräte-, Gruppen- und Bridge-Funktionen

- Bridge-Requests und Antworten wurden vereinheitlicht und robuster ausgewertet.
- Die Bridge unterstützt zusätzliche OTA-Befehle für Downgrade, Scheduling, Unschedule und eigene OTA-URLs.
- Geräte-Instanzen können Zigbee2MQTT-Geräteoptionen wie `transition`, `debounce`, `filtered_attributes`, `optimistic`, `retain` oder gerätespezifische `definition.options` direkt in der Konfiguration anzeigen und setzen.
- Binding und Reporting können in der Geräte-Konfiguration über Endpoint-, Cluster- und Attributdaten gepflegt werden.
- Reine Tunable-White-Leuchtmittel erhalten eine abgeleitete `color`-Variable, die den aktuellen Weißton als `~HexColor`-Farbwert darstellt.
- Der Kelvin-Bereich der Farbtemperatur kann pro Device überschrieben werden, falls Zigbee2MQTT beziehungsweise dessen Device-Definitionen ungenaue Mired-Grenzen melden.
- Gruppen-Instanzen können Mitglieder einschließlich Endpoints verwalten, Gruppenoptionen setzen und Szenen speichern, hinzufügen, abrufen, umbenennen oder löschen.
- Die Bridge erhielt einen Diagnosebereich für Health Check, Coordinator Check, Bridge-Events, Warnungen und Fehler sowie nicht unterstützte oder unvollständig interviewte Geräte.
- Die Bridge-Wartung erhielt Zigbee2MQTT-Backups, einmaliges Senden von Zigbee-3.0-Install-Codes sowie Touchlink-Scan, Identify und Factory-Reset.
- Sicherheits-Kacheln unterstützen zusätzliche Kontakt- und Alarm-Exposes wie `opening_state`, `alarm_state`, Präsenz-, Sirenen-, Leck-, Gas- und Rauchmelderwerte.
- Eigene Kacheln übernehmen Grundfarben, Schriftfarben und Schriftgrößen vom aktiven Symcon Tile-Theme, damit Hell- und Dunkelmodus mit den Standardkacheln übereinstimmen.
- Fehlende Properties und Attribute werden während Modulupdates tolerant behandelt, damit Bestandsinstanzen sauber migrieren.

### 23. bis 26. Mai 2026: Komfort, Sicherheit und Binding/Reporting

- Gruppenmitglieder lassen sich über filterbare Gerätelisten und automatisch erkannte Endpoints auswählen. Nicht erreichbare Geräte erzeugen ein verständliches Popup.
- Geräte- und Gruppenoptionen verwenden typisierte Editoren für Boolean-, Enum-, Numeric-, Text-, Array- und Objektwerte. Attributfilter wie `filtered_attributes`, `filtered_cache` oder `debounce_ignore` bieten bekannte Payload-Attribute zur Auswahl an.
- Enum-basierte `state`-Variablen wie Rollladenbefehle senden ihre originalen Zigbee2MQTT-Werte wie `OPEN`, `CLOSE` und `STOP`. Binäre Schalter bleiben bei `ON` und `OFF`.
- Die Bridge kann globale Zigbee2MQTT-Blocklist und -Passlist verwalten. Die Auswahl nutzt bekannte Zigbee2MQTT-Geräte sowie vorhandene Device-Instanzen. Passlist-Änderungen werden wegen ihrer restriktiven Wirkung mit einer Sicherheitsabfrage geschützt.
- Farbtemperatur-Funktionen werden nur angeboten, wenn das Gerät tatsächlich ein passendes Expose liefert.
- Vorhandene Bindings und Reportings werden aus dem Zigbee2MQTT-`bridge/devices`-Cache gelesen und übersichtlich dargestellt. Die Bearbeitung erhielt Zielauswahl, unterstützte Cluster, Attributauswahl und besser dimensionierte Tabellen.
- Über **Endpoint-Daten aktualisieren** können Binding- und Reporting-Daten bewusst neu eingelesen werden.
- Das Öffnen der Geräte-Konfiguration wurde beschleunigt, da Binding-Zielauswahlen nicht mehr bei jedem Formularaufbau live über die Symcon-Extension geladen werden.
- Der Abruf von Geräteinformationen wartet länger auf die Symcon-Extension und zeigt bei Erfolg oder Nichterreichbarkeit verständliche Rückmeldungen im Formular.
- Übersetzungen, PHPDocs, READMEs und Screenshots wurden für die neuen Gerätefunktionen vollständig überarbeitet.
- Command-Payloads werden robuster validiert; nicht mehr verwendeter Tile-Code wurde entfernt.

### 27. bis 31. Mai 2026: Bridge-Wartung, Stabilität und zentrale OTA-Verwaltung

- Die Bridge erhielt eine Variablen-Wartung. Sie trennt verwaiste Variablen in klare Löschkandidaten und Review-Kandidaten und schützt archivierte oder referenzierte Variablen.
- Das zwischenzeitlich vorhandene externe Bereinigungsscript wurde entfernt. Die integrierte Variablen-Wartung ist der unterstützte Weg zum Aufräumen verwaister Variablen.
- Konfigurator und Geräteformulare laden schneller und nutzen bevorzugt den Bridge-Cache. Extension-Antworten ohne Transaction-ID werden bei Bedarf über das Response-Topic zugeordnet.
- Die MQTT-Transaktionsverwaltung wurde deadline-basiert und stabiler aufgebaut. Lange Bridge-Antworten wie Zigbee2MQTT-Backups blockieren nicht mehr durch zu kurze Wartezeiten oder instabile Buffer.
- Zigbee2MQTT-Backups werden wegen der Symcon-Ausgabegrenze chunkweise als ZIP-Datei unter `user/IPSZigbee2MQTT/backups` gespeichert. Eine öffentliche Base64-Rückgabe wird bewusst nicht angeboten.
- Die Bridge erhielt eine zentrale OTA-Verwaltung. OTA-fähige Geräte können geprüft, verfügbare Updates gestartet oder geplant und laufende Updates mit Fortschritt, Restzeit und Ergebnisverlauf verfolgt werden. Zum Schutz des Zigbee-Netzes startet die Oberfläche nur ein aktives Update gleichzeitig.
- OTA-Fortschritte werden automatisch aktualisiert. Die Restzeitvariable `update__remaining` nutzt für den von Zigbee2MQTT gelieferten Sekundenwert die native Symcon-Dauerdarstellung.
- Die Bridge-Wartung erhielt einen optionalen lokalen Install-Code-Katalog. Install-Codes können mit einer Bezeichnung gespeichert, maskiert angezeigt, erneut gesendet, bearbeitet und nach Bestätigung gelöscht werden. Sensible MQTT-Payloads und Antworten erscheinen nicht im Debug-Protokoll.
- Die Bridge-Dokumentation wurde in eigene Funktionsblöcke für Diagnose, Netzwerksicherheit, OTA-Updates, Variablen-Wartung sowie Zigbee2MQTT-Wartung mit Backup, Install-Codes und Touchlink gegliedert.

### 1. bis 7. Juni 2026: Erweiterte Ablaufplan-Aktionen, Datenaktualisierung und Bedienkomfort

- Wiederverwendbare Ablaufplan-Aktionen wurden für Status-Umschaltung, Ein-/Ausschalten mit Übergangszeit, Kelvin-Farbtemperatur mit Übergangszeit und Zigbee-Szenenabruf ergänzt.
- Die Kelvin-Aktion verwendet die pro Geräteinstanz ermittelte beziehungsweise überschriebene Farbtemperaturdarstellung und rechnet den gewählten Kelvin-Wert für Zigbee2MQTT in Mired um.
- Bestehende Übergangsaktionen werden nur noch für Zigbee2MQTT-Geräte und -Gruppen angeboten. Die Aktionen sind in Symcon als zielspezifisch kategorisiert und erhielten ergänzte Beschreibungen sowie Übersetzungen.
- Farbübergänge werden für reine Tunable-White-Leuchtmittel nicht als native RGB-Befehle versendet. Deren abgeleitete Farbvariable bleibt eine reine Visualisierungsdarstellung.
- Übersetzungen verwenden während eines laufenden Modul-Updates einen sicheren Originaltext-Fallback. Kurzzeitig noch nicht verfügbare Sprachdateien oder Instanzschnittstellen unterbrechen dadurch keine OTA-Formularaktualisierung mehr.
- Geräte- und Bridge-Formulare erhielten gezielte Aktualisieren-Schaltflächen für den Variablenkatalog, verfügbare Netzwerksicherheitsgeräte und sämtliche OTA-Tabellen. Der manuelle Variablen-Refresh baut den Device-Katalog neu aus aktuellen Exposes und dem zuletzt empfangenen Geräte-Payload auf. Historische fachfremde Einträge verschwinden aus der Liste, ohne vorhandene Symcon-Variablen zu löschen. Bei laut Zigbee2MQTT OTA-fähigen Geräten bleiben stabile OTA-Metadaten erhalten; temporäre Fortschrittswerte werden nur geführt, solange Zigbee2MQTT sie aktuell liefert. Bestehende Symcon-Variablen bleiben für eine kontrollierte Prüfung über die integrierte Variablen-Wartung erhalten.
- Die Variablenverwaltung in Geräteinstanzen aktualisiert die Liste nach Einzelaktionen gezielt, ohne dass das Formular nach jedem Anlegen oder Deaktivieren an den Anfang der Konfiguration springt.
- Von Zigbee2MQTT berechnete oder nachgelieferte Werte können auch bei unvollständiger Expose-Kennung nachträglich angelegt werden. Numeric-, Binary- und Enum-Variablen ergänzen fehlende `name`- oder `property`-Felder typunabhängig aus der jeweils vorhandenen Kennung.
- Der von Zigbee2MQTT berechnete Taupunkt `dewpoint` wird bei nachträglicher Anlage als übersetzte Float-Variable `Taupunkt` mit dem Symcon-Temperaturprofil `~Temperature` angelegt.
- Geräteinstanzen prüfen eingehende MQTT-Topics zusätzlich zum Symcon-Datenfilter selbst. Fremde Geräte-Payloads können dadurch auch bei unerwarteter Zustellung keine Variablen in der falschen Instanz anlegen.
- Gruppeninstanzen erhielten **Gruppeninformationen aktualisieren**. Damit werden extern in Zigbee2MQTT geänderte Mitglieder, Gruppenoptionen und Szenen erneut eingelesen und direkt in der geöffneten Symcon-Konfiguration angezeigt.
- Die Discovery behält bei internen MQTT-Servern alle gleichzeitig gefundenen Zigbee2MQTT-Basen bei. Veraltete Konstanten, Locale-Einträge und irreführende PHPDocs aus der früheren dateibasierten Expose-Verwaltung wurden bereinigt.
- Die zentrale OTA-Verwaltung bietet nur noch Geräte an, die Zigbee2MQTT ausdrücklich mit `supports_ota` kennzeichnet. Historische `update__*`-Variablen allein führen nicht mehr zu falschen OTA-Angeboten.
- Die OTA-Zentrale kann geplante Updates weiterhin per `unschedule` aus der Planung nehmen und nutzt zusätzlich den neuen Zigbee2MQTT-Abbruch-Endpunkt, um angeforderte oder laufende OTA-Updates abzubrechen.
- Health Check und Coordinator Check zeigen bei nicht erreichbarem Zigbee2MQTT in der Bridge-Konfiguration eine lesbare Diagnosemeldung statt einer technischen Timeout-Notice.
- Weitere Zigbee2MQTT-Gerätelabels und Werte für Kalibrierung, Dimmverhalten, Helligkeitsschwellen, Impulssteuerung und Pulsaktionen wurden ins Deutsche übersetzt. Sprachneutrale numerische Werte wie `1x`, `2x` oder `3x` werden nicht mehr als fehlende Übersetzung gemeldet.
- Die Konfigurationsformulare wurden nach Nutzungshäufigkeit gegliedert: Geräte bündeln Optionen, Variablen sowie Binding und Reporting unter erweiterten Einstellungen; die Bridge zeigt zentrale Statusschaltflächen direkt und trennt Diagnose sowie OTA von administrativen und Expertenwerkzeugen; Gruppenoptionen liegen unter erweiterten Gruppeneinstellungen. Der Konfigurator zeigt Gruppen standardmäßig eingeklappt und blendet selten benötigte Gerätespalten aus.
- Die direkt sichtbaren Bridge-Statusschaltflächen übernehmen beim Öffnen zuverlässig den zuletzt ermittelten Zustand der Symcon-Erweiterung, der `last_seen`-Einstellung und der `permit_join`-Konfiguration.
- Fehlende deutsche Übersetzungen für Ventiladaption, Display-Einschaltdauer, Fehlerzustand sowie Temperatur- und Displayauswahlwerte von Thermostaten wurden ergänzt.
- Geräte- und Gruppeninformationen lassen sich über einheitlich benannte, direkt sichtbare Aktualisierungsaktionen neu aus Zigbee2MQTT einlesen; die doppelte Gruppenaktion unter den Expertenwerkzeugen wurde entfernt.
- Gruppenoptionen werden ohne unnötige Zwischenebene direkt neben Gruppenmitgliedern und Szenen angezeigt.
- Geräteinstanzen erhielten eine bestätigungspflichtige Gerätewartung. Ein erneutes Interview liest Endpoints, Cluster und Basisattribute neu ein; eine erneute Konfiguration stößt die gerätespezifische Zigbee2MQTT-Konfiguration für Bindings und Reporting erneut an. Lange Requests sowie nicht erreichbare oder nicht konfigurierbare Geräte werden mit verständlichen Ergebnisdialogen behandelt.
- Die Bridge erhielt eine erweiterte Pairing-Steuerung. Der Netzwerkbeitritt kann für eine wählbare Dauer über das gesamte Netzwerk, den Coordinator oder einen bestimmten Router geöffnet werden; Ziel, Endzeit und verbleibende Zeit werden in Statusvariablen und der Bridge-Konfiguration angezeigt.
- Geräteinstanzen bieten unter den Expertenwerkzeugen eine bestätigungspflichtige erweiterte Geräteentfernung. Geräte können regulär entfernt, nur aus der Zigbee2MQTT-Datenbank zwangsweise entfernt oder nach dem Entfernen zusätzlich blockiert werden; Symcon-Instanz und Variablen bleiben dabei erhalten.
- Das neue eigenständige Modul **Zigbee2MQTT Netzwerkkarte** analysiert die Netzwerktopologie asynchron als RAW-Daten, zeigt Geräte, gerichtete LQI-Verbindungen, Routen und Scanfehler an und bietet eine interaktive HTML-SDK-Kachel. RAW-, Graphviz- und PlantUML-Exporte werden lokal aus der letzten Analyse erzeugt und lösen keinen weiteren Zigbee-Netzwerkscan aus.
- Die Netzwerkkarten-Kachel reserviert den Symcon-Titelbereich, richtet Status, Zusammenfassung und Filter responsiv aus und blendet vor der ersten Analyse noch nicht nutzbare Bedienelemente aus.
- Live-Aktualisierungen der Netzwerkkarten-Kachel werden als JSON-Zeichenkette an das HTML-SDK übertragen und dort sicher normalisiert, sodass Scanstart, Fortschritt und Ergebnisse ohne Typkonvertierungswarnung dargestellt werden.
- Die grafische Netzwerkkarte filtert Verbindungen zu unbekannten oder nicht im Scan enthaltenen Knoten, lädt Cytoscape unabhängig von der HTML-SDK-Umgebung und verwendet für große Netzwerke ein schnelles konzentrisches Layout. Darstellungsfehler werden sichtbar in der Kachel ausgegeben.
- Die HTML-SDK-Netzwerkkarte reagiert auf Größen- und Sichtbarkeitsänderungen, verwendet die verfügbare Viewport-Höhe und fordert beim Öffnen den aktuell gespeicherten Zustand an. Das initiale HTML-Grundgerüst bleibt unabhängig von der Netzwerkgröße klein; die Topologie wird erst nach dem Laden sicher übertragen. Weil das Symcon-PHP-SDK keine eigene HTML-Darstellung für die maximierte Instanzansicht anbietet, stellt die Karte für die große grafische Ansicht einen eigenen Vollbildmodus bereit.
- Die Netzwerkkarten-Kachel bietet mehrere Layouts, eine Gerätesuche, fokussierte Umfeldansichten und optional ausblendbare Beschriftungen. Die allgemeine Cytoscape-Bedienlogik ist dabei von der Zigbee-spezifischen Darstellung getrennt und kann nach Stabilisierung der Schnittstelle als wiederverwendbarer Helfer ausgegliedert werden.
- Standardlayout und anfängliche Sichtbarkeit der Beschriftungen können im Abschnitt **Ansicht** der Netzwerkkarten-Instanz dauerhaft konfiguriert werden.
- Der eigene Vollbildmodus der Netzwerkkarte leitet aus der aktuellen Symcon-Schriftfarbe automatisch eine kontrastierende Vollbildfläche ab, sodass Beschriftungen sowohl im hellen als auch im dunklen Profil lesbar bleiben.

### 8. bis 12. Juni 2026: Dokumentation und Schnittstellenbereinigung

- Die READMEs aller enthaltenen Module wurden mit den tatsächlich verfügbaren Formularbereichen, Aktionen und öffentlichen Funktionen abgeglichen.
- Aktuelle und einheitlich nummerierte Screenshots erläutern die Konfiguration von Bridge, Konfigurator, Geräten, Gruppen und Netzwerkkarte.
- Die Bridge-Dokumentation beschreibt Netzwerksicherheit, Diagnose, Anlernen, OTA-Verwaltung und Variablen-Wartung anhand der aktuellen Bedienoberfläche.
- Veraltete Sprungmarken der Hauptdokumentation wurden korrigiert und sämtliche lokalen README-Verweise sowie Bilddateien geprüft.
- Die interne Cache-Funktion `Z2M_GetCachedNetworkDevices` wurde in der Bridge-Funktionsreferenz ergänzt.
- Ausschließlich intern genutzte Hilfsmethoden des Konfigurators sind nicht länger als öffentliche Modul-Funktionen sichtbar.
- Die in `library.json` hinterlegte Mindestversion wurde an die dokumentierte und erforderliche Mindestversion IP-Symcon 9.0 angeglichen.
- Der Konfigurator zeigt und verwaltet ausschließlich Instanzen mit demselben MQTT-Splitter und MQTT-Basistopic. Instanzen anderer Splitter bleiben vollständig unberücksichtigt; passende Geräte und Gruppen des aktuellen Zigbee2MQTT-Netzes werden regulär zur Erstellung angeboten.
- Gruppeninstanzen begrenzen auch ihre lokale Geräteauswahl auf denselben MQTT-Splitter und dasselbe MQTT-Basistopic. Mehrere Zigbee2MQTT-Systeme können dadurch keine Geräte des jeweils anderen Netzes als Gruppenmitglied anbieten.
- Eine beim Abruf von Geräteinformationen erkannte IEEE-Adresse wird in bestehenden Instanzen ausschließlich als noch nicht gespeicherter Formularwert eingetragen. Erst das reguläre **Übernehmen** der Instanzkonfiguration speichert die Adresse; das Modul ändert oder übernimmt die Eigenschaft nicht selbstständig.
- Fehlende Bridge-Instanzen werden ausschließlich über den regulären Symcon-Konfigurator erstellt. Formularskripte erzeugen oder konfigurieren keine Instanzen mehr direkt.
- MQTT-Befehle brechen während des kurzen Instanzschnittstellen-Wechsels eines Modul-Updates kontrolliert ab. Laufende Ereignisse erzeugen dadurch keine `InstanceInterface is not available`-Warnungen und senden keine unvollständigen MQTT-Topics.
- Gerätebilder werden modellbezogen unter `user/IPSZigbee2MQTT/icons` zwischengespeichert und nur beim Öffnen der Geräte-Konfiguration geladen. Bestehende Base64-Bildattribute werden automatisch migriert, wodurch `IPS_GetSnapshot()` und darauf aufbauende Visualisierungen deutlich weniger Arbeitsspeicher benötigen.

### 13. bis 15. Juni 2026: Instanzbezogene Variablen-Wartung

- Ausgehende MQTT-Befehle verwenden wieder den von Zigbee2MQTT abonnierten Topic-Baum ohne führenden Slash. Geräte- und Gruppenaktionen erreichen dadurch den konfigurierten Zigbee2MQTT-Basistopic wieder korrekt.
- Binäre Statusaktionen schreiben Zigbee2MQTT-Werte wie `ON` und `OFF` nach dem Senden wieder typgerecht als Boolean in die Symcon-Variable. Dadurch springt ein ausgeschalteter Status nicht mehr durch PHPs String-Konvertierung unmittelbar auf `Ein` zurück.
- Die Variablen-Wartung folgt den Instanz- und Systemgrenzen: Die Bridge zeigt nur noch eine kompakte, nach Geräten und Gruppen desselben MQTT-Splitters und MQTT-Basistopics zusammengefasste Übersicht. Prüfung und bestätigtes Löschen erfolgen direkt in der zuständigen Instanz, die ausschließlich ihre eigenen direkten Variablen verwalten darf.
- Expertenwerkzeuge in Geräte- und Gruppeninstanzen nutzen die verfügbare Formularbreite. Die instanzbezogene Variablen-Wartung erscheint bei Geräten direkt unterhalb der erweiterten Geräteentfernung.
- Dynamisch erzeugte Texte der instanzbezogenen Variablen-Wartung werden vollständig übersetzt. Die Dokumentation erläutert Suchlauf-Hinweise als diagnostische Meldungen für übersprungene oder unvollständig prüfbare Instanzen.
- Erkannte IEEE-Adressen werden review-konform nur noch in das Konfigurationsformular eingetragen. Sie werden ausschließlich durch das reguläre **Übernehmen** der Instanzkonfiguration gespeichert.
- Bridge-Suche, Binding-Ziele, OTA-Verwaltung und Netzwerksicherheitslisten berücksichtigen neben dem MQTT-Basistopic immer auch den tatsächlich verbundenen MQTT-Splitter. Mehrere Zigbee2MQTT-Systeme bleiben dadurch selbst bei identischem Basistopic vollständig voneinander getrennt.
- Der Konfigurator listet und verändert keine Zigbee2MQTT-Instanzen fremder MQTT-Splitter mehr. Der bisherige Reparaturdialog wurde entfernt; Geräte und Gruppen des aktuellen Netzes werden stattdessen ausschließlich über den regulären Symcon-Konfigurator zur Erstellung angeboten.
- Der Erreichbarkeitstest von Bridge und Konfigurator wartet bei ausgelasteten Zigbee2MQTT-Systemen bis zu 20 Sekunden auf den Options-Request und unterdrückt technische Zwischen-Notices. Dadurch wird Zigbee2MQTT nicht mehr bereits nach dem bisherigen Fünf-Sekunden-Limit fälschlich als nicht erreichbar gemeldet.
- Die Discovery lässt den Anwender über `mqtt://` oder `mqtts://` ausdrücklich zwischen unverschlüsseltem MQTT und TLS wählen. Bei TLS werden Zertifikat und Hostname standardmäßig geprüft; für lokale Broker mit selbstsignierten Zertifikaten können beide Prüfungen bewusst deaktiviert werden. Ein automatischer Rückfall auf eine unverschlüsselte Verbindung findet nicht statt.
- Bereits vorhandene Geräte- und Gruppeninstanzen werden im Konfigurator wieder korrekt als weiterhin von Zigbee2MQTT erkannte Einträge dargestellt. Die rote Symcon-Markierung bleibt damit ausschließlich tatsächlich nicht mehr gefundenen Instanzen vorbehalten.
- Die Bridge erhielt eine rein lesende Variablenprofil-Diagnose. Sie listet konfliktbedingt erzeugte kompatible Profile mit ihren aktuellen Abweichungen, ihrer Verwendung und identischen Dubletten auf. Neue Profilkonflikte protokollieren zusätzlich die konkreten Unterschiede zur vorhandenen Definition.
- Der Profilvergleich berücksichtigt nun, dass Symcon bei mehrfach definierten Assoziationen mit demselben Wert nur den zuletzt gesetzten Eintrag speichert. Dadurch werden beispielsweise bei Farbtemperatur-Presets keine fortlaufenden kompatiblen Ersatzprofile mehr erzeugt.
- Die Testcenter von Bridge, Geräte- und Gruppeninstanzen befinden sich als eigenständige Bereiche auf der obersten Formularebene und sind nicht mehr in Erweiterungs- oder Expertenmenüs verschachtelt.
- Das dadurch leere Bridge-Untermenü **Expertenwerkzeuge** wurde entfernt; Dokumentation und Regressionstests wurden an die einheitliche Formularstruktur angepasst.
- Bestehende Variablenprofile werden bei abweichendem Typ, Wertebereich oder abweichenden Assoziationen weder verändert noch gelöscht. Vollständig passende Profile werden wiederverwendet; bei Namenskonflikten erstellt das Modul ein eindeutig benanntes kompatibles Profil und weist dieses der betreffenden Variable zu.
- Die öffentlichen Funktionsreferenzen für Geräte und Gruppen dokumentieren `Z2M_CommandExt()` und den Rückgabewert von `Z2M_ReadValue()` jetzt entsprechend den tatsächlich bereitgestellten Schnittstellen.
- Gerätebilder werden mit geprüftem TLS, einem Timeout von fünf Sekunden und einer Größenbegrenzung von zwei MiB geladen. Nur technisch lesbare PNG-Dateien mit begrenzten Bildabmessungen werden gespeichert oder aus einem bestehenden Cache übernommen.

### 20. Juni 2026: Review-konforme Variablendarstellungen

- Empfohlene moderne Variablendarstellungen werden ausschließlich beim erstmaligen Anlegen einer Variable als initiale `RegisterVariable*`-Darstellung übergeben. Bestehende benutzerdefinierte Darstellungen bleiben bei `ApplyChanges`, Payloads und Expose-Aktualisierungen unverändert.
- Das Modul ruft produktiv kein `IPS_SetVariableCustomPresentation()` mehr auf und bietet keine Aktion mehr an, um vorhandene Custom-Presentations nachträglich zu überschreiben oder zu entfernen. Damit bleibt die Darstellungs-Hoheit nach der Erstellung vollständig beim Anwender.
- Neue Variablen verwenden bevorzugt moderne RegisterVariable-Darstellungen. Dynamische Z2M-Profile werden nur noch erstellt, wenn keine passende Symcon-Standarddarstellung und kein Standardprofil verfuegbar ist.
- Bestehende Variablen behalten ihr Modulprofil, wenn kein festes Standardprofil fachlich vorgegeben ist. `ApplyChanges` erzeugt dadurch keine neuen kompatiblen Profile nur deshalb, weil inzwischen eine passende moderne Darstellung verfuegbar waere.
- Die Geräte-Konfiguration zeigt keine eigene Aktion zum erneuten Anwenden empfohlener Darstellungen mehr. Die Dokumentation beschreibt stattdessen die Trennung zwischen initialer Modul-Empfehlung und späterer Benutzeranpassung.
- Die Hauptdokumentation trennt die Begriffe Spezialkachel/Visualisierung, Variablendarstellung und Variablenprofil ausdrücklich, damit eigene HTML-SDK-Kacheln nicht mit Symcon-Variablendarstellungen oder Profilen vermischt werden.
- Die Discovery kann `mqtts://`-Broker mit lokalen selbstsignierten Zertifikaten erreichen, wenn der Anwender die TLS-Zertifikats- oder Hostnamenprüfung bewusst deaktiviert. Sichere TLS-Prüfung bleibt der Standard und es gibt keinen automatischen Fallback auf `mqtt://`.

### 21. Juni 2026: Stabilisierung frischer Installationen

- Extension-Listenabfragen für Geräte und Gruppen warten länger auf Zigbee2MQTT. Frische Installationen, große Netze oder gerade gestartete Symcon-Extensions werden dadurch nicht mehr fälschlich als fehlende oder veraltete Extension gemeldet, wenn die Geräteliste erst nach mehreren Sekunden geliefert wird.

**Version 5.42:**  

- Bridge Instanz konnte den Namen der bereits installierten Erweiterung nicht korrekt erkennen und übernehmen.  
- Diverse Übersetzungen ergänzt.  
- Neue Funktion Z2M_ReadValue und Z2M_SendGetCommand.  
- Diverse spezielle Funktionen im globalen Namensraum aufrufen, um Compiler-Optimierung zu ermöglichen.  

**Version 5.40:**  

- Einheiten in Profilen wurden teilweise nicht als UTF8 String an Symcon übergeben.  
- Explizites Token-Mapping für häufige Zeichenketten bei booleschen Werten. Verhindert false positives bei Erkennung von Strings, wie z.B. `OFF` welches zu true umgewandelt wurde.  
- Fehlerhafte Typisierung bei mehrdeutigen Features wie der Position (z.B. `position` numerischer vs. Enum-/String-Geräte) wird verhindert.  
- Gefilterte Attribute aus Z2M werden in Symcon nicht mehr als Variablen angelegt. (Danke an JosVanHaag für den PR)  
- Fehlende Übersetzungen vom Gerät S8 ergänzt.  

**Version 5.39:**  

- Fehlende Übersetzungen vom Gerät Senoro.Win v2 ergänzt.  
  
**Version 5.38:**  

- Fehlende Übersetzungen von den Geräten 501.40, BMCT-SLZ, S4SW-001P8EU und WT-A03E ergänzt  
- Discovery Instanz liefert die ganze Kette für Symcon 9.x  
  
**Version 5.37:**  

- Bridge Instanz erkannte aktuelle Z2M Versionen falsch.  
  
**Version 5.36:**  

- phpMQTT Bibliothek aktualisiert um Verbindungsprobleme der Discovery-Instanz zu beheben.  
  
**Version 5.35:**  

- Fehlende Übersetzungen von den Geräten PS-S04D und MTD285-ZB ergänzt.  
- interne Modul Tests erweitert um fehlende Übersetzungen zu erkennen.  
  
**Version 5.34:**  

- Das `&` Zeichen wird bei feature / Property zu `_and_` ersetzt.

**Version 5.33:**  

- Bei composite wurde versucht für eine nicht vorhandene Hauptvariable eine Aktion zu setzen.  
- Das `&` Zeichen wird bei Profilen gefiltert.
- Readme aktualisiert.  
  
**Version 5.31:**  

- Fehlermeldung Profil Z2M.AutoLock existiert nicht behoben  
- Bridge Instanz erkennt ZH Version 6.X  
- Alle Instanzen mit einer "Occupancy"/"Bewegung" Variable unterstützen, sofern in Z2M eingerichtet, auch die "No Occupancy Since"/"Keine Bewegung seit" Variable  
- interne Modul Tests erweitert  
  
**Version 5.26:**  

- Diverse Fixes betreffend der Fehlermeldungen Undefined array key  
- Die Aktion "Helligkeit mit Übergang" war defekt  
- Geändertes Verhalten beim schalten der Farbe, basierend auf dem aktiven Farbmodus  
- Color Datenempfang um Hue / Saturation ergänzt  
- Bridge Instanz erkennt ZH Version 5.X  

**Version 5.25:**

- Erste Version als stable im Store erhältlich  
- Letzte Änderung war nun das Entfernen von Debug Meldungen aus dem Logfile  

**Version 5.22:**  

- Durch das aktiveren von Include device information in Z2M werden keine Variablen mehr in Symcon angelegt  

**Version 5.20:**  

- Diverse Übersetzungen ergänzt (Nachträglich werden diese bei Variablen nicht angepasst!)  
- Fix für Smoke Profile (~Alert)  
- Fix für Boolean Profile, wo Variablen als Boolean und Profile als String angelegt wurden  
- Dateiname des Debug Download enthält den Modelnamen  

**Version 5.19:**

- Diverse Übersetzungen ergänzt (Nachträglich werden diese bei Variablen nicht angepasst!)  
- contact, tamper Variablen erhalten korrekte Standard-Profile (~Window.Reserved bzw ~Alert)  
- Fix für color_temp_kelvin Variable  

**Version 5.18:**  

- Preset Variablen (Voreinstellungen) zeigen den zuletzt empfangenen / gesendeten Wert an  
- Übersetzungen von Profil zu Voreinstellungen geändert. (Hat keinen Einfluss auf vorhandenen Variablen)  

**Version 5.17:**  

- Das Debug Download war teilweise defekt  
  
**Version 5.16:**  

- Instanzen welche als Topic einen Anfang von anderen Topics enthielten, haben falsche Daten empfangen und verarbeitet (z.B. Topic "Flur" hat auch Daten von Topics "Flur 01", "Flur 02", "Flur hinten" verarbeitet)  
  
**Version 5.15:**  

- Erweiterung bei Update Variablen  
- Einführung der Instanz-Funktionen Z2M_WriteValueBoolean, Z2M_WriteValueInteger, Z2M_WriteValueFloat und Z2M_WriteValueString für PHP-Skripte  

**Version 5.13:**  

- Erweiterung der Variablen-Erstellung auf die ‚list‘-Exposes, welche vorher nicht beachtet wurden  
- fehlende Übersetzungen ergänzt  
- Fehler bei Discovery Instanz sollte behoben sein  

**Version 5.12:**  

- Array und Composite Variablen (z.b. Update, Level-Config usw.) sind Variablen verfügbar  

**Version 5.11:**  

- Child Lock konnte nicht geschaltet werden  
- einige Text Variablen wurden nicht angelegt (z.B. die Schedule Variablen)  
- Fehlende Übersetzungen ergänzt (werden nur beim neu Anlegen von Variablen/Profilen berücksichtigt)  
- Debug Download bei Gruppen war defekt  
- JSON Datei für fehlende Übersetzungen konnte kaputt gehen  
- Fehlende Übersetzungen werden im Debug Download einbezogen  
- Fehlende Übersetzungen können in der Instanz-Konfig angezeigt werden (nur wenn es welche gibt)  

**Version 5.10:**  

- Fix für nicht vorhandene Profile bei Text Datentypen  

**Version 5.09:**  

- Fix für 32-Bit Int zu Float Überlauf bei last_seen behoben  

**Version 5.08:**  

- diverse fixes für die Migration → einige Idents konnten nicht übertragen werden (z.B. Z2M_SmokeDensityDBM, Z2M_Window_OpenFeature, Z2M_PiHeatingDemand etc)  
- Variablen welche aufgrund eines (früher) falschen Variablentyps nicht migriert werden können, werden übersprungen  
- last_seen wird immer als integer behandelt.
- calibration_time wird immer auf float und countdown* immer auf int gemappt  
- Debug JSON um unnötige Verschachtlungen reduziert  
  
**Version 5.05:**  

- Debug Download eingeführt  
- Discovery Instanz verfügbar
- Konfigurator erkennt falsch zugeordnete MQTT-Server/Clients  

**Version 5.01:**  

- diverse Profile von float zu int umgestellt  
- Extension filtert Gruppen ohne Namen aus (vermutlich Reste aus alten Z2M Versionen)  
- Migrate hat State Variablen nicht korrekt verarbeitet  

**Version 5.00:**  

- Kompatibilität mit Zigbee2MQTT Version 2.0 hergestellt  
- Geräte erkennen automatisch die Features und Exposes und erstellen die benötigten Variablen mit den entsprechenden Profilen eigenständig  
  - Somit keine missing exposes Debugs mehr nötig!  
- Nutzung von Standard-Symcon Profilen, soweit möglich  
- Presets und Effekte als Variablen verfügbar  
- Array und Composite Variablen (z.b. Update, Level-Config usw.) sind Variablen verfügbar.
- Geräte speichern die IEEE um umbenannte Geräte (= geändertes Topic) zu erkennen
- Z2M Prefix bei VariablenIdents entfernt  
- Konfigurator übernimmt die MQTT Topic-Struktur beim Anlegen von Geräten als Kategorien  
- Konfigurator erkennt fehlende Bridge-Instanz  
- Konfigurator erkennt falsche Topics (anhand der IEEE Adresse der Geräte)  
- Bridge installiert die Extension nicht mehrfach  
- Bridge installiert automatisch die benötigte Extension  
- Komplettes Code-Rework für Geräte und Gruppen von Bruki24
- Diverse Aktionen für die Instanzen der Geräte und Gruppen:  
  - Relatives Dimmen der Helligkeit  
  - Schrittweises Dimmen der Helligkeit  
  - Relatives Dimmen der Farbtemperatur  
  - Schrittweises Dimmen der Farbtemperatur  
  - Ein-/Ausschaltverzögerung  
  
## 6. Spenden

Dieses Modul ist für die nicht kommerzielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:

<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=EK4JRP87XLSHW" target="_blank"><img src="https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donate_LG.gif" border="0" /></a> <a href="https://www.amazon.de/hz/wishlist/ls/3JVWED9SZMDPK?ref_=wl_share" target="_blank">Amazon Wunschzettel</a>

## 7. Lizenz

[CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)
