<?php

declare(strict_types=1);

trait WebHookHelper
{
    /* -------------------- DEVICE CACHE -------------------- */

    private function UpdateDeviceCache(string $topic, $payload)
    {
        if (strpos($topic, '/bridge/') !== false) {
            return;
        }

        $parts = explode('/', $topic);
        $device = end($parts);

        if (!is_array($payload)) {
            return;
        }

        $devices = json_decode($this->GetBuffer('Devices'), true);

        if (!is_array($devices)) {
            $devices = [];
        }

        $devices[$device] = $payload;

        $this->SetBuffer('Devices', json_encode($devices));
    }

    private function GetDevices(): array
    {
        $devices = json_decode($this->GetBuffer('Devices'), true);

        if (!is_array($devices)) {
            return [];
        }

        return $devices;
    }

    /* -------------------- WEB UI -------------------- */

    public function ProcessHookData()
    {
        $action = $_GET['action'] ?? 'overview';
        $name   = $_GET['name'] ?? '';

        switch ($action) {

            case 'on':
                $this->SendToZ2M($name, ['state' => 'ON']);
                echo 'OK';
                break;

            case 'off':
                $this->SendToZ2M($name, ['state' => 'OFF']);
                echo 'OK';
                break;

            case 'brightness':
                $value = intval($_GET['value'] ?? 0);
                $value = (int) round($value * 2.54);

                $this->SendToZ2M($name, ['brightness' => $value]);
                echo 'OK';
                break;

            case 'device':
                $this->HandleDevice();
                break;

            default:
                $this->RenderOverview();
                break;
        }
    }

    private function RenderOverview()
    {
        header('Content-Type: text/html');

        $devices = $this->GetDevices();

        echo '<h1>Zigbee2MQTT</h1>';

        echo '<style>
        body { font-family: Arial; background:#111; color:#eee; }
        a { display:block; margin:10px; padding:10px; background:#333; color:#fff; text-decoration:none; }
        </style>';

        foreach ($devices as $name => $data) {
            $state = $data['state'] ?? 'unknown';

            echo "<a href='?action=device&name={$name}'>
                    {$name} ({$state})
                  </a>";
        }
    }

    private function HandleDevice()
    {
        header('Content-Type: text/html');

        $name = $_GET['name'] ?? '';
        $devices = $this->GetDevices();

        if (!isset($devices[$name])) {
            echo 'Device not found';
            return;
        }

        $data = $devices[$name];

        echo "<h2>{$name}</h2>";

        if (isset($data['state'])) {
            echo "<a href='?action=on&name={$name}'>ON</a>";
            echo "<a href='?action=off&name={$name}'>OFF</a>";
        }

        if (isset($data['brightness'])) {
            $b = $data['brightness'];
            echo "<p>Brightness: {$b}</p>";
            echo "<a href='?action=brightness&name={$name}&value=50'>50%</a>";
            echo "<a href='?action=brightness&name={$name}&value=100'>100%</a>";
        }

        echo "<br><a href='/hook/z2m/ui'>⬅ Back</a>";
    }

    private function SendToZ2M(string $device, array $payload)
    {
        $topic = '/' . $device . '/set';
        $this->SendData($topic, $payload);
    }
}
