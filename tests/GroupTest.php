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
        $this->assertSame('1', $list['values'][0]['endpoints']);
    }

    public function testSelectingAvailableDevicePopulatesEndpointOptionsFromExtension(): void
    {
        $group = $this->createGroupFormTestDouble([
            [
                'friendly_name' => 'Flur/Beleuchtung/Treppe',
                'model'         => '929001821618',
                'type'          => 'Router',
                'endpoints'     => [
                    '11'  => ['id' => '11'],
                    '242' => ['id' => '242']
                ]
            ]
        ]);
        $form = json_decode($group->GetConfigurationForm(), true);
        $list = $this->findFormItemByName($form, 'GroupAvailableDeviceList');

        $this->assertSame('11, 242', $list['values'][0]['endpoints']);

        $group->RequestAction('SelectGroupMemberDevice', json_encode([
            'device'   => 'Flur/Beleuchtung/Treppe',
            'endpoint' => ''
        ]));

        $this->assertSame('Flur/Beleuchtung/Treppe', $group->updatedFields['GroupMemberDevice']['value']);
        $endpointOptions = json_decode($group->updatedFields['GroupMemberEndpoint']['options'], true);
        $this->assertSame(['11', '242'], array_column($endpointOptions, 'value'));
        $this->assertSame('11', $group->updatedFields['GroupMemberEndpoint']['value']);
    }

    public function testOfflineGroupMemberRequestShowsPopupInsteadOfNotice(): void
    {
        $group = $this->createFailingGroupBridgeTestDouble(
            "Failed to add to group (ZCL command 0x142d41fffe507d64/1 genGroups.add failed (Delivery failed for '10979'.))"
        );

        $group->RequestAction('AddGroupMember', json_encode([
            'device'   => 'Flur/Beleuchtung/Treppe',
            'endpoint' => '11'
        ]));

        $this->assertSame(true, $group->updatedFields['GroupMemberRequestError']['visible']);
        $this->assertSame('Device offline', $group->updatedFields['GroupMemberRequestErrorTitle']['caption']);
        $this->assertStringContainsString('did not respond', $group->updatedFields['GroupMemberRequestErrorText']['caption']);
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

    private function createGroupFormTestDouble(array $devices): Zigbee2MQTTGroup
    {
        $group = new class(990001, $devices) extends Zigbee2MQTTGroup {
            public array $updatedFields = [];

            public function __construct(int $InstanceID, private array $devices)
            {
                parent::__construct($InstanceID);
            }

            protected function SendData(string $Topic, array $Payload = [], int $Timeout = 5000): array|bool
            {
                if ($Topic === self::SYMCON_EXTENSION_LIST_REQUEST . 'getDevices') {
                    return ['list' => $this->devices];
                }

                return false;
            }

            protected function ReadPropertyString(string $Name): string
            {
                return match ($Name) {
                    self::MQTT_BASE_TOPIC => 'zigbee2mqtt',
                    self::MQTT_TOPIC      => 'Flur/Beleuchtung/Deckenlicht/Gruppe',
                    default               => parent::ReadPropertyString($Name)
                };
            }

            protected function HasActiveParent(): bool
            {
                return true;
            }

            protected function GetStatus(): int
            {
                return IS_ACTIVE;
            }

            protected function UpdateFormField(string $Field, string $Parameter, mixed $Value): bool
            {
                $this->updatedFields[$Field][$Parameter] = $Value;
                return true;
            }

            protected function SendDebug(string $Message, string $Data, int $Format): bool
            {
                return true;
            }
        };
        $group->Create();

        return $group;
    }

    private function createFailingGroupBridgeTestDouble(string $errorMessage): Zigbee2MQTTGroup
    {
        $group = new class(990002, $errorMessage) extends Zigbee2MQTTGroup {
            public array $updatedFields = [];

            public function __construct(int $InstanceID, private string $errorMessage)
            {
                parent::__construct($InstanceID);
            }

            protected function CallMatchingBridgeFunction(string $function, array $arguments): mixed
            {
                trigger_error($this->errorMessage, E_USER_NOTICE);
                return false;
            }

            protected function ReadPropertyString(string $Name): string
            {
                return match ($Name) {
                    self::MQTT_BASE_TOPIC => 'zigbee2mqtt',
                    self::MQTT_TOPIC      => 'Flur/Beleuchtung/Deckenlicht/Gruppe',
                    default               => parent::ReadPropertyString($Name)
                };
            }

            protected function UpdateFormField(string $Field, string $Parameter, mixed $Value): bool
            {
                $this->updatedFields[$Field][$Parameter] = $Value;
                return true;
            }

            protected function SendDebug(string $Message, string $Data, int $Format): bool
            {
                return true;
            }
        };
        $group->Create();

        return $group;
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
