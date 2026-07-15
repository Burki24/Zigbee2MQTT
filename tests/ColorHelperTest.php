<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/ColorHelper.php';

use PHPUnit\Framework\TestCase;

/**
 * Prüft Randfälle der Farbumrechnungen.
 */
class ColorHelperTest extends TestCase
{
    public function testHSVToIntNormalizesHue(): void
    {
        $helper = $this->CreateHelper();

        $this->assertSame(0xFF00FF, $helper->HSVToInt(-60, 100, 255));
        $this->assertSame(0xFF0000, $helper->HSVToInt(360, 100, 255));
        $this->assertSame(0xFF0000, $helper->HSVToInt(720, 100, 255));
    }

    public function testHSVToIntClampsSaturationAndBrightness(): void
    {
        $helper = $this->CreateHelper();

        $this->assertSame(0xFFFFFF, $helper->HSVToInt(120, -10, 255));
        $this->assertSame(0x00FF00, $helper->HSVToInt(120, 150, 300));
    }

    private function CreateHelper(): object
    {
        return new class() {
            use \Zigbee2MQTT\ColorHelper {
                HSVToInt as public;
            }

            public function SendDebug(string $message, mixed $data, int $format): void
            {
            }
        };
    }
}
