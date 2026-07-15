<?php

declare(strict_types=1);

use Zigbee2MQTT\Tools\DeviceSimulator\SimulatorDeviceCatalog;

require_once __DIR__ . '/DumpInclude.php';
require_once __DIR__ . '/DeviceSimulator/SimulatorDeviceCatalog.php';

/**
 * Prüft die Verarbeitung des vollständigen Simulator-Expose-Satzes durch das Gerätemodul.
 */
final class DeviceSimulatorModuleTest extends DumpInclude
{
    private const DEVICE_MODULE_ID = '{E5BB36C6-A70B-EB23-3716-9151A09AC8A2}';

    public function testAllExposePayloadCanBeRegisteredAndProcessedByDeviceModule(): void
    {
        $catalogDevice = SimulatorDeviceCatalog::devices()['Test/AllExposes'];
        $instanceID = IPS_CreateInstance(self::DEVICE_MODULE_ID);
        IPS_SetProperty($instanceID, 'MQTTBaseTopic', 'Z2M-SIM');
        IPS_SetProperty($instanceID, 'MQTTTopic', 'Test/AllExposes');
        IPS_ApplyChanges($instanceID);

        $device = IPS\InstanceManager::getInstanceInterface($instanceID);
        $device->BUFFER_MQTT_SUSPENDED = false;
        $mapExposes = new ReflectionMethod($device, 'mapExposesToVariables');
        $mapExposes->invoke($device, $catalogDevice['exposes']);

        $this->assertGreaterThan(20, \count(IPS_GetChildrenIDs($instanceID)));

        $device->ReceiveData(json_encode([
            'Topic'   => 'Z2M-SIM/Test/AllExposes',
            'Payload' => bin2hex(json_encode($catalogDevice['state'], JSON_THROW_ON_ERROR)),
        ], JSON_THROW_ON_ERROR));

        $brightnessID = IPS_GetObjectIDByIdent('brightness', $instanceID);
        $temperatureID = IPS_GetObjectIDByIdent('temperature', $instanceID);
        $weeklyScheduleID = IPS_GetObjectIDByIdent('weekly_schedule', $instanceID);

        $this->assertIsInt($brightnessID);
        $this->assertSame(70, GetValue($brightnessID));
        $this->assertIsInt($temperatureID);
        $this->assertSame(20.8, GetValue($temperatureID));
        $this->assertIsInt($weeklyScheduleID);
    }
}
