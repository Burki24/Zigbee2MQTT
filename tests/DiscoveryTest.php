<?php

declare(strict_types=1);

include_once __DIR__ . '/DumpInclude.php';

if (!\function_exists('MQTT_GetRetainedMessageTopicList')) {
    function MQTT_GetRetainedMessageTopicList(int $splitterID): array
    {
        return array_keys($GLOBALS['MQTT_RETAINED_MESSAGES'][$splitterID] ?? []);
    }
}

if (!\function_exists('MQTT_GetRetainedMessage')) {
    function MQTT_GetRetainedMessage(int $splitterID, string $topic): array
    {
        return ['Payload' => $GLOBALS['MQTT_RETAINED_MESSAGES'][$splitterID][$topic] ?? ''];
    }
}

/**
 * Tests discovery of retained Zigbee2MQTT bridge topics.
 */
class DiscoveryTest extends DumpInclude
{
    protected function tearDown(): void
    {
        unset($GLOBALS['MQTT_RETAINED_MESSAGES'][12345]);
    }

    public function testRetainedBridgeTopicDiscoveryKeepsAllOnlineBases(): void
    {
        $GLOBALS['MQTT_RETAINED_MESSAGES'][12345] = [
            'zigbee2mqtt/bridge/state'       => '{"state":"online"}',
            'second-z2m/bridge/state'        => '{"state":"online"}',
            'offline-z2m/bridge/state'       => '{"state":"offline"}',
            'zigbee2mqtt/bridge/devices'     => '[]',
            'zigbee2mqtt/device/state'       => '{"state":"online"}'
        ];

        $discovery = new Zigbee2MQTTDiscovery(990004);
        $reflection = new \ReflectionMethod(Zigbee2MQTTDiscovery::class, 'FindRetainedBridgeTopics');
        $reflection->setAccessible(true);

        $this->assertSame(
            ['zigbee2mqtt', 'second-z2m'],
            $reflection->invoke($discovery, 12345)
        );
    }
}
