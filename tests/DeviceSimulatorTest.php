<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Zigbee2MQTT\Tools\DeviceSimulator\SimulatorDeviceCatalog;
use Zigbee2MQTT\Tools\DeviceSimulator\Zigbee2MQTTDeviceSimulator;

require_once __DIR__ . '/DeviceSimulator/SimulatorDeviceCatalog.php';
require_once __DIR__ . '/DeviceSimulator/Zigbee2MQTTDeviceSimulator.php';

final class DeviceSimulatorTest extends TestCase
{
    public function testCatalogContainsTunableWhiteAndAllExposeDevices(): void
    {
        $devices = SimulatorDeviceCatalog::devices();

        $this->assertArrayHasKey('Test/VirtualTunableWhite', $devices);
        $this->assertArrayHasKey('Test/AllExposes', $devices);
        $this->assertNotEmpty($devices['Test/AllExposes']['exposes']);
    }

    public function testAllExposeDeviceCoversEverySupportedExposeShape(): void
    {
        $device = SimulatorDeviceCatalog::devices()['Test/AllExposes'];
        $types = [];
        $categories = [];
        $walk = static function (array $features) use (&$walk, &$types, &$categories): void
        {
            foreach ($features as $feature) {
                if (!\is_array($feature)) {
                    continue;
                }
                if (isset($feature['type'])) {
                    $types[] = $feature['type'];
                }
                if (isset($feature['category'])) {
                    $categories[] = $feature['category'];
                }
                if (isset($feature['features']) && \is_array($feature['features'])) {
                    $walk($feature['features']);
                }
                if (isset($feature['item_type']) && \is_array($feature['item_type'])) {
                    $walk([$feature['item_type']]);
                }
            }
        };
        $walk($device['exposes']);

        foreach (['binary', 'numeric', 'enum', 'text', 'composite', 'list', 'light', 'switch', 'cover', 'lock', 'climate', 'fan'] as $type) {
            $this->assertContains($type, $types);
        }
        $this->assertContains('config', $categories);
        $this->assertContains('diagnostic', $categories);
    }

    public function testExtensionResponsesPreserveTransactionAndTopic(): void
    {
        $published = [];
        $simulator = new Zigbee2MQTTDeviceSimulator(
            'Z2M-SIM',
            static function (string $topic, string $payload, bool $retain) use (&$published): void
            {
                $published[] = [$topic, json_decode($payload, true), $retain];
            }
        );

        $handled = $simulator->handleMessage(
            'Z2M-SIM/SymconExtension/request/getDeviceInfo/Test/AllExposes',
            json_encode(['transaction' => 6568], JSON_THROW_ON_ERROR)
        );

        $this->assertTrue($handled);
        $this->assertSame('Z2M-SIM/SymconExtension/response/getDeviceInfo/Test/AllExposes', $published[0][0]);
        $this->assertSame(6568, $published[0][1]['transaction']);
        $this->assertSame('SIM-ALL-01', $published[0][1]['model']);
        $this->assertNotEmpty($published[0][1]['exposes']);
    }

    public function testSetCommandIsEchoedAsUpdatedState(): void
    {
        $published = [];
        $simulator = new Zigbee2MQTTDeviceSimulator(
            'Z2M-SIM',
            static function (string $topic, string $payload, bool $retain) use (&$published): void
            {
                $published[] = [$topic, json_decode($payload, true), $retain];
            }
        );

        $this->assertTrue($simulator->handleMessage(
            'Z2M-SIM/Test/VirtualTunableWhite/set',
            json_encode(['brightness' => 42, 'state' => 'OFF'], JSON_THROW_ON_ERROR)
        ));

        $last = $published[array_key_last($published)];
        $this->assertSame('Z2M-SIM/Test/VirtualTunableWhite', $last[0]);
        $this->assertSame(42, $last[1]['brightness']);
        $this->assertSame('OFF', $last[1]['state']);
        $this->assertFalse($last[2]);
    }
}
