<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/autoload.php';

use PHPUnit\Framework\TestCase;

class BridgeTest extends TestCase
{
    public function setUp(): void
    {
        IPS\Kernel::reset();
        IPS\ModuleLoader::loadLibrary(__DIR__ . '/../library.json');
        IPS\ModuleLoader::loadLibrary(__DIR__ . '/stubs/CoreStubs/library.json');
        IPS\ModuleLoader::loadLibrary(__DIR__ . '/stubs/IOStubs/library.json');

        parent::setUp();
    }

    public function testCheckOTAUpdateUsesCurrentZigbee2MqttResponseField(): void
    {
        $bridge = $this->createBridgeTestDouble([
            'status' => 'ok',
            'data'   => [
                'id'               => 'test_device',
                'update_available' => true
            ]
        ]);

        $this->assertTrue($bridge->CheckOTAUpdate('test_device'));
        $this->assertSame('/bridge/request/device/ota_update/check', $bridge->lastTopic);
        $this->assertSame(['id' => 'test_device'], $bridge->lastPayload);
        $this->assertSame(10000, $bridge->lastTimeout);
    }

    public function testCheckOTAUpdateKeepsLegacyResponseFieldCompatible(): void
    {
        $bridge = $this->createBridgeTestDouble([
            'status' => 'ok',
            'data'   => [
                'id'              => 'test_device',
                'updateAvailable' => true
            ]
        ]);

        $this->assertTrue($bridge->CheckOTAUpdate('test_device'));
    }

    public function testPerformOTAUpdateIsSentAsAsyncBridgeCommand(): void
    {
        $bridge = $this->createBridgeTestDouble(true);

        $this->assertTrue($bridge->PerformOTAUpdate('test_device'));
        $this->assertSame('/bridge/request/device/ota_update/update', $bridge->lastTopic);
        $this->assertSame(['id' => 'test_device'], $bridge->lastPayload);
        $this->assertSame(0, $bridge->lastTimeout);
    }

    private function createBridgeTestDouble(array|bool $result): Zigbee2MQTTBridge
    {
        return new class(900001, $result) extends Zigbee2MQTTBridge {
            public string $lastTopic = '';
            public array $lastPayload = [];
            public int $lastTimeout = -1;

            public function __construct(int $InstanceID, private array|bool $result)
            {
                parent::__construct($InstanceID);
            }

            protected function SendData(string $Topic, array $Payload = [], int $Timeout = 5000): array|bool
            {
                $this->lastTopic = $Topic;
                $this->lastPayload = $Payload;
                $this->lastTimeout = $Timeout;

                return $this->result;
            }
        };
    }
}
