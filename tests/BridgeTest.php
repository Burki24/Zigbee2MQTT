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

    public function testCheckOTADowngradeWithUrlUsesDowngradeTopicAndUrlPayload(): void
    {
        $bridge = $this->createBridgeTestDouble([
            'status' => 'ok',
            'data'   => [
                'id'               => 'test_device',
                'update_available' => true
            ]
        ]);

        $this->assertTrue($bridge->CheckOTADowngradeWithUrl('test_device', 'ota/index.json'));
        $this->assertSame('/bridge/request/device/ota_update/check/downgrade', $bridge->lastTopic);
        $this->assertSame(['id' => 'test_device', 'url' => 'ota/index.json'], $bridge->lastPayload);
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

    public function testPerformOTADowngradeWithUrlIsSentAsAsyncBridgeCommand(): void
    {
        $bridge = $this->createBridgeTestDouble(true);

        $this->assertTrue($bridge->PerformOTADowngradeWithUrl('test_device', 'firmware.ota'));
        $this->assertSame('/bridge/request/device/ota_update/update/downgrade', $bridge->lastTopic);
        $this->assertSame(['id' => 'test_device', 'url' => 'firmware.ota'], $bridge->lastPayload);
        $this->assertSame(0, $bridge->lastTimeout);
    }

    public function testScheduleOTAUpdateWithUrlWaitsForBridgeResponse(): void
    {
        $bridge = $this->createBridgeTestDouble([
            'status' => 'ok',
            'data'   => ['id' => 'test_device']
        ]);

        $this->assertTrue($bridge->ScheduleOTAUpdateWithUrl('test_device', 'ota/index.json'));
        $this->assertSame('/bridge/request/device/ota_update/schedule', $bridge->lastTopic);
        $this->assertSame(['id' => 'test_device', 'url' => 'ota/index.json'], $bridge->lastPayload);
        $this->assertSame(5000, $bridge->lastTimeout);
    }

    public function testScheduleOTADowngradeUsesDowngradeTopic(): void
    {
        $bridge = $this->createBridgeTestDouble([
            'status' => 'ok',
            'data'   => ['id' => 'test_device']
        ]);

        $this->assertTrue($bridge->ScheduleOTADowngrade('test_device'));
        $this->assertSame('/bridge/request/device/ota_update/schedule/downgrade', $bridge->lastTopic);
        $this->assertSame(['id' => 'test_device'], $bridge->lastPayload);
        $this->assertSame(5000, $bridge->lastTimeout);
    }

    public function testUnscheduleOTAUpdateUsesUnscheduleTopic(): void
    {
        $bridge = $this->createBridgeTestDouble([
            'status' => 'ok',
            'data'   => ['id' => 'test_device']
        ]);

        $this->assertTrue($bridge->UnscheduleOTAUpdate('test_device'));
        $this->assertSame('/bridge/request/device/ota_update/unschedule', $bridge->lastTopic);
        $this->assertSame(['id' => 'test_device'], $bridge->lastPayload);
        $this->assertSame(5000, $bridge->lastTimeout);
    }

    public function testSetDeviceOptionsUsesDeviceOptionsBridgeRequest(): void
    {
        $bridge = $this->createBridgeTestDouble([
            'status' => 'ok',
            'data'   => [
                'id'               => 'test_device',
                'restart_required' => false
            ]
        ]);

        $this->assertTrue($bridge->SetDeviceOptions('test_device', '{"transition":1,"filtered_attributes":["battery"]}'));
        $this->assertSame('/bridge/request/device/options', $bridge->lastTopic);
        $this->assertSame([
            'id'      => 'test_device',
            'options' => [
                'transition'          => 1,
                'filtered_attributes' => ['battery']
            ]
        ], $bridge->lastPayload);
        $this->assertSame(5000, $bridge->lastTimeout);
    }

    public function testSetDeviceOptionsRejectsInvalidJson(): void
    {
        $bridge = $this->createBridgeTestDouble(true);

        $this->assertFalse(@$bridge->SetDeviceOptions('test_device', 'not json'));
        $this->assertSame('', $bridge->lastTopic);
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
