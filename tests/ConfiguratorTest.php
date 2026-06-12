<?php

declare(strict_types=1);

include_once __DIR__ . '/DumpInclude.php';

/**
 * Tests splitter-safe configurator instance assignment and repair.
 */
class ConfiguratorTest extends DumpInclude
{
    private const CONFIGURATOR_MODULE_ID = '{D30BADA8-F261-4D9F-89A9-2E9961AF021F}';
    private const DEVICE_MODULE_ID = '{E5BB36C6-A70B-EB23-3716-9151A09AC8A2}';
    private const VIRTUAL_IO_MODULE_ID = '{6179ED6A-FC31-413C-BB8E-1204150CF376}';

    public function testConfiguratorSeparatesAndRepairsWrongSplitterInstances(): void
    {
        $splitterID = IPS_CreateInstance(self::VIRTUAL_IO_MODULE_ID);
        $otherSplitterID = IPS_CreateInstance(self::VIRTUAL_IO_MODULE_ID);
        $configuratorID = IPS_CreateInstance(self::CONFIGURATOR_MODULE_ID);
        IPS_SetConfiguration($configuratorID, json_encode(['MQTTBaseTopic' => 'zigbee2mqtt']));
        IPS_ConnectInstance($configuratorID, $splitterID);
        IPS_ApplyChanges($configuratorID);

        $correctDeviceID = $this->CreateConfiguredDevice('zigbee2mqtt', 'Wohnzimmer/Licht', $splitterID);
        $wrongDeviceID = $this->CreateConfiguredDevice('zigbee2mqtt', 'Kueche/Licht', $otherSplitterID);
        $otherWrongDeviceID = $this->CreateConfiguredDevice('zigbee2mqtt', 'Flur/Licht', $otherSplitterID);
        $otherBaseDeviceID = $this->CreateConfiguredDevice('other_base', 'Nicht/Anzeigen', $otherSplitterID);

        $configurator = IPS\InstanceManager::getInstanceInterface($configuratorID);
        $matchingMethod = new ReflectionMethod($configurator, 'GetIPSInstancesByBaseTopic');
        $matchingMethod->setAccessible(true);
        $wrongMethod = new ReflectionMethod($configurator, 'GetIPSInstancesWithWrongConnectionByBaseTopic');
        $wrongMethod->setAccessible(true);

        $this->assertSame(
            [$correctDeviceID => 'Wohnzimmer/Licht'],
            $matchingMethod->invoke($configurator, self::DEVICE_MODULE_ID, 'zigbee2mqtt')
        );
        $this->assertSame(
            [
                $wrongDeviceID      => 'Kueche/Licht',
                $otherWrongDeviceID => 'Flur/Licht'
            ],
            $wrongMethod->invoke($configurator, self::DEVICE_MODULE_ID, 'zigbee2mqtt')
        );
        $this->assertNotContains($otherBaseDeviceID, array_keys($matchingMethod->invoke($configurator, self::DEVICE_MODULE_ID, 'zigbee2mqtt')));

        $configurator->RequestAction('RepairWrongConnection', $otherBaseDeviceID);
        $this->assertSame($otherSplitterID, IPS_GetInstance($otherBaseDeviceID)['ConnectionID']);

        $configurator->RequestAction('RepairWrongConnection', $wrongDeviceID);

        $this->assertSame($splitterID, IPS_GetInstance($wrongDeviceID)['ConnectionID']);
        $this->assertSame($otherSplitterID, IPS_GetInstance($otherWrongDeviceID)['ConnectionID']);
    }

    public function testRepairPopupUsesExplicitPerInstanceAction(): void
    {
        $form = json_decode(file_get_contents(__DIR__ . '/../Configurator/form.json'), true);
        $repairPopup = $form['actions'][3]['popup'];
        $actionColumn = array_column($repairPopup['items'][1]['columns'], null, 'name')['action'];

        $this->assertArrayNotHasKey('buttons', $repairPopup);
        $this->assertStringContainsString("'RepairWrongConnection'", $actionColumn['onClick']);
    }

    private function CreateConfiguredDevice(string $BaseTopic, string $Topic, int $SplitterID): int
    {
        $instanceID = IPS_CreateInstance(self::DEVICE_MODULE_ID);
        IPS_SetConfiguration($instanceID, json_encode([
            'MQTTBaseTopic' => $BaseTopic,
            'MQTTTopic'     => $Topic,
            'IEEE'          => '0x' . substr(sha1($Topic), 0, 16)
        ]));
        IPS_ConnectInstance($instanceID, $SplitterID);
        IPS_ApplyChanges($instanceID);

        return $instanceID;
    }
}
