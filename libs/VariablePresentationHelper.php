<?php

declare(strict_types=1);

namespace Zigbee2MQTT;

/**
 * Trait mit Hilfsfunktionen fuer moderne Variablendarstellungen in der Tile-Visualisierung.
 */
trait VariablePresentationHelper
{
    /**
     * Setzt fuer die Kelvin-Farbtemperaturvariable die moderne Tile-Darstellung.
     *
     * Zigbee2MQTT liefert color_temp in Mired. Fuer die Bedienung in der Tile-Visu
     * wird die zusaetzliche color_temp_kelvin-Variable verwendet und der Bereich
     * aus den Mired-Grenzen des Exposes nach Kelvin umgerechnet.
     */
    protected function ApplyColorTemperaturePresentation(string $ident, array $feature): void
    {
        $minKelvin = 1000;
        $maxKelvin = 12000;

        if (isset($feature['value_min'], $feature['value_max']) && (int) $feature['value_min'] > 0 && (int) $feature['value_max'] > 0) {
            $minKelvin = $this->convertMiredToKelvin((int) $feature['value_max']);
            $maxKelvin = $this->convertMiredToKelvin((int) $feature['value_min']);

            if ($minKelvin > $maxKelvin) {
                [$minKelvin, $maxKelvin] = [$maxKelvin, $minKelvin];
            }
        }

        $this->ApplySliderPresentation($ident, [
            'MIN'                 => $minKelvin,
            'MAX'                 => $maxKelvin,
            'STEP_SIZE'           => 1,
            'GRADIENT_TYPE'       => 3,
            'CUSTOM_GRADIENT'     => $this->CreateColorTemperatureGradient($minKelvin, $maxKelvin),
            'USAGE_TYPE'          => 1,
            'SUFFIX'              => ' K',
            'DIGITS'              => 0,
            'ICON'                => 'temperature-half',
        ]);
    }

    /**
     * Setzt eine generische Schieberegler-Darstellung.
     *
     * Die Methode ist bewusst als Basis fuer weitere Tile-Visualisierungen gedacht.
     */
    protected function ApplySliderPresentation(string $ident, array $presentation): void
    {
        if (!$this->SupportsCustomPresentation()) {
            return;
        }

        $variableID = $this->GetObjectIDByIdent($ident);
        if ($variableID === false) {
            return;
        }

        IPS_SetVariableCustomPresentation($variableID, array_merge([
            'PRESENTATION'          => \constant('VARIABLE_PRESENTATION_SLIDER'),
            'MIN'                   => 0,
            'MAX'                   => 100,
            'STEP_SIZE'             => 1,
            'GRADIENT_TYPE'         => 0,
            'CUSTOM_GRADIENT'       => '[]',
            'USAGE_TYPE'            => 0,
            'PREFIX'                => '',
            'SUFFIX'                => '',
            'PERCENTAGE'            => false,
            'THOUSANDS_SEPARATOR'   => '',
            'DIGITS'                => 0,
            'DECIMAL_SEPARATOR'     => 'Client',
            'ICON'                  => '',
            'INTERVALS_ACTIVE'      => false,
            'INTERVALS'             => '[]'
        ], $presentation));
    }

    /**
     * Prueft, ob die laufende Symcon-Version moderne Custom Presentations unterstuetzt.
     */
    private function SupportsCustomPresentation(): bool
    {
        return \function_exists('IPS_SetVariableCustomPresentation') && \defined('VARIABLE_PRESENTATION_SLIDER');
    }

    /**
     * Erstellt einen benutzerdefinierten Farbtemperaturverlauf fuer den konkreten Kelvin-Bereich.
     */
    private function CreateColorTemperatureGradient(int $minKelvin, int $maxKelvin): string
    {
        $anchors = [
            ['Value' => 2200, 'Color' => 0xFFB36B],
            ['Value' => 2700, 'Color' => 0xFFD19A],
            ['Value' => 3000, 'Color' => 0xFFE1B8],
            ['Value' => 3500, 'Color' => 0xFFF0D6],
            ['Value' => 4000, 'Color' => 0xFFF8EB],
            ['Value' => 4500, 'Color' => 0xF4FAFF],
            ['Value' => 5000, 'Color' => 0xE6F3FF],
            ['Value' => 6500, 'Color' => 0xD6ECFF]
        ];

        $gradient = [];
        foreach ($anchors as $anchor) {
            if ($anchor['Value'] >= $minKelvin && $anchor['Value'] <= $maxKelvin) {
                $gradient[] = $anchor;
            }
        }

        if (empty($gradient) || $gradient[0]['Value'] !== $minKelvin) {
            array_unshift($gradient, [
                'Value' => $minKelvin,
                'Color' => $this->InterpolateColorTemperatureColor($minKelvin, $anchors)
            ]);
        }

        $lastIndex = count($gradient) - 1;
        if ($gradient[$lastIndex]['Value'] !== $maxKelvin) {
            $gradient[] = [
                'Value' => $maxKelvin,
                'Color' => $this->InterpolateColorTemperatureColor($maxKelvin, $anchors)
            ];
        }

        return json_encode($gradient);
    }

    /**
     * Interpoliert die Farbe zwischen den bekannten Farbtemperatur-Ankerpunkten.
     */
    private function InterpolateColorTemperatureColor(int $kelvin, array $anchors): int
    {
        if ($kelvin <= $anchors[0]['Value']) {
            return $anchors[0]['Color'];
        }

        $lastIndex = count($anchors) - 1;
        if ($kelvin >= $anchors[$lastIndex]['Value']) {
            return $anchors[$lastIndex]['Color'];
        }

        for ($i = 1; $i <= $lastIndex; $i++) {
            if ($kelvin > $anchors[$i]['Value']) {
                continue;
            }

            $lower = $anchors[$i - 1];
            $upper = $anchors[$i];
            $factor = ($kelvin - $lower['Value']) / ($upper['Value'] - $lower['Value']);

            return $this->InterpolateHexColor($lower['Color'], $upper['Color'], $factor);
        }

        return $anchors[$lastIndex]['Color'];
    }

    /**
     * Interpoliert zwei SelectColor-Hexwerte.
     */
    private function InterpolateHexColor(int $fromColor, int $toColor, float $factor): int
    {
        $fromR = ($fromColor >> 16) & 0xFF;
        $fromG = ($fromColor >> 8) & 0xFF;
        $fromB = $fromColor & 0xFF;

        $toR = ($toColor >> 16) & 0xFF;
        $toG = ($toColor >> 8) & 0xFF;
        $toB = $toColor & 0xFF;

        $red = (int) round($fromR + (($toR - $fromR) * $factor));
        $green = (int) round($fromG + (($toG - $fromG) * $factor));
        $blue = (int) round($fromB + (($toB - $fromB) * $factor));

        return ($red << 16) | ($green << 8) | $blue;
    }
}
