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
        $requested = 0xC59F5A;
        $cie = $helper->RGBToXy($helper->IntToRGB($requested));
        $feedback = $helper->xyToInt($cie['x'], $cie['y'], 254);
        $requestedChromaticity = $this->RgbToHueAndSaturation($requested);
        $feedbackChromaticity = $this->RgbToHueAndSaturation($feedback);

        $this->assertEqualsWithDelta($requestedChromaticity['hue'], $feedbackChromaticity['hue'], 1.0);
        $this->assertEqualsWithDelta($requestedChromaticity['saturation'], $feedbackChromaticity['saturation'], 1.5);
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

    /** @return array{hue:float,saturation:float} */
    private function RgbToHueAndSaturation(int $color): array
    {
        $red = (($color >> 16) & 0xFF) / 255;
        $green = (($color >> 8) & 0xFF) / 255;
        $blue = ($color & 0xFF) / 255;
        $maximum = max($red, $green, $blue);
        $minimum = min($red, $green, $blue);
        $delta = $maximum - $minimum;
        $hue = 0.0;

        if ($delta !== 0.0) {
            if ($maximum === $red) {
                $hue = 60.0 * fmod(($green - $blue) / $delta, 6.0);
            } elseif ($maximum === $green) {
                $hue = 60.0 * ((($blue - $red) / $delta) + 2.0);
            } else {
                $hue = 60.0 * ((($red - $green) / $delta) + 4.0);
            }
        }
        if ($hue < 0.0) {
            $hue += 360.0;
        }

        return [
            'hue'        => $hue,
            'saturation' => $maximum === 0.0 ? 0.0 : ($delta / $maximum) * 100.0
        ];
    }
}
