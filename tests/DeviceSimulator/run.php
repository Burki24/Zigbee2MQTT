<?php

declare(strict_types=1);

use Zigbee2MQTT\Tools\DeviceSimulator\Zigbee2MQTTDeviceSimulator;

require_once dirname(__DIR__, 2) . '/libs/phpMQTT.php';
require_once __DIR__ . '/Zigbee2MQTTDeviceSimulator.php';

/**
 * Liest eine Simulatoroption aus CLI-Argumenten, Umgebung oder Standardwert.
 *
 * @param array  $options     Von `getopt()` gelieferte Optionen.
 * @param string $name        Name der CLI-Option.
 * @param string $environment Name der alternativen Umgebungsvariable.
 * @param mixed  $default     Rückgabewert, wenn beide Quellen fehlen.
 */
function simulatorOption(array $options, string $name, string $environment, mixed $default = null): mixed
{
    if (array_key_exists($name, $options)) {
        return $options[$name];
    }
    $value = getenv($environment);
    return $value === false ? $default : $value;
}

/**
 * Gibt Aufrufsyntax und verfügbare Einstellungen des Gerätesimulators aus.
 */
function printSimulatorHelp(): void
{
    echo <<<'HELP'
Zigbee2MQTT virtual device simulator

Usage:
  php run.php --host=HOST [--port=1883] [--username=USER] [--password=PASS]
              [--base-topic=Z2M-SIM] [--client-id=CLIENT]
  php run.php --dump

Environment variable alternatives:
  Z2M_SIM_HOST, Z2M_SIM_PORT, Z2M_SIM_USERNAME, Z2M_SIM_PASSWORD,
  Z2M_SIM_BASE_TOPIC, Z2M_SIM_CLIENT_ID

The password is never written to the repository or printed by the simulator.
Stop the simulator with Ctrl+C.

HELP;
}

$options = getopt('', [
    'host:',
    'port:',
    'username:',
    'password:',
    'base-topic:',
    'client-id:',
    'dump',
    'help',
]);

if (isset($options['help'])) {
    printSimulatorHelp();
    exit(0);
}

$baseTopic = trim((string) simulatorOption($options, 'base-topic', 'Z2M_SIM_BASE_TOPIC', 'Z2M-SIM'), '/');
$published = [];
$simulator = new Zigbee2MQTTDeviceSimulator(
    $baseTopic,
    static function (string $topic, string $payload, bool $retain) use (&$published): void
    {
        $published[] = ['topic' => $topic, 'payload' => json_decode($payload, true), 'retain' => $retain];
    }
);

if (isset($options['dump'])) {
    echo json_encode(
        ['base_topic' => $baseTopic, 'devices' => $simulator->devices()],
        JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    ) . PHP_EOL;
    exit(0);
}

$host = (string) simulatorOption($options, 'host', 'Z2M_SIM_HOST', '');
if ($host === '') {
    fwrite(STDERR, "Missing MQTT host. Use --host or Z2M_SIM_HOST.\n\n");
    printSimulatorHelp();
    exit(2);
}

$port = (int) simulatorOption($options, 'port', 'Z2M_SIM_PORT', 1883);
$username = (string) simulatorOption($options, 'username', 'Z2M_SIM_USERNAME', '');
$password = (string) simulatorOption($options, 'password', 'Z2M_SIM_PASSWORD', '');
$clientId = (string) simulatorOption(
    $options,
    'client-id',
    'Z2M_SIM_CLIENT_ID',
    'symcon-z2m-simulator-' . substr(hash('sha256', php_uname('n') . getmypid()), 0, 12)
);

$mqtt = new \Zigbee2MQTT\phpMQTT($host, $port, $clientId);
$will = [
    'topic'   => $baseTopic . '/bridge/state',
    'content' => json_encode(['state' => 'offline'], JSON_THROW_ON_ERROR),
    'qos'     => 0,
    'retain'  => true,
];

if (!$mqtt->connect(true, $will, $username !== '' ? $username : null, $password !== '' ? $password : null)) {
    fwrite(STDERR, "Unable to connect to the MQTT broker.\n");
    exit(3);
}

$simulator = new Zigbee2MQTTDeviceSimulator(
    $baseTopic,
    static function (string $topic, string $payload, bool $retain) use ($mqtt): void
    {
        $mqtt->publish($topic, $payload, 0, $retain);
        echo date('H:i:s') . ' TX ' . $topic . PHP_EOL;
    }
);

$mqtt->subscribe([$baseTopic . '/#' => 0]);
$simulator->publishInitialState();

echo sprintf(
    "Simulator connected to %s:%d using base topic %s. Devices: %s\n",
    $host,
    $port,
    $baseTopic,
    implode(', ', array_keys($simulator->devices()))
);

while (true) {
    $message = $mqtt->proc();
    if (!\is_array($message)) {
        continue;
    }
    [$topic, $payload] = $message;
    if ($simulator->handleMessage((string) $topic, (string) $payload)) {
        echo date('H:i:s') . ' RX ' . $topic . PHP_EOL;
    }
}
