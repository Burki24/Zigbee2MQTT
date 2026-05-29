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

    public function testResponseWithoutTransactionIsMatchedByResponseTopic(): void
    {
        $helper = new class() {
            use \Zigbee2MQTT\SendData {
                SendData as public sendMqttData;
                UpdateTransactionByResponseTopic as public completeByResponseTopic;
            }

            public array $TransactionData = [];
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
