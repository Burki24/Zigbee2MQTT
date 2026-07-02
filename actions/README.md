# Zigbee2MQTT Actions

Die Dateien in diesem Verzeichnis sind Symcon-Aktionsvorlagen. Sie werden nicht als eigene PHP-Funktionen direkt aus dem Dateisystem aufgerufen, sondern stehen in Symcon als auswählbare Aktionen zur Verfügung, wenn eine passende Zigbee2MQTT-Geräte- oder Gruppeninstanz als Ziel verwendet wird.

## Verwendung in Symcon

1. In Symcon ein Ereignis, einen Ablaufplan, eine Automatisierung oder einen Aktionsdialog öffnen.
2. Als Ziel eine Zigbee2MQTT-Geräte- oder Gruppeninstanz auswählen.
3. Eine der angebotenen Zigbee2MQTT-Actions auswählen.
4. Parameter wie Helligkeit, Schrittweite, Farbtemperatur, Szene oder Übergangszeit eintragen.
5. Aktion speichern oder testweise ausführen.

Symcon blendet eine Action nur ein, wenn die Zielinstanz die nötigen Variablen-Idents besitzt. Wird eine Action nicht angezeigt, unterstützt das gewählte Gerät oder die Gruppe die dafür nötige Zigbee2MQTT-Funktion sehr wahrscheinlich nicht, oder die entsprechende Variable wurde in der Instanz deaktiviert.

## Actions und Voraussetzungen

| Action | Datei | Voraussetzung | Beschreibung |
| --- | --- | --- | --- |
| Status umschalten | `toggleState.json` | `state`; nicht bei Rollladen-/Cover-Instanzen mit `position`, `position_left` oder `position_right` | Sendet `TOGGLE` an Zigbee2MQTT. |
| Licht schalten mit Übergangszeit | `setStateWithTransition.json` | `state`, `brightness` | Schaltet ein dimmbares Leuchtmittel mit einer definierten Übergangszeit ein oder aus. |
| Helligkeit mit Übergangszeit | `setWithTransition.json` | `brightness` | Setzt die Helligkeit in Prozent und sendet intern den Zigbee2MQTT-Wert 0 bis 254. |
| Helligkeit erhöhen oder verringern | `dimStep.json` | `brightness`, `state` | Führt einen einmaligen relativen Helligkeitsschritt aus. Positive Werte erhöhen, negative Werte verringern die Helligkeit. |
| Relatives Dimmen starten oder anhalten | `dimRelative.json` | `brightness`, `state` | Startet kontinuierliches Dimmen. Positive Werte dimmen heller, negative dunkler, `0` stoppt den Vorgang. |
| Farbtemperatur mit Übergangszeit | `setColorTempWithTransition.json` | `color_temp`, `color_temp_kelvin` | Setzt eine Farbtemperatur in Kelvin und rechnet intern in Mired um. |
| Farbtemperatur erhöhen oder verringern | `dimColorTempStep.json` | `color_temp` | Führt einen einmaligen relativen Farbtemperaturschritt aus. Positive Werte werden wärmer, negative kälter. |
| Relative Farbtemperaturänderung starten oder anhalten | `dimColorTempRelative.json` | `color_temp` | Startet eine fortlaufende Farbtemperaturänderung. `0` stoppt den Vorgang. |
| Farbe mit Übergangszeit | `setColorWithTransition.json` | `color` | Setzt eine RGB-Farbe mit Übergangszeit. |
| Einschalten mit Ausschaltdauer | `setOnTime.json` | `state`, `countdown` | Schaltet ein Gerät ein und setzt zusätzlich einen Countdown zum späteren Ausschalten. |
| Ausschalten mit Einschaltdauer | `setOffTime.json` | `state`, `countdown` | Schaltet ein Gerät aus und setzt zusätzlich einen Countdown zum späteren Einschalten. |
| Szene abrufen | `recallScene.json` | Zigbee2MQTT-Geräte- oder Gruppeninstanz | Ruft eine vorhandene Zigbee-Szene per `scene_recall` ab. |

