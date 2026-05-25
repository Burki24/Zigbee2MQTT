[![SDK](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Module Version](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2FNall-chan%2FZigbee2MQTT%2Frefs%2Fheads%2Fmain%2Flibrary.json&query=%24.version&label=Modul%20Version&color=blue)](https://community.symcon.de/t/modul-zigbee2mqtt-version-5-x/139819)
[![Symcon Version](https://img.shields.io/badge/Symcon%20Version-9.0%3E-green)](https://www.symcon.de/de/service/dokumentation/einfuehrung/systemvoraussetzungen/versionenuebersicht/#version-90)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![Check Style](https://github.com/Nall-chan/Zigbee2MQTT/workflows/Check%20Style/badge.svg)](https://github.com/Nall-chan/Zigbee2MQTT/actions)
[![Run Tests](https://github.com/Nall-chan/Zigbee2MQTT/workflows/Run%20Tests/badge.svg)](https://github.com/Nall-chan/Zigbee2MQTT/actions)  

# Zigbee2MQTT-Gerät  <!-- omit in toc -->  

   Mit diesem Module werden die Geräte von Zigbee2MQTT in IP-Symcon als Instanz abgebildet

## Inhaltsverzeichnis <!-- omit in toc -->  

- [1. Funktionsumfang](#1-funktionsumfang)
- [2. Voraussetzungen](#2-voraussetzungen)
- [3. Software-Installation](#3-software-installation)
- [4. Konfiguration](#4-konfiguration)
  - [4.1 Visualisierung und Kacheln](#41-visualisierung-und-kacheln)
  - [4.2 Temperatur-Visualisierung](#42-temperatur-visualisierung)
  - [4.3 Farbtemperatur in der Beleuchtungs-Kachel](#43-farbtemperatur-in-der-beleuchtungs-kachel)
  - [4.4 Geräteoptionen](#44-geräteoptionen)
  - [4.5 Binding und Reporting](#45-binding-und-reporting)
  - [4.6 Variablenverwaltung](#46-variablenverwaltung)
- [5. Statusvariablen](#5-statusvariablen)
- [6. PHP-Funktionsreferenz](#6-php-funktionsreferenz)
- [7. Aktionen](#7-aktionen)
- [8. Anhang](#8-anhang)
  - [1. Changelog](#1-changelog)
  - [2. Spenden](#2-spenden)
  - [3. Lizenz](#3-lizenz)

## 1. Funktionsumfang

- Darstellung aller von Z2M gelieferten Werte in Symcon
- Inklusive der Verfügbarkeit des Gerätes als Variable (Online-Variable), wenn dies in Z2M aktiviert ist: [availability](https://www.zigbee2mqtt.io/guide/configuration/device-availability.html).
- Automatisches Erstellern der für die Variablen benötigten Variablenprofile gemäß den Daten aus Z2M
- Automatische Zuordnung moderner Tile-Darstellungen und passender Standardprofile, soweit die Exposes dies zulassen
- Eigene HTML-SDK-Kacheln für häufige Gerätetypen wie Schaltaktoren mit Messwerten, Heizungen, Sensoren, Sicherheitskontakte, Fenstergriffe und Aktionsgeräte
- Komfortable Pflege von Zigbee2MQTT-Geräteoptionen inklusive typisierter Editoren und Attributauswahl
- Binding- und Reporting-Verwaltung anhand der von Zigbee2MQTT gelieferten Endpoint- und Cluster-Daten
- Variablenverwaltung für automatisch erkannte, nachgelieferte, deaktivierte oder vom Anwender gelöschte Variablen
- Erstellen von Variablen für reine Aktionen wie Voreinstellungen wählen, Effekte aufrufen oder Identifizieren starten
  
## 2. Voraussetzungen

- mindestens IP-Symcon Version 9.0
- MQTT-Broker (interner MQTT-Server von Symcon oder externer z.B. Mosquitto)
- installiertes und lauffähiges [zigbee2mqtt](https://www.zigbee2mqtt.io)  
  
## 3. Software-Installation

- Dieses Modul ist Bestandteil der [Zigbee2MQTT-Library](../README.md#3-installation).  

## 4. Konfiguration

![Konfiguration Device](imgs/config.png)  

| **Nummer** | **Feld**                        | **Beschreibung**                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      |
| ---------- | ------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **1**      | **MQTT Base Topic**             | Dieses wird vom [Konfigurator](../Configurator/README.md) bei Anlage der Instanz automatisch auf den korrekten Wert gesetzt und sollte auch so belassen werden.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       |
| **2**      | **MQTT Topic**                  | Das Topic, welches die Instanz in Z2M nutzt. Beim Anlernen von Geräten an Z2m erhält jedes Gerät einen Namen (`friendly_name`). Standard ist hier die IEEE-Adresse. Dies kann im Nachgang aber geändert werden.<br>**Bei jeder Änderung des Namen ändert sich auch das Topic in MQTT.**<br>Entsprechend muss das neue Topic in Symcon übernommen werden. Dies kann per Hand, oder über den [Konfigurator](../Configurator/README.md) erfolgen (Prüfen Button), welcher geänderte Topics anhand der Geräte IEEE Adresse erkennt.                                                                                                                       |
| **3**      | **IEEE Adresse**                | Anhand dieser Adresse ist, unabhängig vom Topic, eine eindeutige Identifikation von Geräten in Z2M möglich. **Die IEEE Adresse sollte nicht geändert werden!** Ausnahme wäre der 1:1 Austausch von einem baugleichen Gerät, so muss die Instanz in Symcon nicht gelöscht und neu angelegt werden.                                                                                                                                                                                                                                                                                                                                                     |
| **4**      | **Geräteinformationen**         | Hier wird der Link zum Gerät in der Z2M Doku angezeigt und das entsprechende Bild von dem Gerät. Die Bilder werden von Z2M bereit gestellt und können teilweise abweichen.                                                                                                                                                                                                                                                                                                                                                                                                                                                                            |
| **5**      | **Geräteinformationen abrufen** | Über diesen Button können alle Informationen zu einem Gerät aus Z2M erneut abgerufen werden. Dies ist manchmal notwendig, wenn das Gerät bezüglich der betreffenden Daten (exposes) aus Z2M ein Update erhalten hat (z.B. neue Effekte oder zusätzliche Datenpunkte). Beim Anlegen der Instanz wird dies automatisch durchgeführt.                                                                                                                                                                                                                                                                                                                    |
| **6**      | **Testcenter**                  | Hier werden alle Statusvariablen der Instanz welche bedienbar (steuerbar) sind von der Konsole dargestellt. Somit ist ein Funktionstest schnell möglich.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              |
| **7**      | **Dokumentation**               | Direkter Zugriff auf die Dokumentation der Instanz.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   |
| **8**      | **Gateway konfigurieren**       | Unter diesem Punkt kann der verbundene MQTT-Splitter (Client oder Server) aufgerufen werden.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          |
| **9**      | **Gateway ändern**              | Dient zur Auswahl des von der Instanz genutzten MQTT-Splitters (Client oder Server).  Wird beim anlegen von Geräten über den [Konfigurator](../Configurator/README.md) automatisch gesetzt und kann auch über diese Korrigert werden.                                                                                                                                                                                                                                                                                                                                                                                                                 |
| **10**     | **InstanzID kopieren**          | Kopiert die Instanz ID in die Zwischenablage.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         |
| **11**     | **Instanzobjekt bearbeiten**    | Öffnet den gleichen Dialog wie im Objektbaum unter `Instanz bearbeiten`.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              |
| **12**     | **Ereignisse**                  | Zeigt eine Übersicht, welche Ereignisse mit der Instanz verbunden sind. Über den Button Neu lassen sich neue Ereignisse zu der Instanz einrichten (Ausgelöst, zyklisch oder per Wochenplan). Die zugehörigen Ereignisse können direkt bearbeitet werden. ![Ereignisse](imgs/events.jpg)                                                                                                                                                                                                                                                                                                                                                                |
| **13**     | **Statusvariablen**             | Hier lassen sich alle der Instanz zugehörigen Variablen bearbeiten ![Variablen](imgs/variablen.png)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   |
| **14**     | **Debug**                       | Öffnet eine Debug-Ausgabe dieser Instanz. Protokolle der Debug-Ausgabe werden im Fehlerfall von den Entwicklern abgefragt. Da hier u.a. auch zu sehen ist, ob Werte des MQTT-Expose oder Payload nicht zugeordnet werden können, Profile fehlen, Schaltaktionen nicht ausgeführt werden können usw...<br>Sollte es Probleme mit einer Instanz geben, können diese nur adäquat bearbeitet werden, wenn der Meldung (unter Issues oder im Forum) ein Debug beigelegt wird. Dazu bitte im Debug-Fenster zuerst das Limit ausschalten und später über ![Download](imgs/download-debug.png) die heruntergeladene Debug-Datei der Meldung im Forum beifügen. |

### 4.1 Visualisierung und Kacheln

Das Modul prüft anhand der Zigbee2MQTT-Exposes automatisch, ob eine eigene HTML-SDK-Kachel sinnvoll ist. Wenn eine passende Kachel verfügbar ist, wird diese automatisch als Visualisierung der Instanz verwendet. In der Konfiguration erscheint dann der Bereich **Visualisierung** mit der aktuell aktiven Kachel und den passenden Abschaltoptionen.

Es werden nur Optionen angezeigt, die für das jeweilige Gerät fachlich passen. Ein einfacher Temperatursensor zeigt also keine Schalter-Kachel-Option, ein Schaltaktor ohne Messwerte keine Messwert-Kachel-Option.

| Kachel | Typische Exposes | Darstellung |
| ------ | ---------------- | ----------- |
| Heizungs-Kachel | `occupied_heating_setpoint`, `local_temperature`, optional Ventil- und Betriebswerte | Ist- und Solltemperatur als Hauptansicht mit Plus-/Minus-Tasten und Presets, Detailseiten für weitere Heizungswerte und Einstellungen |
| Schalter-/Leistungsmessungs-Kachel | `state`, optional `state_1` bis `state_4`, `power`, `energy`, `voltage`, `current`, `ac_frequency`, `power_factor`, `power_apparent`, `power_reactive`, `produced_energy`, `consumption` | Schalten auf der Hauptseite, mehrere Schaltausgänge in einer Kachel, Messwertseite mit optionalem Archiv-Graphen bei archivierten Variablen |
| Fenstergriff-Kachel | `position`, `alarm`, optional `action`, `action_left`, `action_right`, `button_left`, `button_right` | Griffzustand Geschlossen/Offen/Gekippt, Alarmstatus und Tasten |
| Sicherheits-Kachel | z.B. `contact`, `window_open`, `opening_state`, `alarm_state`, `tamper`, `smoke`, `gas`, `water_leak`, `battery_low` | Status-/Alarmdarstellung mit Priorität auf Kontakt- bzw. Öffnungszustand, Detailseite für Alarm-, Batterie- und Sirenenwerte |
| Aktions-Kachel | Taster-, Fernbedienungs-, Button- oder Szenen-Exposes | Letzte Aktion und verfügbare Aktionswerte |
| Sensor-Kachel | z.B. `temperature`, `humidity`, `soil_moisture`, `illuminance`, `occupancy`, `motion`, `presence`, `target_distance` | Messwertdarstellung für reine Sensoren und Radar-/Präsenzmelder, inklusive Detail-/Einstellseite wenn passende Einstellwerte vorhanden sind |

#### Beispiel Schaltaktor mit Leistungmessung
![Schaltaktor mit Leistungsmessung](imgs/Schaltaktoren-Kachel.png)

Bei kombinierten Aktor-/Sensorgeräten bleibt die automatische Auswahl zunächst bei der Aktor- bzw. Standarddarstellung. Sobald passende Sensorwerte vorhanden sind, kann in der Instanzkonfiguration bewusst **Sensor-Kachel verwenden** aktiviert werden.

Die drei Solltemperatur-Presets der Heizungs-Kachel sind pro Instanz im Bereich **Visualisierung** konfigurierbar. Standardwerte sind `18,0 °C`, `20,0 °C` und `22,0 °C`.
![Heizungs-Kachel mit Einstellung](imgs/Heizungs-Kachel.png)

Die eigenen Kacheln geben keine festen Schriftarten oder Grundfarben vor. Dadurch übernehmen sie Hell-/Dunkelmodus, Schrift und Basisfarben der Symcon Tile-Visualisierung. Eigene Farben werden nur für fachliche Zustände verwendet, z. B. Alarm, OK, aktiv, inaktiv oder den Farbverlauf eines Messwerts.

Wenn mehrere Kacheln fachlich passen, gilt folgende Priorität:

1. Heizungs-Kachel
2. Schalter-/Leistungsmessungs-Kachel
3. Fenstergriff-Kachel
4. Sicherheits-Kachel
5. Aktions-Kachel
6. Sensor-Kachel
7. Standard-Visualisierung von Symcon

Die höher priorisierte Kachel kann in der Instanz-Konfiguration deaktiviert werden, wenn stattdessen die nächste passende Kachel oder die Standard-Visualisierung verwendet werden soll.

![Kachel-Auswahl](imgs/Instanz_Visualisierung.png)

Für Gerätetypen, die Symcon bereits nativ gut darstellen kann, erstellt das Modul bewusst keine eigene HTML-Kachel. Rollladen/Jalousien mit `type: "cover"` und `position` werden über die Symcon-Shutter-Darstellung bzw. das Standardprofil `~Shutter.Reversed` abgebildet. Einfache Türschlösser, Lüfter oder Sirenen bleiben bei den passenden Standarddarstellungen wie Schalter, Slider oder Aufzählung, solange die Exposes keine eigenständige zusammengefasste Kachel nötig machen.

### 4.2 Temperatur-Visualisierung

Für Temperatur-Exposes setzt das Modul automatisch eine moderne Tile-Darstellung. Wenn Zigbee2MQTT `value_min` und `value_max` liefert, werden diese Werte für den Darstellungsbereich genutzt.

Falls ein Temperatur-Expose keinen Wertebereich mitliefert, verwendet das Modul den Fallback-Bereich aus der Instanz-Konfiguration. Standard ist:

| Einstellung | Standard |
| ----------- | -------- |
| Minimum | `-40,0 °C` |
| Maximum | `80,0 °C` |

Der Bereich ist nur für die Darstellung relevant. Er ändert keine Gerätewerte und keine von Zigbee2MQTT gelieferten Exposes.

### 4.3 Farbtemperatur in der Beleuchtungs-Kachel

Für Leuchtmittel mit `color_temp` legt das Modul zusätzlich die Variable `color_temp_kelvin` an. Diese Variable wird für die Farbtemperatur-Seite der Symcon-Standardkachel **Beleuchtung** verwendet, damit die Bedienung in Kelvin statt in Mired erfolgt.

Zigbee2MQTT liefert den Bereich für `color_temp` normalerweise in Mired. Das Modul rechnet diesen Bereich automatisch in Kelvin um:

| Zigbee2MQTT Expose | Symcon-Variable | Darstellung |
| ------------------ | --------------- | ----------- |
| `color_temp` | `color_temp` | Mired-Wert für Zigbee2MQTT |
| `color_temp` | `color_temp_kelvin` | Kelvin-Bedienung für die Beleuchtungs-Kachel |

Wenn Zigbee2MQTT `value_min` und `value_max` für `color_temp` liefert, wird daraus der Kelvin-Bereich berechnet. Mired und Kelvin laufen dabei entgegengesetzt: ein kleiner Mired-Wert bedeutet eine hohe Kelvin-Zahl, ein großer Mired-Wert eine niedrige Kelvin-Zahl. Beispiel: Aus `value_min: 200` und `value_max: 454` wird ungefähr `2202 K` bis `5000 K`.

Falls kein Wertebereich vorhanden ist, verwendet das Modul den Standardbereich `1000 K` bis `12000 K`. Zusätzlich wird ein Farbverlauf von Warmweiß bis Kaltweiß gesetzt, der zum jeweiligen Kelvin-Bereich passt.

Einige Zigbee2MQTT-Device-Definitionen melden für `color_temp` jedoch einen zu großen oder nicht zum konkreten Leuchtmittel passenden Bereich. Das ist kein Fehler der Symcon-Visualisierung und kein Fehler dieses Moduls: Das Modul kann zunächst nur die Werte verwenden, die Zigbee2MQTT über das Expose liefert. Wenn die Zigbee2MQTT-Definition z. B. `153..555 mired` meldet, ergibt das rechnerisch ungefähr `1801 K` bis `6535 K`, auch wenn das reale Leuchtmittel nur etwa `2202 K` bis `5000 K` unterstützt.

Für solche Fälle kann der Kelvin-Bereich in der Instanz-Konfiguration unter **Farbtemperatur-Visualisierung** überschrieben werden:

| Einstellung | Bedeutung |
| ----------- | --------- |
| `Minimum = 0` und `Maximum = 0` | Bereich automatisch aus dem Zigbee2MQTT-Expose berechnen |
| `Minimum > 0` und `Maximum > 0` | diesen Kelvin-Bereich für die Symcon-Darstellung verwenden |

Der Override korrigiert die Symcon-Darstellung der `color_temp_kelvin`-Variable, begrenzt Kelvin-Aktionen auf diesen Bereich und passt die abgeleitete Weiß-Farbe entsprechend an. Er ändert keine Zigbee2MQTT-Device-Definition und keine technischen Fähigkeiten des Leuchtmittels.

Bei reinen Tunable-White-Leuchtmitteln ohne RGB/HS/XY-Farb-Expose legt das Modul zusätzlich eine abgeleitete Variable `color` mit dem Profil `~HexColor` an. Diese Variable zeigt den aktuellen Weißton als Farbe an, bleibt aber eine reine Darstellung und ersetzt keine echte RGB-Steuerung.

### 4.4 Geräteoptionen

Zigbee2MQTT liefert je nach Gerät allgemeine und gerätespezifische Optionen. In der Instanz-Konfiguration erscheint dafür der Bereich **Geräteoptionen**. Dort werden bekannte Optionen mit aktuellem Wert, Typ und Beschreibung angezeigt.

![Geräteoptionen](imgs/geraeteoptionen.png)

Wird eine Option über `Bearbeiten` ausgewählt, passt sich der Editor an den Optionstyp an. Bei `Wahr/Falsch` erscheint z. B. ein Schalter, bei Listen eine JSON-/Attributauswahl und bei Zahlen oder Texten ein Eingabefeld.

![Geräteoptionen Editor](imgs/geraeteoptionen-editor.png)

Die Änderung wird über `bridge/request/device/options` an Zigbee2MQTT gesendet. Wenn eine passende Bridge-Instanz mit gleichem MQTT-Basistopic vorhanden ist, wird diese für die Anfrage genutzt und die Zigbee2MQTT-Antwort geprüft.

Soweit der Typ bekannt ist, zeigt das Formular passende Editoren:

| Optionstyp | Darstellung |
| ---------- | ----------- |
| Boolean/Binary | Schalter |
| Enum/Select | Auswahlliste mit bekannten Werten |
| Numeric | Zahlen-/Textfeld mit Typkonvertierung |
| Text | Textfeld |
| Array | JSON-Array oder, bei Attributoptionen, eine auswählbare Attributliste |
| Object | JSON-Objekt |

Das Modul kennt die häufigsten allgemeinen Zigbee2MQTT-Geräteoptionen wie `debounce`, `debounce_ignore`, `disable_automatic_update_check`, `disabled`, `filtered_attributes`, `filtered_cache`, `filtered_optimistic`, `homeassistant`, `icon`, `optimistic`, `qos`, `retain`, `retention`, `throttle` und `transition`. Zusätzlich werden gerätespezifische Optionen aus `definition.options` angezeigt, wenn Zigbee2MQTT sie für das Gerät liefert.

Für Listen und Objekte muss JSON-Schreibweise verwendet werden, z. B. `["battery"]` für `filtered_attributes` oder `{"key":"value"}` für Objektwerte. Bei Attributoptionen schlägt das Formular bekannte Payload-Attribute aus Exposes, Variablenkatalog und vorhandenen Variablen vor.

Optionen, die Zigbee2MQTT erst nach einem Neustart übernimmt, lösen in der Bridge eine entsprechende Meldung aus.

### 4.5 Binding und Reporting

Wenn Zigbee2MQTT Endpoint-Daten liefert, zeigt die Instanz-Konfiguration den Bereich **Binding und Reporting**. Dort sind Endpoints, Eingangs-/Ausgangscluster sowie vorhandene Bindings und konfigurierte Reportings sichtbar. Bekannte Bindings werden zusätzlich als eigene Übersicht mit Quell-Endpoint, Cluster, Zieltyp, Ziel und Ziel-Endpoint aufgelistet.

Über den Binding-Bereich können Geräte oder Gruppen direkt gebunden oder wieder gelöst werden. Der Quell-Endpoint wird aus den bekannten Endpoints des Geräts als Auswahl angeboten. Als Ziel können lokale Zigbee2MQTT-Geräte- und Gruppeninstanzen sowie von Zigbee2MQTT gemeldete Geräte und Gruppen ausgewählt werden. Die Cluster-Auswahl wird aus den bekannten Clustern des Quell-Endpoints aufgebaut und beim Wechsel von Endpoint oder Zielgerät aktualisiert. Bleibt der Quell-Endpoint leer, wird das Gerät ohne Endpoint-Suffix verwendet.

Über den Reporting-Bereich kann Attribute Reporting gelesen oder konfiguriert werden. Batteriebetriebene Geräte müssen dafür unter Umständen direkt vor dem Ausführen geweckt werden. Nicht jedes Gerät und nicht jedes Attribut unterstützt Reporting.

Die Endpoint-Liste wird aus den von Zigbee2MQTT gelieferten Geräteinformationen aufgebaut. Sie zeigt Endpoint, Name, Eingangscluster, Ausgangscluster sowie die Anzahl bekannter Bindings und konfigurierter Reportings.

### 4.6 Variablenverwaltung

Die Instanz merkt sich alle aus Exposes, Payloads und Systemmeldungen bekannten Variablen in einem lokalen Variablenkatalog. In der Konfiguration erscheint dazu der Bereich **Variablen**. Dort kann pro Variable gesteuert werden, ob das Modul sie automatisch anlegen darf.

![Variablenverwaltung](imgs/variablenverwaltung.png)

Die wichtigsten Zustände in der Liste sind:

| Status | Bedeutung | Aktion |
| --- | --- | --- |
| `Angelegt` | Die Variable existiert im Objektbaum und darf vom Modul verwaltet werden. | `Deaktivieren` |
| `Nicht angelegt` | Die Variable ist aus Exposes, Payloads oder Systemmeldungen bekannt, existiert aber noch nicht im Objektbaum. | `Anlegen` |
| `Deaktiviert, existiert` | Die Variable existiert noch im Objektbaum, ist aber für die automatische Neuanlage gesperrt. | `Aktivieren` |
| `Gelöscht` | Die Variable ist erlaubt, fehlt aber im Objektbaum. | `Anlegen` |

Deaktivierte Variablen werden nicht automatisch gelöscht. Bestehende Variablen bleiben erhalten, werden aber nach einer manuellen Löschung nicht wieder neu erzeugt, solange sie deaktiviert sind.

Composite-Exposes werden dabei auf die tatsächlich anlegbaren Untervariablen reduziert. Ein nicht selbst nutzbarer Composite-Elternknoten wird nicht als eigene Variable angeboten, während Unterwerte wie `options__motor_speed` sauber im Variablenkatalog erscheinen.

## 5. Statusvariablen

Die Statusvariablen werden je nach Funktion und Fähigkeiten der Geräte dynamisch erstellt.  

## 6. PHP-Funktionsreferenz

### RequestAction <!-- omit in toc -->  

   ```php
   RequestAction(int $VariablenID, mixed $Value);
   ```  

   Mit dieser Funktion können alle Aktionen einer Variable ausgelöst werden.  

   > [!IMPORTANT]
   > Bei der Nutzung von RequestAction innerhalb eines Aktionsskriptes darf nicht die Variable übergeben werden, welche dieses Aktionsskript nutzt. Sonst wird eine Endlosschleife ausgelöst. Anstatt RequestAction sind die Z2M_Command oder Z2M_WriteValue* Instanz-Funktionen zu benutzen.

   **Beispiel:**

   Variable ID Status: 12345

   ```php
   RequestAction(12345, true); //Einschalten
   RequestAction(12345, false); //Ausschalten
   ```

   Bei enum-basierten `state`-Variablen werden die Originalwerte von Zigbee2MQTT gesendet. Das ist z. B. für Rollladen wichtig, deren `state` nicht `ON`/`OFF`, sondern `OPEN`, `CLOSE` und `STOP` erwartet.

   ```php
   RequestAction(12345, 'OPEN');  // Rollladen öffnen
   RequestAction(12345, 'STOP');  // Rollladen stoppen
   RequestAction(12345, 'CLOSE'); // Rollladen schließen
   ```

---

### Z2M_WriteValueBoolean <!-- omit in toc -->

   ```php
   bool Z2M_WriteValueBoolean(int $InstanzId, string $Ident, bool $Value);
   ```

   Mit dieser Funktion können Bool Werte an eine Instanz gesendet werden.

   **Beispiel:**

   Variablen-Ident `state` der Instanz 12345

   ```php
   Z2M_WriteValueBoolean(12345, 'state', true); //Einschalten
   ```

---

### Z2M_WriteValueInteger <!-- omit in toc -->

   ```php
   bool Z2M_WriteValueInteger(int $InstanzId, string $Ident, int $Value);
   ```

   Mit dieser Funktion können Integer Werte an eine Instanz gesendet werden.

   **Beispiel:**

   Variablen-Ident `position` der Instanz 12345

   ```php
   Z2M_WriteValueInteger(12345, 'position', 50); // Setze Position auf 50
   ```

---

### Z2M_WriteValueFloat <!-- omit in toc -->

   ```php
   bool Z2M_WriteValueFloat(int $InstanzId, string $Ident, float $Value);
   ```

   Mit dieser Funktion können Float Werte an eine Instanz gesendet werden.

   **Beispiel:**

   Variablen-Ident `calibration_time` der Instanz 12345

   ```php
   Z2M_WriteValueFloat(12345, 'calibration_time', 22.5); // Setze Kalibrierung auf 22,5 Sekunden
   ```

---

### Z2M_WriteValueString <!-- omit in toc -->

   ```php
   bool Z2M_WriteValueString(int $InstanzId, string $Ident, string $Value);
   ```

   Mit dieser Funktion können String Werte an eine Instanz gesendet werden.

   **Beispiel:**

   Variablen-Ident `effect` der Instanz 12345

   ```php
   Z2M_WriteValueString(12345, 'effect', 'blink'); // Effekt Blinken ausführen
   ```

   ---

### Z2M_ReadValue <!-- omit in toc -->

   ```php
   bool Z2M_ReadValue(int $InstanzId, string $Property);
   ```

   Mit dieser Funktion wird eine Leseanfrage für eine bestimmte Eigenschaft an das Gerät gesendet.

   **Beispiel:**

   Property `wifi_status` der Instanz 12345

   ```php
   Z2M_ReadValue(12345, 'wifi_status'); // Lese WiFi-Status
   ```

   ---

### Z2M_SendGetCommand <!-- omit in toc -->

   ```php
   bool Z2M_SendGetCommand(int $InstanzId);
   ```

   Mit dieser Funktion wird eine Leseanfrage für alle bekannten Eigenschaften an das Gerät gesendet.

   **Beispiel:**

   ```php
      Z2M_SendGetCommand(12345);
   ```

   Sendet eine Leseanfrage für alle bekannten Eigenschaften an das Gerät der Instanz 12345.

---

### Z2M_SendSetCommand <!-- omit in toc -->

   ```php
   bool Z2M_SendSetCommand(int $InstanzId, array $Payload);
   ```

   Mit dieser Funktion kann ein beliebiger Payload (Datensatz) an das Gerät gesendet werden.

   **Beispiel:**

   ```php
   $Payload['brightness_step_onoff'] = 10;
   Z2M_SendSetCommand(12345, $Payload);
   ```

   Sendet `brightness_step_onoff` mit dem Wert 10 an das Gerät, welches entsprechend die Helligkeit um den Rohwert 10 erhöht und, falls es vorher ausgeschaltet war, eingeschaltet wird.

---

### Z2M_Command <!-- omit in toc -->

   ```php
   bool Z2M_Command(int $InstanzId, string $Topic, string $Value);
   ```

   Mit dieser Funktion kann ein beliebiger Payload (Datensatz) an das Gerät (Geräte-Topic) gesendet werden.

   **Beispiel:**

   ```php
   $Payload['brightness_step_onoff'] = 10;
   Z2M_Command(12345, 'set', json_encode($Payload));
   ```

   Sendet `brightness_step_onoff` mit dem Wert 10 an das Gerät, welches entsprechend die Helligkeit um den Rohwert 10 erhöht und, falls es vorher ausgeschaltet war, eingeschaltet wird.

---

### Z2M_CommandEx <!-- omit in toc -->

   ```php
   bool Z2M_CommandEx(int $InstanzId, string $FullTopic, string $Value);
   ```

   Mit dieser Funktion kann ein beliebiger Payload (Datensatz) an Z2M gesendet werden.

   **Beispiel:**

   ```php
   $Payload['state'] = '';
   Z2M_CommandEx(12345, 'Keller/Lampe1/get', json_encode($Payload));
   ```

   Dieses Beispiel ruft `state` von `{BaseTopic}Keller/Lampe1` ab.

---

### Z2M_SetColorExt <!-- omit in toc -->

   ```php
   bool Z2M_SetColorExt(int $InstanzId, int $Color, int $TransitionTime);
   ```

   Setzt eine Farbe mit Übergangszeit. `Color` ist ein Symcon-Farbwert als Integer, `TransitionTime` die Übergangszeit in Sekunden.

---

### Z2M_UIExportDebugData <!-- omit in toc -->

   ```php
   string Z2M_UIExportDebugData(int $InstanzId);
   ```

   Exportiert die für Support und Fehlersuche relevanten Instanzdaten als JSON-Download. Die Funktion wird vom Button **Download Debug Data** in der Instanz-Konfiguration genutzt.

## 7. Aktionen

> [!NOTE]  
> **Nutzung von Aktionen mit Ziel Variable:**  
> Grundsätzlich können alle bedienbaren Statusvariablen als Ziel einer [`Aktion`](https://www.symcon.de/service/dokumentation/konzepte/automationen/ablaufplaene/aktionen/) mit 'Auf Wert schalten' angesteuert werden, so das hier keine speziellen Aktionen benutzt werden müssen.

**Zusätzlich** gibt es Sonderfunktionen in Form von speziellen Aktionen, welche für die Zigbee2MQTT-Geräte und Gruppen Instanzen zur Verfügung stehen, wenn diese als Ziel einer Aktion ausgewählt wurden.

Die möglichen Aktionen werden anhand der Statusvariablen der Instanz angeboten, somit sind nicht alle Aktionen immer verfügbar.  

> [!TIP]  
> Über das `i` hinter einer Aktion kann eine Erklärung der Aktion angezeigt werden.  
> Hier als Beispiel das Schrittweise auf/abdimmen.  
> ![Aktionen](imgs/actions.png)  

Liste aller Aktionen:

| Funktion                            | Voraussetzung (Variable) |
| :---------------------------------- | :------------------------ |
| Einschaltverzögerung                | Countdown                 |
| Ausschaltverzögerung                | Countdown                 |
| Helligkeit mit Übergangszeit        | Helligkeit                |
| Dimmen der Helligkeit (absolut)     | Helligkeit                |
| Dimmen der Helligkeit (relativ)     | Helligkeit                |
| Dimmen der Farbtemperatur (absolut) | Farbtemperatur            |
| Dimmen der Farbtemperatur (relativ) | Farbtemperatur            |
| Farbe mit Übergangszeit             | Farbe                     |

## 8. Anhang

### 1. Changelog

[Changelog der Library](../README.md#5-changelog)

### 2. Spenden

Dieses Modul ist für die nicht kommerzielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:

<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=EK4JRP87XLSHW" target="_blank"><img src="https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donate_LG.gif" border="0" /></a> <a href="https://www.amazon.de/hz/wishlist/ls/3JVWED9SZMDPK?ref_=wl_share" target="_blank">Amazon Wunschzettel</a>

### 3. Lizenz

[CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)
