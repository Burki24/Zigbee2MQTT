<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/autoload.php';
require_once __DIR__ . '/../libs/MQTTHelper.php';

use PHPUnit\Framework\TestCase;

/**
 * Tests MQTT request transaction handling.
 */
class MQTTHelperTest extends TestCase
{
    public function testAddTransactionUsesOnlyFreeIdsAndPreservesPendingEntries(): void
    {
        $helper = new class() {
            use \Zigbee2MQTT\SendData {
                AddTransaction as public createTransaction;
            }

            public array $TransactionData = [];
            public array $Multi_TransactionData = [];
            public int $lockCount = 0;
            public int $unlockCount = 0;

            public function lock(string $name): bool
            {
                $this->lockCount++;
                return true;
            }

            public function unlock(string $name): void
            {
                $this->unlockCount++;
            }

            public function Translate(string $text): string
            {
                return $text;
            }
        };
        $helper->Multi_TransactionData = array_fill_keys(range(1, 10000), []);
        unset($helper->Multi_TransactionData[7777]);
        $payload = [];

        $transactionId = $helper->createTransaction($payload, '/bridge/request/health_check');

        $this->assertSame(7777, $transactionId);
        $this->assertSame(7777, $payload['transaction']);
        $this->assertCount(10000, $helper->Multi_TransactionData);
        $this->assertArrayHasKey(1, $helper->Multi_TransactionData);
        $this->assertSame(1, $helper->lockCount);
        $this->assertSame(1, $helper->unlockCount);
    }

