# Zigbee2MQTT-Gerätesimulator

[Deutsch](README.md) | [English](README.en.md)

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
$env:Z2M_SIM_HOST = '127.0.0.1'
$env:Z2M_SIM_USERNAME = 'mqtt-user'
$env:Z2M_SIM_PASSWORD = 'mqtt-password'
php tests/DeviceSimulator/run.php
```

Die Beispiele werden im Repository-Verzeichnis ausgeführt. PHP mit Netzwerkzugriff und ein erreichbarer Test-MQTT-Broker sind erforderlich; der Host muss angegeben werden. Der Standardport ist `1883`, das Standard-Basistopic `Z2M-SIM`. Zugangsdaten nur für den Testbroker verwenden.

Alternativ können `--host`, `--port`, `--username`, `--password`, `--client-id` und
`--base-topic` als Kommandozeilenoptionen übergeben werden. Die entsprechenden Umgebungsvariablen heißen `Z2M_SIM_HOST`, `Z2M_SIM_PORT`, `Z2M_SIM_USERNAME`, `Z2M_SIM_PASSWORD`, `Z2M_SIM_CLIENT_ID` und `Z2M_SIM_BASE_TOPIC`. Für das Passwort
werden Umgebungsvariablen empfohlen, damit es nicht im Verlauf der Shell
gespeichert wird.

Eine separate Zigbee2MQTT-Konfiguratorinstanz mit `Z2M-SIM` als Basistopic
einrichten und mit dem MQTT Client verbinden, der dieses Topic empfängt. Danach
den Konfigurator aktualisieren und eines der zurückgegebenen virtuellen Geräte
erstellen.

Der Simulator wird mit `Strg+C` beendet.

Ein eigenes Basistopic verwenden, das keine echte Zigbee2MQTT-Installation nutzt. Retained Testnachrichten können nach dem Beenden auf dem Broker verbleiben; sie werden nicht durch das Stoppen des Prozesses automatisch gelöscht. Nur die ausdrücklich für diesen Test verwendeten Topics bei Bedarf bereinigen. `--help` zeigt die Optionen. Der Simulator bildet ausgewählte MQTT-Funktionen nach und ersetzt keine vollständige Zigbee-/OTA-/Funkprüfung.

## Payload ohne MQTT anzeigen

```powershell
php tests/DeviceSimulator/run.php --dump
```

Für diesen Modus werden keine Zugangsdaten benötigt.
