<?php

declare(strict_types=1);

include_once __DIR__ . '/DumpInclude.php';

/**
 * Prüft die splittergebundene Zuordnung von Configurator-Instanzen.
 */
class ConfiguratorTest extends DumpInclude
{
    private const CONFIGURATOR_MODULE_ID = '{D30BADA8-F261-4D9F-89A9-2E9961AF021F}';
    private const BRIDGE_MODULE_ID = '{00160D82-9E2F-D1BD-6D0B-952F945332C5}';
    private const DEVICE_MODULE_ID = '{E5BB36C6-A70B-EB23-3716-9151A09AC8A2}';
    private const GROUP_MODULE_ID = '{11BF3773-E940-469B-9DD7-FB9ACD7199A2}';
    private const VIRTUAL_IO_MODULE_ID = '{6179ED6A-FC31-413C-BB8E-1204150CF376}';

    public function testReceiveDataIgnoresIncompleteMqttFrames(): void
    {
        $configuratorID = IPS_CreateInstance(self::CONFIGURATOR_MODULE_ID);
        IPS_SetConfiguration($configuratorID, json_encode(['MQTTBaseTopic' => 'zigbee2mqtt']));
        IPS_ApplyChanges($configuratorID);
        $configurator = IPS\InstanceManager::getInstanceInterface($configuratorID);

        $this->assertSame('', $configurator->ReceiveData(json_encode(['Topic' => 'zigbee2mqtt/bridge/response/options'])));
        $this->assertSame('', $configurator->ReceiveData(json_encode([
            'Topic'   => 'zigbee2mqtt/bridge/response/options',
            'Payload' => []
        ])));
        $this->assertSame('', $configurator->ReceiveData(json_encode([
            'Topic'   => [],
            'Payload' => bin2hex('{}')
        ])));
    }

    public function testConfiguratorOnlyReturnsInstancesFromItsOwnSplitter(): void
    {
        $splitterID = IPS_CreateInstance(self::VIRTUAL_IO_MODULE_ID);
        $otherSplitterID = IPS_CreateInstance(self::VIRTUAL_IO_MODULE_ID);
        $configuratorID = IPS_CreateInstance(self::CONFIGURATOR_MODULE_ID);
        IPS_SetConfiguration($configuratorID, json_encode(['MQTTBaseTopic' => 'zigbee2mqtt']));
        IPS_ConnectInstance($configuratorID, $splitterID);
        IPS_ApplyChanges($configuratorID);

        $correctDeviceID = $this->CreateConfiguredDevice('zigbee2mqtt', 'Wohnzimmer/Licht', $splitterID);
        $this->CreateConfiguredDevice('zigbee2mqtt', 'Kueche/Licht', $otherSplitterID);
        $this->CreateConfiguredDevice('zigbee2mqtt', 'Flur/Licht', $otherSplitterID);
        $otherBaseDeviceID = $this->CreateConfiguredDevice('other_base', 'Nicht/Anzeigen', $otherSplitterID);

        $configurator = IPS\InstanceManager::getInstanceInterface($configuratorID);
        $matchingMethod = new ReflectionMethod($configurator, 'GetIPSInstancesByBaseTopic');

        $this->assertSame(
            [$correctDeviceID => 'Wohnzimmer/Licht'],
            $matchingMethod->invoke($configurator, self::DEVICE_MODULE_ID, 'zigbee2mqtt')
        );
        $this->assertNotContains($otherBaseDeviceID, array_keys($matchingMethod->invoke($configurator, self::DEVICE_MODULE_ID, 'zigbee2mqtt')));
    }

    public function testConfiguratorDoesNotExposeOrManipulateForeignSplitterInstances(): void
    {
        $form = json_decode(file_get_contents(__DIR__ . '/../Configurator/form.json'), true);
        $source = file_get_contents(__DIR__ . '/../Configurator/module.php');

        $this->assertNotContains('WrongConnectedInstancesPopup', array_column($form['actions'], 'name'));
        $this->assertStringNotContainsString('RepairWrongConnection', $source);
        $this->assertStringNotContainsString('IPS_ConnectInstance(', $source);
        $this->assertStringNotContainsString('IPS_DisconnectInstance(', $source);
        $this->assertStringNotContainsString('IPS_CreateInstance(', $source);
        $this->assertStringNotContainsString('IPS_SetProperty(', $source);
        $this->assertStringNotContainsString('IPS_ApplyChanges(', $source);
    }

