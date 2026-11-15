# Zigbee2MQTT device simulator

[Deutsch](README.md) | [English](README.en.md)

This developer tool simulates selected Zigbee2MQTT devices over MQTT without physical Zigbee hardware or modifying a running Zigbee2MQTT installation. Use an isolated test broker/topic, not the base topic of a real installation.

The default base topic is `Z2M-SIM`. Virtual devices:

- `Test/VirtualTunableWhite`: brightness, color temperature and presets.
- `Test/AllExposes`: grouped/standalone binary, numeric, enum, text, composite and list exposes, including configuration/diagnostic categories.

The simulator responds to the configurator's Symcon extension queries, preserves transaction IDs and publishes updated device state in response to `/set`. It does not simulate every Zigbee2MQTT feature, radio behavior or real firmware updates.

## Start

Run from the repository directory using PHP with network access and a reachable test MQTT broker:

```powershell
$env:Z2M_SIM_HOST = '127.0.0.1'
$env:Z2M_SIM_USERNAME = 'mqtt-user'
$env:Z2M_SIM_PASSWORD = 'mqtt-password'
php tests/DeviceSimulator/run.php
```

Replace the example host and credentials with those of the test broker. A host is required; the default port is `1883`.

| CLI option | Environment variable |
| --- | --- |
| `--host` | `Z2M_SIM_HOST` |
| `--port` | `Z2M_SIM_PORT` |
| `--username` | `Z2M_SIM_USERNAME` |
| `--password` | `Z2M_SIM_PASSWORD` |
| `--base-topic` | `Z2M_SIM_BASE_TOPIC` |
| `--client-id` | `Z2M_SIM_CLIENT_ID` |

Prefer the password environment variable to avoid storing it in shell history. `--help` lists the options.

Create a separate Zigbee2MQTT configurator using `Z2M-SIM` and connect it to the MQTT Client receiving that topic. Refresh its list and create one of the virtual devices. Keep the test topic unique and separate from real installations.

Stop with **Ctrl+C**. Retained test messages may remain on the broker; stopping the process does not automatically delete them. Remove only the explicitly designated test topics if cleanup is needed.

## Print payloads without MQTT

```powershell
php tests/DeviceSimulator/run.php --dump
```

No credentials or broker connection are needed in this mode.
