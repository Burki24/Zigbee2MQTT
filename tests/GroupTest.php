<?php

declare(strict_types=1);

include_once __DIR__ . '/DumpInclude.php';

class GroupTest extends DumpInclude
{
    private const DEVICE_MODULE_ID = '{E5BB36C6-A70B-EB23-3716-9151A09AC8A2}';
    private const GROUP_MODULE_ID = '{11BF3773-E940-469B-9DD7-FB9ACD7199A2}';

    public function testGroupAvailableDeviceListIsFilledFromExistingDeviceInstances(): void
    {
        $this->createConfiguredDevice('zigbee2mqtt', 'Flur/Beleuchtung/Deckenlicht');
        $this->createConfiguredDevice('zigbee2mqtt', 'Bad/Beleuchtung/Spiegel');
        $this->createConfiguredDevice('other_base', 'Darf/Nicht/Erscheinen');

        $groupID = $this->createConfiguredGroup('zigbee2mqtt', 'Flur/Beleuchtung/Deckenlicht/Gruppe');
        $form = json_decode(IPS_GetConfigurationForm($groupID), true);

        $list = $this->findFormItemByName($form, 'GroupAvailableDeviceList');
        $this->assertNotNull($list);
        $this->assertSame('List', $list['type']);

        $values = array_column($list['values'], 'topic');
        $this->assertContains('Flur/Beleuchtung/Deckenlicht', $values);
        $this->assertContains('Bad/Beleuchtung/Spiegel', $values);
        $this->assertNotContains('Darf/Nicht/Erscheinen', $values);

        $input = $this->findFormItemByName($form, 'GroupMemberDevice');
        $this->assertNotNull($input);
        $this->assertSame('ValidationTextBox', $input['type']);
    }

    public function testGroupAvailableDeviceListKeepsUnknownExistingMembersSelectable(): void
    {
        $groupID = $this->createConfiguredGroup('zigbee2mqtt', 'Flur/Beleuchtung/Deckenlicht/Gruppe');
        $this->writeStubAttributeArray($groupID, 'GroupMembers', [
            [
                'device'   => 'Extern/Nur/In/Gruppe',
                'endpoint' => '1'
            ]
        ]);

        $form = json_decode(IPS_GetConfigurationForm($groupID), true);
        $list = $this->findFormItemByName($form, 'GroupAvailableDeviceList');

        $values = array_column($list['values'], 'topic');
        $this->assertContains('Extern/Nur/In/Gruppe', $values);
    }

    private function createConfiguredDevice(string $baseTopic, string $mqttTopic): int
    {
        $instanceID = IPS_CreateInstance(self::DEVICE_MODULE_ID);
        IPS_SetName($instanceID, basename(str_replace('\\', '/', $mqttTopic)));
        IPS_SetConfiguration($instanceID, json_encode([
            'MQTTBaseTopic' => $baseTopic,
            'MQTTTopic'     => $mqttTopic,
            'IEEE'          => '0x' . substr(sha1($mqttTopic), 0, 16)
        ]));
        IPS_ApplyChanges($instanceID);

        return $instanceID;
    }

    private function createConfiguredGroup(string $baseTopic, string $mqttTopic): int
    {
        $instanceID = IPS_CreateInstance(self::GROUP_MODULE_ID);
        IPS_SetConfiguration($instanceID, json_encode([
            'MQTTBaseTopic' => $baseTopic,
            'MQTTTopic'     => $mqttTopic,
            'GroupId'       => 9
        ]));
        IPS_ApplyChanges($instanceID);

        return $instanceID;
    }

    private function findFormItemByName(array $node, string $name): ?array
    {
        if (($node['name'] ?? null) === $name) {
            return $node;
        }

        foreach ($node as $child) {
            if (!\is_array($child)) {
                continue;
            }

            $match = $this->findFormItemByName($child, $name);
            if ($match !== null) {
                return $match;
            }
        }

        return null;
    }

    private function writeStubAttributeArray(int $iid, string $name, array $value): void
    {
        $module = $this->getStubModule($iid);
        $reflection = new \ReflectionClass(IPSModule::class);
        $attributeProperty = $reflection->getProperty('attributes');
        $attributeProperty->setAccessible(true);

        $attributes = $attributeProperty->getValue($module);
        $this->assertArrayHasKey($name, $attributes, 'Attribute not found: ' . $name);
        $attributes[$name]['Current'] = json_encode($value);
        $attributeProperty->setValue($module, $attributes);
    }

    private function getStubModule(int $iid): object
    {
        $interface = IPS\InstanceManager::getInstanceInterface($iid);
        $reflection = new \ReflectionClass(IPSModuleStrict::class);
        $moduleProperty = $reflection->getProperty('module');
        $moduleProperty->setAccessible(true);
        return $moduleProperty->getValue($interface);
    }
}
