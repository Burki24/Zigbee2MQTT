<?php

declare(strict_types=1);

namespace Zigbee2MQTT;

/**
 * Trait mit Hilfsfunktionen fuer moderne Variablendarstellungen in der Tile-Visualisierung.
 */
trait VariablePresentationHelper
{
    /**
     * Setzt automatisch eine passende Tile-Darstellung fuer bekannte Expose-Typen.
     */
    protected function ApplyFeaturePresentation(string $ident, array $feature): void
    {
        if (!isset($feature['type'])) {
            return;
        }

        switch ($feature['type']) {
            case 'numeric':
                $this->ApplyNumericFeaturePresentation($ident, $feature);
                return;
            case 'enum':
                $this->ApplyEnumerationPresentation($ident, $feature);
                return;
        }
    }

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
     * Setzt fuer numerische Exposes mit Wertebereich eine Schieberegler-Darstellung.
     */
    protected function ApplyNumericFeaturePresentation(string $ident, array $feature): void
    {
        if (!isset($feature['value_min'], $feature['value_max'])) {
            return;
        }
        if (!isset($feature['access']) || (((int) $feature['access'] & 2) !== 2)) {
            return;
        }

        $unit = isset($feature['unit']) && \is_string($feature['unit']) ? $feature['unit'] : '';
        $stepSize = isset($feature['value_step']) ? (float) $feature['value_step'] : 1.0;
        $digits = $this->GetDigitsFromStepSize($stepSize);

        $presentation = [
            'MIN'       => (float) $feature['value_min'],
            'MAX'       => (float) $feature['value_max'],
            'STEP_SIZE' => $stepSize,
            'SUFFIX'    => $unit === '' ? '' : ' ' . $unit,
            'DIGITS'    => $digits,
            'ICON'      => $this->GetNumericPresentationIcon($feature)
        ];

        if ($unit === '%') {
            $presentation['PERCENTAGE'] = true;
        }

        if ($unit === '°C') {
            $presentation['GRADIENT_TYPE'] = 1;
            $presentation['USAGE_TYPE'] = 0;
        }

        $this->ApplySliderPresentation($ident, $presentation);
    }

    /**
     * Setzt fuer Enum-Exposes eine Aufzaehlungs-Darstellung.
     */
    protected function ApplyEnumerationPresentation(string $ident, array $feature): void
    {
        if (!$this->SupportsEnumerationPresentation() || !isset($feature['values']) || !\is_array($feature['values'])) {
            return;
        }

        $variableID = $this->GetObjectIDByIdent($ident);
        if ($variableID === false) {
            return;
        }

        $options = [];
        foreach ($feature['values'] as $value) {
            $options[] = [
                'Value'      => (string) $value,
                'Caption'    => $this->CreatePresentationCaption((string) $value),
                'IconActive' => false,
                'IconValue'  => '',
                'Color'      => -1
            ];
        }

        IPS_SetVariableCustomPresentation($variableID, [
            'PRESENTATION' => \constant('VARIABLE_PRESENTATION_ENUMERATION'),
            'OPTIONS'      => json_encode($options),
            'LAYOUT'       => count($options) <= 3 ? 1 : 0,
            'DISPLAY'      => 0,
            'ICON'         => $this->GetEnumerationPresentationIcon($feature)
        ]);
    }

    /**
     * Prueft, ob die laufende Symcon-Version moderne Custom Presentations unterstuetzt.
     */
    private function SupportsCustomPresentation(): bool
    {
        return \function_exists('IPS_SetVariableCustomPresentation') && \defined('VARIABLE_PRESENTATION_SLIDER');
    }

    /**
     * Prueft, ob Aufzaehlungs-Darstellungen verfuegbar sind.
     */
    private function SupportsEnumerationPresentation(): bool
    {
        return \function_exists('IPS_SetVariableCustomPresentation') && \defined('VARIABLE_PRESENTATION_ENUMERATION');
    }

    /**
     * Ermittelt Nachkommastellen aus der Schrittweite.
     */
    private function GetDigitsFromStepSize(float $stepSize): int
    {
        $stepText = rtrim(rtrim(sprintf('%.6F', $stepSize), '0'), '.');
        $decimalPosition = strpos($stepText, '.');

        if ($decimalPosition === false) {
            return 0;
        }

        return strlen($stepText) - $decimalPosition - 1;
    }

    /**
     * Ermittelt ein Standard-Icon fuer numerische Darstellungen.
     */
    private function GetNumericPresentationIcon(array $feature): string
    {
        $property = $feature['property'] ?? '';
        $unit = $feature['unit'] ?? '';

        if ($unit === '°C') {
            return 'temperature-half';
        }

        if ($unit === '%') {
            return 'gauge';
        }

        if ($unit === 's') {
            return 'clock';
        }

        if (strpos((string) $property, 'brightness') !== false) {
            return 'sun';
        }

        return '';
    }

    /**
     * Ermittelt ein Standard-Icon fuer Enum-Darstellungen.
     */
    private function GetEnumerationPresentationIcon(array $feature): string
    {
        $property = (string) ($feature['property'] ?? '');

        if (strpos($property, 'mode') !== false) {
            return 'menu';
        }

        if (strpos($property, 'orientation') !== false) {
            return 'rotate-cw';
        }

        return 'list';
    }

    /**
     * Erstellt eine lesbare Beschriftung aus einem Expose-Wert.
     */
    private function CreatePresentationCaption(string $value): string
    {
        return ucwords(str_replace('_', ' ', $value));
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
