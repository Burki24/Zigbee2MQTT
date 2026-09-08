<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/BufferHelper.php';
require_once __DIR__ . '/../libs/AttributeArrayHelper.php';

use PHPUnit\Framework\TestCase;

/**
 * Prueft die transparente Komprimierung grosser Buffer- und Attributwerte.
 */
class DataCompressionHelperTest extends TestCase
{
    public function testEncodedMarkerIsDetectedWithoutRecompression(): void
    {
        $largeValue = str_repeat('repeated payload value', 100);
        $encoded = \Zigbee2MQTT\DataCompressionHelper::Encode($largeValue);

        $this->assertTrue(\Zigbee2MQTT\DataCompressionHelper::IsEncoded($encoded));
        $this->assertFalse(\Zigbee2MQTT\DataCompressionHelper::IsEncoded($largeValue));
    }

    public function testLargeBufferIsCompressedAndRoundTrips(): void
    {
        $helper = $this->CreateBufferHelper();
        $value = array_fill(0, 200, ['property' => 'temperature', 'value' => 21.5]);

        $helper->Payload = $value;

        $raw = $helper->buffers['Payload'];
        $this->assertStringStartsWith('Z2MZ1:', $raw);
        $this->assertLessThan(strlen(serialize($value)), strlen($raw));
        $this->assertSame($value, $helper->Payload);
    }

    public function testLegacyAndSmallBuffersRemainReadable(): void
    {
        $helper = $this->CreateBufferHelper();
        $helper->buffers['Legacy'] = serialize(['state' => 'ON']);

        $this->assertSame(['state' => 'ON'], $helper->Legacy);

        $helper->Small = true;
        $this->assertSame(serialize(true), $helper->buffers['Small']);
        $this->assertTrue($helper->Small);
    }

    public function testLargeChunkedBufferIsCompressedBeforeSplitting(): void
    {
        $helper = $this->CreateBufferHelper();
        $value = array_fill(0, 1000, ['model' => 'Repeated model name', 'supported' => true]);

        $helper->Multi_TransactionData = $value;

        $parts = $helper->BufferListe_Multi_TransactionData;
        $stored = '';
        foreach ($parts as $part) {
            $stored .= $helper->{'Part_Multi_TransactionData' . $part};
        }
        $this->assertStringStartsWith('Z2MZ1:', $stored);
        $this->assertSame($value, $helper->Multi_TransactionData);
    }

    public function testLargeArrayAttributeIsCompressedAndLegacyJsonRemainsReadable(): void
    {
        $helper = new class() {
            use \Zigbee2MQTT\AttributeArrayHelper {
                ReadAttributeArray as public readArray;
                WriteAttributeArray as public writeArray;
            }

            public array $attributes = [];

            protected function ReadAttributeString(string $name): string
            {
                return $this->attributes[$name] ?? '';
            }

            protected function WriteAttributeString(string $name, string $value): void
            {
                $this->attributes[$name] = $value;
            }

            protected function RegisterAttributeString(string $name, string $value): void
            {
                $this->attributes[$name] ??= $value;
            }
        };
        $value = array_fill(0, 200, ['endpoint' => 1, 'cluster' => 'genOnOff']);

        $helper->writeArray('Endpoints', $value);

        $this->assertStringStartsWith('Z2MZ1:', $helper->attributes['Endpoints']);
        $this->assertLessThan(strlen(json_encode($value)), strlen($helper->attributes['Endpoints']));
        $this->assertSame($value, $helper->readArray('Endpoints'));

        $helper->attributes['Legacy'] = '{"state":"ON"}';
        $this->assertSame(['state' => 'ON'], $helper->readArray('Legacy'));
    }

    public function testLargeLegacyAttributeIsMigratedWhenRead(): void
    {
        $helper = new class() {
            use \Zigbee2MQTT\AttributeArrayHelper {
                ReadAttributeArray as public readArray;
            }

            public array $attributes = [];

            protected function ReadAttributeString(string $name): string
            {
                return $this->attributes[$name] ?? '';
            }

            protected function WriteAttributeString(string $name, string $value): void
            {
                $this->attributes[$name] = $value;
            }

            protected function RegisterAttributeString(string $name, string $value): void
            {
                $this->attributes[$name] ??= $value;
            }
        };
        $value = array_fill(0, 200, ['property' => 'humidity', 'unit' => '%']);
        $helper->attributes['LegacyLarge'] = json_encode($value);

        $this->assertSame($value, $helper->readArray('LegacyLarge'));
        $this->assertStringStartsWith('Z2MZ1:', $helper->attributes['LegacyLarge']);
    }

    public function testDamagedCompressedDataUsesSafeFallbacks(): void
    {
        $helper = $this->CreateBufferHelper();
        $helper->buffers['Broken'] = 'Z2MZ1:not-valid-base64!';

        $this->assertFalse($helper->Broken);
    }

    private function CreateBufferHelper(): object
    {
        return new class() {
            use \Zigbee2MQTT\BufferHelper;

            public array $buffers = [];

            public function SetBuffer(string $name, string $value): void
            {
                $this->buffers[$name] = $value;
            }

            public function GetBuffer(string $name): string
            {
                return $this->buffers[$name] ?? '';
            }
        };
    }
}
