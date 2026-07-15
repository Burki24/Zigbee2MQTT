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
 * Prüft die Erkennung von Zigbee2MQTT-Bridges anhand gespeicherter MQTT-Topics.
 */
class DiscoveryTest extends DumpInclude
{
    protected function tearDown(): void
    {
        unset($GLOBALS['MQTT_RETAINED_MESSAGES']);
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

    public function testConfigurationFormUsesCacheAndSchedulesDiscoveryRefresh(): void
    {
        $discovery = new class(990007) extends Zigbee2MQTTDiscovery {
            public int $reloadCount = 0;
            public int $scanCount = 0;

            public function getRefreshTimerForTest(): int
            {
                return $this->GetTimerInterval('DiscoveryRefresh');
            }

            public function getDiscoveryCacheForTest(): string
            {
                return $this->ReadAttributeString('DiscoveryCache');
            }

            protected function ReloadForm(): bool
            {
                ++$this->reloadCount;
                return true;
            }

            protected function ScanMqttServers(array $fallbackTopics = []): ?array
            {
                ++$this->scanCount;
                return null;
            }

            protected function getTime(): int
            {
                return time();
            }
        };
        $discovery->Create();

        $form = json_decode($discovery->GetConfigurationForm(), true);

        $this->assertIsArray($form);
        $this->assertSame('', $discovery->getDiscoveryCacheForTest(), 'Opening the form must not run discovery synchronously.');
        $this->assertSame(0, $discovery->scanCount);
        $this->assertGreaterThan(0, $discovery->getRefreshTimerForTest());

        $discovery->RequestAction('RefreshDiscoveryCache', true);

        $cache = json_decode($discovery->getDiscoveryCacheForTest(), true);
        $this->assertNull($cache['topics']);
        $this->assertGreaterThan(0, $cache['timestamp']);
        $this->assertLessThanOrEqual(0, $discovery->getRefreshTimerForTest());
        $this->assertSame(1, $discovery->reloadCount);
        $this->assertSame(1, $discovery->scanCount);

        $discovery->GetConfigurationForm();
        $this->assertLessThanOrEqual(0, $discovery->getRefreshTimerForTest(), 'A fresh cache must not schedule another discovery scan.');

        $discovery->RequestAction('RefreshDiscovery', true);
        $this->assertGreaterThan(0, $discovery->getRefreshTimerForTest());
        $this->assertSame(1, $discovery->scanCount, 'The manual refresh button must only schedule the asynchronous scan.');

        $discovery->RequestAction('RefreshDiscoveryCache', true);
        $this->assertSame(2, $discovery->scanCount);
    }

    public function testManualBrokerDebugDoesNotContainCredentials(): void
    {
        $instanceID = 990005;
        $discovery = new Zigbee2MQTTDiscovery($instanceID);

        $discovery->RequestAction('CheckMQTTBroker', json_encode([
            'Url'          => 'invalid://url-debug-user:url-debug-password@broker.test?access_token=url-debug-token',
            'UserName'     => 'manual-debug-user',
            'Password'     => 'manual-debug-password',
            'ClientSecret' => 'manual-client-secret',
            'ApiKey'       => 'manual-api-key'
        ]));

        $debug = json_encode(IPS\DebugServer::getDebugMessages($instanceID));
        $this->assertIsString($debug);
        $this->assertStringNotContainsString('manual-debug-user', $debug);
        $this->assertStringNotContainsString('manual-debug-password', $debug);
        $this->assertStringNotContainsString('manual-client-secret', $debug);
        $this->assertStringNotContainsString('manual-api-key', $debug);
        $this->assertStringNotContainsString('url-debug-user', $debug);
        $this->assertStringNotContainsString('url-debug-password', $debug);
        $this->assertStringNotContainsString('url-debug-token', $debug);
        $this->assertStringContainsString('[redacted]', $debug);
    }

    public function testFormDebugRedactsNestedBrokerCredentialsWithoutChangingForm(): void
    {
        $instanceID = 990006;
        $discovery = new Zigbee2MQTTDiscovery($instanceID);
        $discovery->ManuelTopics = ['zigbee2mqtt'];
        $discovery->ManuelBrokerConfig = [
            'Host'         => 'mqtt.example.test',
            'Port'         => 1883,
            'UseSSL'       => false,
            'UserName'     => 'form-debug-user',
            'Password'     => 'form-debug-password',
            'ClientSecret' => 'nested-debug-secret'
        ];

        $form = $discovery->GetConfigurationForm();
        $this->assertStringContainsString('form-debug-user', $form);
        $this->assertStringContainsString('form-debug-password', $form);

        $debug = json_encode(IPS\DebugServer::getDebugMessages($instanceID));
        $this->assertIsString($debug);
        $this->assertStringNotContainsString('form-debug-user', $debug);
        $this->assertStringNotContainsString('form-debug-password', $debug);
        $this->assertStringNotContainsString('nested-debug-secret', $debug);
        $this->assertStringContainsString('[redacted]', $debug);
    }
}
