# Zigbee2MQTT device simulator

This development tool simulates Zigbee2MQTT devices entirely through MQTT. It
does not require a physical Zigbee device and does not modify a running
Zigbee2MQTT installation.

The simulator uses the isolated base topic `Z2M-SIM` and provides:

- `Test/VirtualTunableWhite`: a tunable-white light with brightness, color
  temperature and presets.
- `Test/AllExposes`: a synthetic compatibility device containing grouped and
  standalone binary, numeric, enum, text, composite and list exposes as well as
  config and diagnostic categories.

It answers the Symcon extension requests used by the configurator, preserves
transaction IDs and echoes `/set` changes as device state updates.

## Start

PowerShell example:

```powershell
$env:Z2M_SIM_HOST = '192.168.178.6'
$env:Z2M_SIM_USERNAME = 'mqtt-user'
$env:Z2M_SIM_PASSWORD = 'mqtt-password'
php E:\git\Zigbee2MQTT\tests\DeviceSimulator\run.php
```

Alternatively, pass `--host`, `--port`, `--username`, `--password` and
`--base-topic` as command-line options. Environment variables are recommended
for the password because they keep it out of the shell history.

Configure a separate Zigbee2MQTT configurator instance with `Z2M-SIM` as its
base topic and connect it to the MQTT client that receives this topic. Refresh
the configurator and create either virtual device from the returned list.

Stop the simulator with `Ctrl+C`.

## Inspect the payload without MQTT

```powershell
php E:\git\Zigbee2MQTT\tests\DeviceSimulator\run.php --dump
```

No credentials are required for this mode.
