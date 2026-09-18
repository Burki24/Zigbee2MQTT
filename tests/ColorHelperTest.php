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

    public function testCieRoundTripUsesMatchingConversionMatrices(): void
    {
        $helper = $this->CreateHelper();
        $requested = 0xC59F5A;
        $cie = $helper->RGBToXy($helper->IntToRGB($requested));
        $feedback = $helper->xyToInt($cie['x'], $cie['y'], (int) $cie['bri']);

        $this->assertRgbChannelsWithin($requested, $feedback, 1);
    }

    public function testCieFullBrightnessFeedbackPreservesChromaticity(): void
    {
        $helper = $this->CreateHelper();

        foreach ([0xFFFFFF, 0xFF0000, 0x00FF00, 0x0000FF, 0xFFFF00, 0x00FFFF, 0xFF00FF, 0xC59F5A, 0x102030, 0x010203, 0x010101] as $requested) {
            $cie = $helper->RGBToXy($helper->IntToRGB($requested));
            $feedback = $helper->xyToInt($cie['x'], $cie['y'], 254);
            $feedbackCie = $helper->RGBToXy($helper->IntToRGB($feedback));

            // Vier XY-Nachkommastellen und 8-Bit-RGB verursachen kleine Rundungsabweichungen.
            foreach (['x', 'y'] as $coordinate) {
                $this->assertEqualsWithDelta($cie[$coordinate], $feedbackCie[$coordinate], 0.001, sprintf('#%06X: %s', $requested, $coordinate));
            }
        }
    }

    public function testBlackHasZeroLuminance(): void
    {
        $helper = $this->CreateHelper();
        $cie = $helper->RGBToXy([0, 0, 0]);

        $this->assertEquals(0, $cie['bri']);
        $this->assertSame(0x000000, $helper->xyToInt($cie['x'], $cie['y'], 0));
    }

    private function CreateHelper(): object
    {
        return new class() {
            use \Zigbee2MQTT\ColorHelper {
                HSVToInt as public;
                IntToRGB as public;
                RGBToXy as public;
                xyToInt as public;
            }

            public function SendDebug(string $message, mixed $data, int $format): void
            {
            }
        };
    }

    private function assertRgbChannelsWithin(int $expected, int $actual, int $tolerance): void
    {
        foreach ([16, 8, 0] as $shift) {
            $expectedChannel = ($expected >> $shift) & 0xFF;
            $actualChannel = ($actual >> $shift) & 0xFF;
            $this->assertLessThanOrEqual($tolerance, abs($expectedChannel - $actualChannel));
        }
    }

}