## Parameter

### Übergangszeit

`TransitionTime` wird in Sekunden angegeben. `0` bedeutet sofortige Ausführung. Das Zielgerät muss Übergangszeiten über Zigbee2MQTT unterstützen; andernfalls kann Zigbee2MQTT den Wert ignorieren.

### Helligkeit

Symcon zeigt die Helligkeit in Prozent an. Die Actions rechnen den Prozentwert intern auf den Zigbee2MQTT-Bereich `0` bis `254` um.

### Farbtemperatur

Die Kelvin-Action verwendet die Variable `color_temp_kelvin` und rechnet den Wert intern nach Mired um:

```text
mired = 1000000 / kelvin
```

Die relativen Farbtemperatur-Actions verwenden die nativen Zigbee2MQTT-Befehle für Farbtemperatur-Schritte oder Farbtemperatur-Bewegung.

### Countdown

Die Countdown-Actions funktionieren nur bei Geräten, die ein `countdown`-Expose anbieten. Der Wert wird in Sekunden angegeben.

### Szene

`SceneID` ist die Szenen-ID in Zigbee2MQTT. Die Szene muss vorher in Zigbee2MQTT beziehungsweise über die Gruppen- oder Gerätefunktionen angelegt worden sein.

## Beispiel: Actions in einem Ablaufplan

Ein typischer Ablaufplan kann beispielsweise so aussehen:

1. Bewegungsmelder erkennt Bewegung.
2. Action `Licht schalten mit Übergangszeit` auf eine Leuchtmittel- oder Gruppeninstanz anwenden.
3. `State` auf `ON` setzen.
4. `TransitionTime` auf `1.5` Sekunden setzen.
5. Nach Ablauf einer Wartezeit Action `Ausschalten mit Einschaltdauer` oder `Licht schalten mit Übergangszeit` verwenden.

So lassen sich einfache Lichtabläufe ohne eigenes PHP-Skript umsetzen.

## Beispiel: Gleiches Verhalten im PHP-Skript

Die Actions nutzen intern die öffentlichen Modul-Funktionen. Dasselbe Verhalten kann auch in einem Symcon-Skript ausgelöst werden:

```php
<?php

$instanceID = 12345;

// Status umschalten
Z2M_SendSetCommand($instanceID, ['state' => 'TOGGLE']);

// Helligkeit mit Übergangszeit auf 50 Prozent setzen
Z2M_SendSetCommand($instanceID, [
    'brightness' => (int) round(50 * 254 / 100),
    'transition' => 2.0
]);

// Farbtemperatur mit Übergangszeit auf 4000 Kelvin setzen
Z2M_SendSetCommand($instanceID, [
    'color_temp' => (int) round(1000000 / 4000),
    'transition' => 2.0
]);

// RGB-Farbe mit Übergangszeit setzen
Z2M_SetColorExt($instanceID, 0xFFAA00, 2.0);

// Szene 1 abrufen
Z2M_SendSetCommand($instanceID, ['scene_recall' => 1]);
```

## Hinweise und Grenzen

- Actions senden Zigbee2MQTT-Set-Payloads. Sie ersetzen keine direkt konfigurierte Variablenaktion.
- Batteriebetriebene Geräte müssen für manche Befehle wach sein.
- Übergangszeiten werden nur umgesetzt, wenn das Gerät diese Funktion unterstützt.
- Countdown-Actions erscheinen nur bei Geräten mit passendem `countdown`-Expose.
- Szenen müssen vor dem Abruf bereits existieren.
- Für eigene Skripte sollten die dokumentierten `Z2M_*`-Funktionen genutzt werden. Ein direkter Aufruf von `RequestAction()` auf Statusvariablen ist dafür normalerweise nicht nötig.
