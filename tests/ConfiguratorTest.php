<?php

declare(strict_types=1);

include_once __DIR__ . '/DumpInclude.php';

/**
 * Prüft die splittergebundene Zuordnung von Configurator-Instanzen.
 */
class ConfiguratorTest extends DumpInclude
{
    private const CONFIGURATOR_MODULE_ID = '{D30BADA8-F261-4D9F-89A9-2E9961AF021F}';
    private const DEVICE_MODULE_ID = '{E5BB36C6-A70B-EB23-3716-9151A09AC8A2}';
    private const VIRTUAL_IO_MODULE_ID = '{6179ED6A-FC31-413C-BB8E-1204150CF376}';

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
        $matchingMethod->setAccessible(true);

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
    }

    public function testConfiguratorUsesTolerantQuietAvailabilityCheck(): void
    {
        $source = file_get_contents(__DIR__ . '/../Configurator/module.php');

        $this->assertStringContainsString(
            '$this->SendDataQuiet($Topic, $Payload, self::TIMEOUT_CONFIGURATOR_QUICK_REQUEST)',
            $source
        );
    }

    public function testMissingBridgeUsesRegularConfiguratorCreateDescriptor(): void
    {
        $configuratorID = IPS_CreateInstance(self::CONFIGURATOR_MODULE_ID);
        $configurator = IPS\InstanceManager::getInstanceInterface($configuratorID);
        $method = new ReflectionMethod($configurator, 'BuildBridgeCreateDescriptor');

        $descriptor = $method->invoke($configurator, 'zigbee2mqtt', ['Zigbee']);

        $this->assertSame('{00160D82-9E2F-D1BD-6D0B-952F945332C5}', $descriptor['moduleID']);
        $this->assertSame(['Zigbee'], $descriptor['location']);
        $this->assertSame(['MQTTBaseTopic' => 'zigbee2mqtt'], $descriptor['configuration']);

        $form = json_decode(file_get_contents(__DIR__ . '/../Configurator/form.json'), true);
        $errorPopup = $form['actions'][2]['popup'];
        $this->assertCount(3, $errorPopup['items']);

        $source = file_get_contents(__DIR__ . '/../Configurator/module.php');
        $this->assertStringNotContainsString('IPS_CreateInstance(', $source);
        $this->assertStringNotContainsString('IPS_SetProperty(', $source);
        $this->assertStringNotContainsString('IPS_ApplyChanges(', $source);
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
