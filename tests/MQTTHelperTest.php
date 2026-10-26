<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/autoload.php';
require_once __DIR__ . '/../libs/MQTTHelper.php';

use PHPUnit\Framework\TestCase;

/**
 * Prüft die Transaktionsverwaltung von MQTT-Requests.
 */
class MQTTHelperTest extends TestCase
{
    public function testExpiredResponseCannotCompleteReplacementRequest(): void
    {
        $helper = $this->createPollingHelper([]);
        $oldPayload = [];
        $old = $helper->createTransaction($oldPayload, '/bridge/request/options', microtime(true) - 1);
        $newPayload = [];
        $new = $helper->createTransaction($newPayload, '/bridge/request/options');
        $late = ['transaction' => $old, 'status' => 'ok'];
        $this->assertFalse($helper->completeTransaction($late));
        $this->assertFalse($helper->completeByTopic('/bridge/response/options', $late));
        $this->assertNull($helper->Multi_TransactionData[$new]['__result']);
        $this->assertTrue($helper->completeTransaction(['transaction' => $new, 'status' => 'ok']));
        $this->assertSame(['status' => 'ok'], $helper->waitForResult($new, 100));
    }

    public function testTopicFallbackRejectsExplicitAndAmbiguousTransactions(): void
    {
        $helper = $this->createPollingHelper([]);
        $payload = [];
        $id = $helper->createTransaction($payload, '/bridge/request/options');
        foreach ([999999, null, 'invalid'] as $transaction) {
            $this->assertFalse($helper->completeByTopic('/bridge/response/options', ['transaction' => $transaction, 'status' => 'ok']));
            $this->assertNull($helper->Multi_TransactionData[$id]['__result']);
        }
        $secondPayload = [];
        $second = $helper->createTransaction($secondPayload, '/bridge/request/options');
        $this->assertFalse($helper->completeByTopic('/bridge/response/options', ['status' => 'ok']));
        unset($helper->Multi_TransactionData[$second]);
        $this->assertTrue($helper->completeByTopic('/bridge/response/options', ['status' => 'ok']));
        $this->assertFalse($helper->completeByTopic('/bridge/response/options', ['status' => 'error']));
        $this->assertSame(['status' => 'ok'], $helper->waitForResult($id, 100));
    }

    public function testWaitingRetriesBusyLockAndConsumesCompletedTransactionAtomically(): void
    {
        $helper = $this->createPollingHelper([false, true, false]);
        $helper->Multi_TransactionData = [42 => ['__meta' => [], '__result' => ['transaction' => 42, 'status' => 'ok']]];
        $this->assertSame(['status' => 'ok'], $helper->waitForResult(42, 200));
        $this->assertSame([], $helper->Multi_TransactionData);
        $this->assertSame(1, $helper->unlockCalls);
    }

    public function testExpiredTransactionsAreReclaimedAfterCleanupLockWasBusy(): void
    {
        $helper = $this->createPollingHelper(array_fill(0, 100, false));
        $helper->Multi_TransactionData = [42 => ['__meta' => ['expiresAt' => microtime(true) - 1], '__result' => null]];
        $start = microtime(true);
        $this->assertFalse($helper->waitForResult(42, 30));
        $this->assertGreaterThanOrEqual(0.025, microtime(true) - $start);
        $this->assertCount(1, $helper->Multi_TransactionData);
        $this->assertSame(0, $helper->unlockCalls);
        $helper->lockResults = [];
        $helper->Multi_TransactionData[43] = ['__meta' => ['expiresAt' => microtime(true) + 60], '__result' => null];
        $payload = [];
        $id = $helper->createTransaction($payload, '/bridge/request/health_check');
        $this->assertIsInt($id);
        $this->assertArrayNotHasKey(42, $helper->Multi_TransactionData);
        $this->assertArrayHasKey(43, $helper->Multi_TransactionData);
    }

