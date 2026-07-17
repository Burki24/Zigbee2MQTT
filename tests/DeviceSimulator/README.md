# Zigbee2MQTT-Gerätesimulator

Dieses Entwicklungswerkzeug simuliert Zigbee2MQTT-Geräte vollständig über MQTT.
Es benötigt kein physisches Zigbee-Gerät und verändert keine laufende
Zigbee2MQTT-Installation.

Der Simulator verwendet das isolierte Basistopic `Z2M-SIM` und stellt folgende
virtuelle Geräte bereit:

- `Test/VirtualTunableWhite`: Eine abstimmbare Weißlichtleuchte mit Helligkeit,
  Farbtemperatur und Voreinstellungen.
- `Test/AllExposes`: Ein synthetisches Kompatibilitätsgerät mit gruppierten und
  eigenständigen binären, numerischen, Aufzählungs-, Text-, Composite- und
  Listen-Exposes sowie Konfigurations- und Diagnosekategorien.

Der Simulator beantwortet die vom Konfigurator verwendeten
Symcon-Erweiterungsanfragen, übernimmt Transaktions-IDs und gibt Änderungen über
`/set` als aktualisierten Gerätestatus zurück.

## Simulator starten

Beispiel für PowerShell:

```powershell
$env:Z2M_SIM_HOST = '192.168.178.6'
$env:Z2M_SIM_USERNAME = 'mqtt-user'
$env:Z2M_SIM_PASSWORD = 'mqtt-password'
php E:\git\Zigbee2MQTT\tests\DeviceSimulator\run.php
```

Alternativ können `--host`, `--port`, `--username`, `--password` und
`--base-topic` als Kommandozeilenoptionen übergeben werden. Für das Passwort
werden Umgebungsvariablen empfohlen, damit es nicht im Verlauf der Shell
gespeichert wird.

Eine separate Zigbee2MQTT-Konfiguratorinstanz mit `Z2M-SIM` als Basistopic
einrichten und mit dem MQTT Client verbinden, der dieses Topic empfängt. Danach
den Konfigurator aktualisieren und eines der zurückgegebenen virtuellen Geräte
erstellen.

Der Simulator wird mit `Strg+C` beendet.

## Payload ohne MQTT anzeigen

```powershell
php E:\git\Zigbee2MQTT\tests\DeviceSimulator\run.php --dump
```

Für diesen Modus werden keine Zugangsdaten benötigt.
