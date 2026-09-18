<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/ColorHelper.php';
require_once __DIR__ . '/../libs/ModulHelper/DeviceCommandHelper.php';

use PHPUnit\Framework\TestCase;

/**
 * Prueft, dass Farbe und separat exponierte Helligkeit unabhaengig bleiben.
 */
class DeviceColorContractTest extends TestCase
{
    public function testCieColorActionPreservesSeparateBrightness(): void
    {
        $helper = $this->CreateHelper(true);

        $this->assertTrue($helper->SetColorForTest(0xC59F5A, 'cie'));
        $this->assertCount(1, $helper->sentPayloads);
        $this->assertArrayHasKey('color', $helper->sentPayloads[0]);
        $this->assertArrayHasKey('x', $helper->sentPayloads[0]['color']);
        $this->assertArrayHasKey('y', $helper->sentPayloads[0]['color']);
        $this->assertArrayNotHasKey('brightness', $helper->sentPayloads[0]);
    }

    public function testHsColorActionPreservesSeparateBrightness(): void
    {
        $helper = $this->CreateHelper(true);

        $this->assertTrue($helper->SetColorForTest(0x3366CC, 'hs'));
        $this->assertArrayHasKey('color', $helper->sentPayloads[0]);
        $this->assertArrayNotHasKey('brightness', $helper->sentPayloads[0]);
    }

    public function testHsvColorActionPreservesSeparateBrightness(): void
    {
        $helper = $this->CreateHelper(true);

        $this->assertTrue($helper->SetColorForTest(0x3366CC, 'hsv'));
        $this->assertArrayHasKey('color', $helper->sentPayloads[0]);
        $this->assertArrayNotHasKey('brightness', $helper->sentPayloads[0]);
    }

    public function testColorActionRetainsDerivedBrightnessWithoutSeparateExpose(): void
    {
        $helper = $this->CreateHelper(false);

        $this->assertTrue($helper->SetColorForTest(0xC59F5A, 'cie'));
        $this->assertArrayHasKey('brightness', $helper->sentPayloads[0]);
    }

    public function testTransitionDoesNotReintroduceSeparateBrightness(): void
    {
        $helper = $this->CreateHelper(true);

        $this->assertTrue($helper->SetColorForTest(0xC59F5A, 'cie', 'color', 3));
        $this->assertSame(3, $helper->sentPayloads[0]['transition']);
        $this->assertArrayNotHasKey('brightness', $helper->sentPayloads[0]);
    }

    private function CreateHelper(bool $hasBrightnessExpose): object
    {
        return new class($hasBrightnessExpose) {
            use \Zigbee2MQTT\ColorHelper;
            use \Zigbee2MQTT\DeviceCommandHelper {
                setColor as public SetColorForTest;
            }

            /** @var array<int, array<string, mixed>> */
            public array $sentPayloads = [];

            public function __construct(private readonly bool $hasBrightnessExpose)
            {
            }

            public function SendSetCommand(array $payload): bool
            {
                $this->sentPayloads[] = $payload;
                return true;
            }

            public function SendDebug(string $message, mixed $data, int $format): void
            {
            }

            private function HasExposeProperty(string $property): bool
            {
                return $property === 'brightness' && $this->hasBrightnessExpose;
            }
        };
    }
}
