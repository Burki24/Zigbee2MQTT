<?php

declare(strict_types=1);

namespace Zigbee2MQTT\Tools\DeviceSimulator;

require_once __DIR__ . '/SimulatorDeviceCatalog.php';

final class Zigbee2MQTTDeviceSimulator
{
    /** @var array<string,array<string,mixed>> */
    private array $devices;

    /** @var \Closure(string,string,bool):void */
    private \Closure $publisher;

    public function __construct(private readonly string $baseTopic, callable $publisher)
    {
        $this->devices = SimulatorDeviceCatalog::devices();
        $this->publisher = \Closure::fromCallable($publisher);
    }

    public function publishInitialState(): void
    {
        $this->publish($this->baseTopic . '/bridge/state', ['state' => 'online'], true);
        foreach ($this->devices as $device) {
            $friendlyName = (string) $device['friendly_name'];
            $this->publish($this->baseTopic . '/' . $friendlyName . '/availability', ['state' => 'online'], true);
            $this->publishDeviceState($friendlyName, true);
        }
    }

    public function handleMessage(string $topic, string $payloadJson): bool
    {
        if (!str_starts_with($topic, $this->baseTopic . '/')) {
            return false;
        }

        $payload = json_decode($payloadJson, true);
        if (!\is_array($payload)) {
            $payload = [];
        }

        if ($this->handleExtensionRequest($topic, $payload)) {
            return true;
        }

        foreach ($this->devices as $friendlyName => $device) {
            $deviceTopic = $this->baseTopic . '/' . $friendlyName;
            if ($topic === $deviceTopic . '/set') {
                $this->devices[$friendlyName]['state'] = $this->mergeState(
                    (array) ($device['state'] ?? []),
                    $payload
                );
                $this->devices[$friendlyName]['state']['last_seen'] = time();
                $this->publishDeviceState($friendlyName);
                return true;
            }
            if ($topic === $deviceTopic . '/get') {
                $this->publishRequestedState($friendlyName, $payload);
                return true;
            }
        }

        return false;
    }

    /** @return array<string,array<string,mixed>> */
    public function devices(): array
    {
        return $this->devices;
    }

    /** @param array<string,mixed> $payload */
    private function handleExtensionRequest(string $topic, array $payload): bool
    {
        $prefix = $this->baseTopic . '/SymconExtension/';
        if (!str_starts_with($topic, $prefix)) {
            return false;
        }

        $transaction = $payload['transaction'] ?? null;
        $response = [];

        if ($topic === $prefix . 'lists/request/getDevicesLight') {
            $response['list'] = array_values(array_map(
                fn (array $device): array => $this->lightweightDevice($device),
                $this->devices
            ));
        } elseif ($topic === $prefix . 'lists/request/getDevices') {
            $response['list'] = array_values(array_map(
                fn (array $device): array => $this->deviceInformation($device, false),
                $this->devices
            ));
        } elseif ($topic === $prefix . 'lists/request/getGroups') {
            $response['list'] = [];
        } elseif (str_starts_with($topic, $prefix . 'request/getDeviceInfo/')) {
            $identifier = substr($topic, \strlen($prefix . 'request/getDeviceInfo/'));
            $device = $this->findDevice(rawurldecode($identifier));
            if ($device !== null) {
                $response = $this->deviceInformation($device, true);
            }
        } else {
            return false;
        }

        if ($transaction !== null) {
            $response['transaction'] = $transaction;
        }
        $responseTopic = str_replace('/request/', '/response/', $topic);
        $this->publish($responseTopic, $response);
        return true;
    }

    /** @param array<string,mixed> $device */
    private function lightweightDevice(array $device): array
    {
        return array_intersect_key($device, array_flip([
            'ieeeAddr',
            'type',
            'networkAddress',
            'model',
            'vendor',
            'description',
            'friendly_name',
            'manufacturerName',
            'powerSource',
            'modelID',
        ]));
    }

    /** @param array<string,mixed> $device */
    private function deviceInformation(array $device, bool $includeExposes): array
    {
        $information = $device;
        unset($information['state']);
        if (!$includeExposes) {
            unset($information['exposes']);
        }
        return $information;
    }

    /** @return array<string,mixed>|null */
    private function findDevice(string $identifier): ?array
    {
        if (isset($this->devices[$identifier])) {
            return $this->devices[$identifier];
        }
        foreach ($this->devices as $device) {
            if (($device['ieeeAddr'] ?? null) === $identifier) {
                return $device;
            }
        }
        return null;
    }

    /** @param array<string,mixed> $requested */
    private function publishRequestedState(string $friendlyName, array $requested): void
    {
        $state = (array) ($this->devices[$friendlyName]['state'] ?? []);
        if ($requested === []) {
            $this->publishDeviceState($friendlyName);
            return;
        }

        $response = [];
        foreach (array_keys($requested) as $property) {
            if (array_key_exists($property, $state)) {
                $response[$property] = $state[$property];
            }
        }
        $response['last_seen'] = time();
        $this->publish($this->baseTopic . '/' . $friendlyName, $response);
    }

    private function publishDeviceState(string $friendlyName, bool $retain = false): void
    {
        $state = (array) ($this->devices[$friendlyName]['state'] ?? []);
        $state['last_seen'] = time();
        $this->devices[$friendlyName]['state'] = $state;
        $this->publish($this->baseTopic . '/' . $friendlyName, $state, $retain);
    }

    /**
     * @param array<string,mixed> $current
     * @param array<string,mixed> $changes
     * @return array<string,mixed>
     */
    private function mergeState(array $current, array $changes): array
    {
        foreach ($changes as $key => $value) {
            if (\is_array($value) && \is_array($current[$key] ?? null)) {
                $current[$key] = $this->mergeState($current[$key], $value);
            } else {
                $current[$key] = $value;
            }
        }
        return $current;
    }

    /** @param array<string,mixed> $payload */
    private function publish(string $topic, array $payload, bool $retain = false): void
    {
        ($this->publisher)(
            $topic,
            json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $retain
        );
    }
}
