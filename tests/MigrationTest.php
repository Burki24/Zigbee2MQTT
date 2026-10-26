<?php

declare(strict_types=1);

include_once __DIR__ . '/DumpInclude.php';

class MigrationTest extends DumpInclude
{
    public function testFailedMigrationRestoresFlagsAndKeepsLegacyDataForRetry(): void
    {
        $id = IPS_CreateInstance('{E5BB36C6-A70B-EB23-3716-9151A09AC8A2}');
        $brightness = IPS_CreateVariable(1);
        IPS_SetParent($brightness, $id);
        IPS_SetIdent($brightness, 'brightness');
        SetValue($brightness, 73);
        $file = tempnam(sys_get_temp_dir(), 'z2m-migration-');
        $exposes = [['type' => 'numeric', 'name' => 'brightness', 'property' => 'brightness', 'access' => 7]];
        file_put_contents($file, json_encode(['exposes' => $exposes]));
        $device = new class($id, $file) extends Zigbee2MQTTDevice {
            public bool $fail = true;
            public function __construct(int $id, private string $file)
            {
                parent::__construct($id);
            }
            protected function GetLegacyExposeFilePath(): string
            {
                return $this->file;
            }
            protected function BuildBrightnessFeaturePresentation(array $feature): ?array
            {
                if ($this->fail) {
                    throw new RuntimeException('Simulated presentation failure');
                }
                return null;
            }
        };
        $device->Create();
        $device->BUFFER_MQTT_SUSPENDED = false;
        $device->BUFFER_PROCESSING_MIGRATION = false;
        $input = '{"attributes":{"Version":4.0}}';
        try {
            try {
                $device->Migrate($input);
                $this->fail('Expected migration failure');
            } catch (RuntimeException $exception) {
                $this->assertSame('Simulated presentation failure', $exception->getMessage());
            }
            $this->assertFalse($device->BUFFER_MQTT_SUSPENDED);
            $this->assertFalse($device->BUFFER_PROCESSING_MIGRATION);
            $this->assertFileExists($file);
            $this->assertSame(73, GetValue($brightness));
            $device->fail = false;
            $result = $device->Migrate($input);
            $decoded = json_decode($result, true);
            $this->assertSame(5.1, $decoded['attributes']['Version']);
            $this->assertSame($exposes, json_decode($decoded['attributes']['Exposes'], true));
            $this->assertSame($result, $device->Migrate($result));
            $this->assertSame($brightness, IPS_GetObjectIDByIdent('brightness', $id));
            $this->assertSame(73, GetValue($brightness));
            $this->assertFileExists($file);
        } finally {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public function testInvalidLegacySourceIsPreservedAndPreviousFlagsAreRestored(): void
    {
        $id = IPS_CreateInstance('{E5BB36C6-A70B-EB23-3716-9151A09AC8A2}');
        $file = tempnam(sys_get_temp_dir(), 'z2m-invalid-exposes-');
        file_put_contents($file, '{broken-json');
        $device = new class($id, $file) extends Zigbee2MQTTDevice {
            public function __construct(int $id, private string $file)
            {
                parent::__construct($id);
            }
            protected function GetLegacyExposeFilePath(): string
            {
                return $this->file;
            }
        };
        $device->Create();
        $device->BUFFER_MQTT_SUSPENDED = true;
        $device->BUFFER_PROCESSING_MIGRATION = false;
        try {
            try {
                $device->Migrate('{"attributes":{"Version":4.0}}');
                $this->fail('Invalid source must abort migration');
            } catch (JsonException $exception) {
                $this->assertSame(JSON_ERROR_SYNTAX, $exception->getCode());
            }
            $this->assertTrue($device->BUFFER_MQTT_SUSPENDED);
            $this->assertFalse($device->BUFFER_PROCESSING_MIGRATION);
            $this->assertSame('{broken-json', file_get_contents($file));
        } finally {
            unlink($file);
        }
    }
}