    public function testRequestDeadlineIsStoredAndLateResponsesToExpiredTransactionsAreIgnored(): void
    {
        $helper = $this->createPollingHelper([]);
        $payload = [];
        $deadline = microtime(true) + 300;
        $id = $helper->createTransaction($payload, '/bridge/request/backup', $deadline);
        $this->assertSame($deadline, $helper->Multi_TransactionData[$id]['__meta']['expiresAt']);
        $helper->Multi_TransactionData[77] = ['__meta' => ['expiresAt' => microtime(true) - 1], '__result' => null];
        $this->assertFalse($helper->completeTransaction(['transaction' => 77, 'status' => 'ok']));
        $this->assertArrayNotHasKey(77, $helper->Multi_TransactionData);
        $this->assertArrayHasKey($id, $helper->Multi_TransactionData);
    }

    public function testBusyTransactionLockAbortsRequestWithoutSendingOrChangingOtherTransactions(): void
    {
        $helper = new class() {
            use \Zigbee2MQTT\SendData {
                SendData as public sendMqttData;
                ClearTransactionData as public clearTransactions;
                UpdateTransaction as public completeTransaction;
                UpdateTransactionByResponseTopic as public completeByTopic;
            }
            public array $TransactionData = [7 => ['pending' => true]];
            public array $Multi_TransactionData = [8 => ['pending' => true]];
            public int $unlockCalls = 0;
            public int $sendCalls = 0;
            public function lock(string $name): bool
            {
                return false;
            }
            public function unlock(string $name): void
            {
                $this->unlockCalls++;
            }
            public function SendDataToParent(string $data): void
            {
                $this->sendCalls++;
            }
            public function SendDebug(string $message, mixed $data, int $format): void
            {
            }
            public function Translate(string $text): string
            {
                return $text;
            }
        };
        $this->assertFalse($helper->sendMqttData('/SymconExtension/lists/request/getDevices', [], 2500));
        $helper->clearTransactions();
        $this->assertFalse($helper->completeTransaction(['transaction' => 8]));
        $this->assertFalse($helper->completeByTopic('/bridge/response/options', []));
        $this->assertSame([7 => ['pending' => true]], $helper->TransactionData);
        $this->assertSame([8 => ['pending' => true]], $helper->Multi_TransactionData);
        $this->assertSame(0, $helper->sendCalls);
        $this->assertSame(0, $helper->unlockCalls);
    }

    public function testDecodePayloadPreservesLegacyUtf8Payload(): void
    {
        $helper = new class() {
            use \Zigbee2MQTT\SendData {
                DecodePayload as public decodePayloadForTest;
            }
        };

        $this->assertSame('{"friendly_name":"Küchentür"}', $helper->decodePayloadForTest('{"friendly_name":"Küchentür"}'));
    }

    public function testDecodePayloadDecodesStrictHexPayload(): void
    {
        $helper = new class() {
            use \Zigbee2MQTT\SendData {
                DecodePayload as public decodePayloadForTest;
            }
        };
        $payload = '{"state":"ON"}';

        $this->assertSame($payload, $helper->decodePayloadForTest(bin2hex($payload)));
    }

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

    private function createPollingHelper(array $locks): object
    {
        return new class($locks) {
            use \Zigbee2MQTT\SendData {
                UpdateTransactionByResponseTopic as public completeByTopic;
                WaitForTransactionEnd as public waitForResult;
                AddTransaction as public createTransaction;
                UpdateTransaction as public completeTransaction;
            }
            public array $TransactionData = [];
            public array $Multi_TransactionData = [];
            public int $unlockCalls = 0;
            public function __construct(public array $lockResults)
            {
            }
            public function lock(string $name): bool
            {
                return $this->lockResults !== [] ? array_shift($this->lockResults) : true;
            }
            public function unlock(string $name): void
            {
                $this->unlockCalls++;
            }
            public function SendDebug(string $message, mixed $data, int $format): void
            {
            }
            public function Translate(string $text): string
            {
                return $text;
            }
        };
    }
}