    public function testConfiguratorUsesTolerantQuietAvailabilityCheck(): void
    {
        $source = file_get_contents(__DIR__ . '/../Configurator/module.php');

        $this->assertStringContainsString(
            '$this->SendDataQuiet($Topic, $Payload, self::TIMEOUT_CONFIGURATOR_QUICK_REQUEST)',
            $source
        );
    }

    public function testConfiguratorRetainsAssignedInstancesAndNeverShowsBridgeOrCoordinator(): void
    {
        $splitterID = IPS_CreateInstance(self::VIRTUAL_IO_MODULE_ID);
        $otherSplitterID = IPS_CreateInstance(self::VIRTUAL_IO_MODULE_ID);
        $configuratorID = IPS_CreateInstance(self::CONFIGURATOR_MODULE_ID);
        IPS_SetProperty($configuratorID, 'MQTTBaseTopic', 'zigbee2mqtt');
        IPS_ConnectInstance($configuratorID, $splitterID);
        IPS_ApplyChanges($configuratorID);

        $deviceID = $this->CreateConfiguredDevice('zigbee2mqtt', 'Wohnzimmer/Licht', $splitterID);
        $this->CreateConfiguredDevice('zigbee2mqtt', 'Wohnzimmer/Licht', $otherSplitterID);
        $this->CreateConfiguredDevice('other_base', 'Flur/Licht', $splitterID);
        $groupIDs = [];
        foreach ([$splitterID, $otherSplitterID] as $connectionID) {
            $groupID = IPS_CreateInstance(self::GROUP_MODULE_ID);
            IPS_ConnectInstance($groupID, $connectionID);
            IPS_SetConfiguration($groupID, json_encode(['MQTTBaseTopic' => 'zigbee2mqtt', 'MQTTTopic' => 'Wohnzimmer/Gruppe', 'GroupId' => 14]));
            IPS_ApplyChanges($groupID);
            $groupIDs[] = $groupID;
        }
        $bridgeID = IPS_CreateInstance(self::BRIDGE_MODULE_ID);
        IPS_SetProperty($bridgeID, 'MQTTBaseTopic', 'zigbee2mqtt');
        IPS_ApplyChanges($bridgeID);
        IPS_ConnectInstance($bridgeID, $splitterID);
        $configurator = new class($configuratorID) extends Zigbee2MQTTConfigurator {
            public array $devices = [];

            protected function HasActiveParent(): bool
            {
                return true;
            }

            protected function ReadPropertyString(string $Name): string
            {
                return IPS_GetProperty($this->InstanceID, $Name);
            }

            protected function SendData(string $Topic, array $Payload = [], int $Timeout = 5000): array|bool
            {
                return match ($Topic) {
                    '/bridge/request/options'                        => ['status' => 'ok'],
                    '/SymconExtension/lists/request/getDevicesLight' => ['list' => $this->devices],
                    '/SymconExtension/lists/request/getGroups'       => ['list' => []],
                    default                                          => false
                };
            }
        };
        $coordinator = ['friendly_name' => 'Coordinator', 'ieeeAddr' => '0x0000000000000001', 'networkAddress' => 0, 'type' => 'Coordinator'];

        foreach ([[], [$coordinator]] as $devices) {
            $configurator->devices = $devices;
            $json = $configurator->GetConfigurationForm();
            $form = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            $deviceRows = $form['actions'][0]['items'][0]['values'];
            $groupRows = $form['actions'][1]['items'][0]['values'];
            $this->assertSame([$deviceID], array_values(array_filter(array_column($deviceRows, 'instanceID'))));
            $this->assertSame([$groupIDs[0]], array_values(array_filter(array_column($groupRows, 'instanceID'))));
            $this->assertNotContains('Coordinator', array_column($deviceRows, 'topic'));
            $this->assertStringNotContainsString(self::BRIDGE_MODULE_ID, $json);
            $this->assertStringNotContainsString('"objectID":' . $bridgeID, $json);
        }
    }

    public function testDiscoveredExistingInstancesKeepCreateDescriptors(): void
    {
        $source = file_get_contents(__DIR__ . '/../Configurator/module.php');

        $this->assertDoesNotMatchRegularExpression(
            '/if\s*\(\!\$instanceID\)\s*\{\s*\$value\[[\'"]create[\'"]\]/',
            $source
        );
        $this->assertSame(
            2,
            substr_count($source, 'The create descriptor also marks an existing instance as still discovered by the configurator.')
        );
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