    public function testTransactionLockIsReleasedWhenNoIdIsAvailable(): void
    {
        $helper = new class() {
            use \Zigbee2MQTT\SendData {
                AddTransaction as public createTransaction;
            }

            public array $TransactionData = [];
            public array $Multi_TransactionData = [];
            public int $unlockCount = 0;

            public function lock(string $name): bool
            {
                return true;
            }

            public function unlock(string $name): void
            {
                $this->unlockCount++;
            }

            public function Translate(string $text): string
            {
                return $text;
            }
        };
        $helper->Multi_TransactionData = array_fill_keys(range(1, 10000), []);
        $payload = [];

        try {
            $helper->createTransaction($payload, '/bridge/request/health_check');
            $this->fail('A full transaction ID range must reject another transaction.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('No transaction IDs available', $exception->getMessage());
        }

        $this->assertSame(1, $helper->unlockCount);
        $this->assertArrayNotHasKey('transaction', $payload);
    }

    public function testTransactionLockIsReleasedWhenBufferWriteFails(): void
    {
        $helper = new class() {
            use \Zigbee2MQTT\SendData {
                AddTransaction as public createTransaction;
            }

            public int $unlockCount = 0;

            public function __get(string $name): array
            {
                return [];
            }

            public function __set(string $name, mixed $value): void
            {
                if ($name === 'Multi_TransactionData') {
                    throw new \RuntimeException('Buffer write failed');
                }
            }

            public function lock(string $name): bool
            {
                return true;
            }

            public function unlock(string $name): void
            {
                $this->unlockCount++;
            }

            public function Translate(string $text): string
            {
                return $text;
            }
        };
        $payload = [];

        try {
            $helper->createTransaction($payload, '/bridge/request/health_check');
            $this->fail('A failed buffer write must abort the transaction.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Buffer write failed', $exception->getMessage());
        }

        $this->assertSame(1, $helper->unlockCount);
        $this->assertArrayNotHasKey('transaction', $payload);
    }

    public function testInvalidTransactionIdIsRejectedBeforeLockingBuffer(): void
    {
        $helper = new class() {
            use \Zigbee2MQTT\SendData {
                UpdateTransaction as public completeTransaction;
            }

            public array $TransactionData = [];
            public array $Multi_TransactionData = [];
            public int $lockCount = 0;

            public function lock(string $name): bool
            {
                $this->lockCount++;
                return true;
            }

            public function unlock(string $name): void
            {
            }

            public function SendDebug(string $message, mixed $data, int $format): void
            {
            }

            public function Translate(string $text): string
            {
                return $text;
            }
        };

        $this->assertFalse($helper->completeTransaction(['transaction' => ['invalid']]));
        $this->assertFalse($helper->completeTransaction(['transaction' => 10001]));
        $this->assertSame(0, $helper->lockCount);
    }

    public function testExtensionListResponseWithoutTransactionIsMatchedByResponseTopic(): void
    {
        $helper = new class() {
            use \Zigbee2MQTT\SendData {
                SendData as public sendMqttData;
                UpdateTransactionByResponseTopic as public completeByResponseTopic;
            }

            public array $TransactionData = [];
            public array $Multi_TransactionData = [];

            public function lock(string $name): bool
            {
                return true;
            }

            public function unlock(string $name): void
            {
            }

            public function ReadPropertyString(string $name): string
            {
                return 'zigbee2mqtt';
            }

            public function SendDebug(string $message, mixed $data, int $format): void
            {
            }

            public function SendDataToParent(string $data): void
            {
                $this->completeByResponseTopic('/SymconExtension/lists/response/getDevices', [
                    'list' => [
                        ['friendly_name' => 'Test device']
                    ]
                ]);
            }

            public function Translate(string $text): string
            {
                return $text;
            }
        };

        $result = $helper->sendMqttData('/SymconExtension/lists/request/getDevices');

        $this->assertIsArray($result);
        $this->assertSame('Test device', $result['list'][0]['friendly_name']);
    }

    public function testLargeDebugOutputIsTruncated(): void
    {
        $helper = new class() {
            use \Zigbee2MQTT\SendData {
                SendLimitedDebug as public limitedDebug;
            }

            public array $debugMessages = [];

            public function SendDebug(string $message, mixed $data, int $format): void
            {
                $this->debugMessages[$message][] = (string) $data;
            }
        };

        $helper->limitedDebug('LargePayload', str_repeat('A', 200), 0, 50);

        $this->assertArrayHasKey('LargePayload', $helper->debugMessages);
        $this->assertStringContainsString('truncated, original length 200 bytes', $helper->debugMessages['LargePayload'][0]);
        $this->assertLessThan(120, \strlen($helper->debugMessages['LargePayload'][0]));
    }

    public function testSensitiveDataIsSentButRedactedFromDebugOutput(): void
    {
        $helper = new class() {
            use \Zigbee2MQTT\SendData {
                SendSensitiveData as public sendSensitiveMqttData;
            }

            public array $debugMessages = [];
            public array $sentRequests = [];

            public function ReadPropertyString(string $name): string
            {
                return 'zigbee2mqtt';
            }

            public function SendDebug(string $message, mixed $data, int $format): void
            {
                $this->debugMessages[$message][] = (string) $data;
            }

            public function SendDataToParent(string $data): void
            {
                $this->sentRequests[] = json_decode($data, true);
            }
        };

        $this->assertTrue($helper->sendSensitiveMqttData('/bridge/request/install_code/add', ['value' => 'SECRET'], 0));
        $requestPayload = json_decode(hex2bin($helper->sentRequests[0]['Payload']) ?: '', true);
        $this->assertSame(['value' => 'SECRET'], $requestPayload);
        $this->assertSame('[redacted]', $helper->debugMessages['SendSensitiveData:Payload'][0]);
        $this->assertStringNotContainsString('SECRET', json_encode($helper->debugMessages));
    }

    public function testDeviceCommandCombinesTopicsAndPreservesOffPayload(): void
    {
        $helper = new class() {
            use \Zigbee2MQTT\SendData;

            public array $sentRequests = [];

            public function ReadPropertyString(string $name): string
            {
                return match ($name) {
                    'MQTTBaseTopic' => 'Z2M-2',
                    'MQTTTopic'     => 'Wohnbereich/Beleuchtung/Wohnzimmer/Couchlicht',
                    default         => ''
                };
            }

            public function SendDebug(string $message, mixed $data, int $format): void
            {
            }

            public function SendDataToParent(string $data): void
            {
                $this->sentRequests[] = json_decode($data, true);
            }
        };

        $this->assertTrue($helper->Command('set', '{"state":"OFF"}'));
        $this->assertSame('Z2M-2/Wohnbereich/Beleuchtung/Wohnzimmer/Couchlicht/set', $helper->sentRequests[0]['Topic']);
        $this->assertSame(['state' => 'OFF'], json_decode(hex2bin($helper->sentRequests[0]['Payload']) ?: '', true));
    }

    public function testUnavailableInstanceInterfaceStopsBeforeSending(): void
    {
        $helper = new class() {
            use \Zigbee2MQTT\SendData {
                SendData as public sendMqttData;
            }

            public bool $sent = false;

            public function ReadPropertyString(string $name): string
            {
                trigger_error('InstanceInterface is not available', E_USER_WARNING);
                return '';
            }

            public function SendDebug(string $message, mixed $data, int $format): void
            {
            }

            public function SendDataToParent(string $data): void
            {
                $this->sent = true;
            }
        };

        $this->assertFalse($helper->sendMqttData('/bridge/request/health_check', [], 0));
        $this->assertFalse($helper->sent);
    }

    public function testUnavailableParentInterfaceStopsWithoutWarning(): void
    {
        $helper = new class() {
            use \Zigbee2MQTT\SendData {
                SendData as public sendMqttData;
            }

            public function ReadPropertyString(string $name): string
            {
                return 'zigbee2mqtt';
            }

            public function SendDebug(string $message, mixed $data, int $format): void
            {
            }

            public function SendDataToParent(string $data): void
            {
                trigger_error('InstanceInterface is not available', E_USER_WARNING);
            }
        };

        $this->assertFalse($helper->sendMqttData('/bridge/request/health_check', [], 0));
    }

    public function testResponseWithoutTransactionIsMatchedByResponseTopic(): void
    {
        $helper = new class() {
            use \Zigbee2MQTT\SendData {
                SendData as public sendMqttData;
                UpdateTransactionByResponseTopic as public completeByResponseTopic;
            }

            public array $TransactionData = [];
            public array $Multi_TransactionData = [];
            public array $sentRequests = [];
            public array $debugMessages = [];

            public function lock(string $name): bool
            {
                return true;
            }

            public function unlock(string $name): void
            {
            }

            public function ReadPropertyString(string $name): string
            {
                return 'zigbee2mqtt';
            }

            public function SendDebug(string $message, mixed $data, int $format): void
            {
                $this->debugMessages[$message][] = (string) $data;
            }

            public function SendDataToParent(string $data): void
            {
                $this->sentRequests[] = json_decode($data, true);
                $this->completeByResponseTopic('/bridge/response/backup', [
                    'status' => 'ok',
                    'data'   => [
                        'zip' => 'BACKUP'
                    ]
                ]);
            }

            public function Translate(string $text): string
            {
                return $text;
            }
        };

        $result = $helper->sendMqttData('/bridge/request/backup', [], 300000);
        $this->assertIsArray($result);
        $this->assertSame('ok', $result['status']);
        $this->assertArrayHasKey('zip_file', $result['data']);
        $this->assertArrayNotHasKey('zip', $result['data']);

        $requestPayload = json_decode(hex2bin($helper->sentRequests[0]['Payload']) ?: '', true);
        $this->assertIsArray($requestPayload);
        $this->assertArrayHasKey('transaction', $requestPayload);
        $this->assertSame('300000', $helper->debugMessages['SendData:Timeout'][0]);
        $this->assertSame('BACKUP', file_get_contents($result['data']['zip_file']));
        @unlink($result['data']['zip_file']);
    }
}
