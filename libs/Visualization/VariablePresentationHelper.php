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
    protected function ApplyFeaturePresentation(string $ident, array $feature, ?string $groupType = null): void
    {
        if (!isset($feature['type'])) {
            return;
        }

        if ($this->ApplyShutterFeaturePresentation($ident, $feature, $groupType)) {
            return;
        }

        switch ($feature['type']) {
            case 'binary':
                $this->ApplyBinaryFeaturePresentation($ident, $feature);
                return;
            case 'numeric':
                $this->ApplyNumericFeaturePresentation($ident, $feature, $groupType);
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
        [$minKelvin, $maxKelvin] = $this->GetColorTemperaturePresentationRange($feature);

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
     * Aktualisiert die Farbtemperaturdarstellung anhand gespeicherter Exposes.
     */
    protected function RefreshColorTemperaturePresentation(): void
    {
        if ($this->GetObjectIDByIdent('color_temp_kelvin') === false) {
            return;
        }

        $feature = $this->FindColorTemperatureFeature($this->ReadAttributeArray(self::ATTRIBUTE_EXPOSES));
        if ($feature === null) {
            return;
        }

        $this->ApplyColorTemperaturePresentation('color_temp_kelvin', $feature);
    }

    /**
     * Sucht das color_temp-Feature rekursiv in einem Expose-Baum.
     */
    private function FindColorTemperatureFeature(array $features): ?array
    {
        foreach ($features as $feature) {
            if (!\is_array($feature)) {
                continue;
            }

            if (($feature['property'] ?? null) === 'color_temp') {
                return $feature;
            }

            $subFeatures = $feature['features'] ?? [];
            if (!\is_array($subFeatures)) {
                continue;
            }

            $subFeature = $this->FindColorTemperatureFeature($subFeatures);
            if ($subFeature !== null) {
                return $subFeature;
            }
        }

        return null;
    }

    /**
     * Liefert den Kelvin-Bereich fuer die Farbtemperaturdarstellung.
     */
    private function GetColorTemperaturePresentationRange(array $feature): array
    {
        $overrideRange = $this->ReadColorTemperaturePresentationOverrideRange();
        if ($overrideRange !== null) {
            return $overrideRange;
        }

        $minKelvin = 1000;
        $maxKelvin = 12000;

        if (isset($feature['value_min'], $feature['value_max']) && (int) $feature['value_min'] > 0 && (int) $feature['value_max'] > 0) {
            $minKelvin = $this->convertMiredToKelvin((int) $feature['value_max']);
            $maxKelvin = $this->convertMiredToKelvin((int) $feature['value_min']);
        }

        return $this->NormalizeColorTemperaturePresentationRange($minKelvin, $maxKelvin);
    }

    /**
     * Begrenzt Kelvin-Aktionen auf einen manuell gesetzten Bereich.
     */
    protected function ClampColorTemperatureKelvinToConfiguredRange(int $kelvin): int
    {
        $range = $this->ReadColorTemperaturePresentationOverrideRange();
        if ($range === null) {
            return $kelvin;
        }

        return max($range[0], min($range[1], $kelvin));
    }

    /**
     * Liest den optionalen Kelvin-Override aus der Instanzkonfiguration.
     */
    private function ReadColorTemperaturePresentationOverrideRange(): ?array
    {
        try {
            $minKelvin = $this->ReadPropertyIntegerSafe(self::PROPERTY_COLOR_TEMPERATURE_PRESENTATION_MIN, 0);
            $maxKelvin = $this->ReadPropertyIntegerSafe(self::PROPERTY_COLOR_TEMPERATURE_PRESENTATION_MAX, 0);
        } catch (\Throwable) {
            return null;
        }

        if ($minKelvin <= 0 || $maxKelvin <= 0) {
            return null;
        }

        return $this->NormalizeColorTemperaturePresentationRange($minKelvin, $maxKelvin);
    }

    /**
     * Normalisiert Kelvin-Grenzen fuer die Darstellung.
     */
    private function NormalizeColorTemperaturePresentationRange(int $minKelvin, int $maxKelvin): array
    {
        if ($minKelvin > $maxKelvin) {
            [$minKelvin, $maxKelvin] = [$maxKelvin, $minKelvin];
        }
        if ($minKelvin === $maxKelvin) {
            $maxKelvin = $minKelvin + 1;
        }

        return [$minKelvin, $maxKelvin];
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
    protected function ApplyNumericFeaturePresentation(string $ident, array $feature, ?string $groupType = null): void
    {
        if ($this->ShouldSuppressNumericSliderPresentation($ident, $feature)) {
            $this->ResetCustomPresentation($ident);
            return;
        }

        if ($this->IsThermometerValue($ident, $feature, $groupType)) {
            $this->ApplyTemperatureValuePresentation($ident, $feature);
            return;
        }

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

        if ($this->IsRoomTemperatureSetpoint($ident, $feature, $groupType) && \defined('VARIABLE_TEMPLATE_SLIDER_ROOM_TEMPERATURE')) {
            $presentation['TEMPLATE'] = \constant('VARIABLE_TEMPLATE_SLIDER_ROOM_TEMPERATURE');
        }

        $this->ApplySliderPresentation($ident, $presentation);
    }

    /**
     * Erkennt Solltemperaturen, die in Symcon als Raumtemperatur-Slider dargestellt werden sollen.
     */
    private function IsRoomTemperatureSetpoint(string $ident, array $feature, ?string $groupType = null): bool
    {
        $effectiveGroupType = $groupType ?? ($feature['group_type'] ?? null);
        $property = (string) ($feature['property'] ?? $ident);

        if ($effectiveGroupType !== 'climate') {
            return false;
        }

        return \in_array($property, [
            'occupied_heating_setpoint',
            'current_heating_setpoint',
            'occupied_cooling_setpoint'
        ], true);
    }

    /**
     * Erkennt reine Thermometerwerte, die als Symcon-Temperatur-Wertkachel dargestellt werden sollen.
     */
    private function IsThermometerValue(string $ident, array $feature, ?string $groupType = null): bool
    {
        $effectiveGroupType = $groupType ?? ($feature['group_type'] ?? null);
        $property = (string) ($feature['property'] ?? $ident);
        $unit = isset($feature['unit']) && \is_string($feature['unit']) ? $feature['unit'] : '';

        if (($feature['type'] ?? '') !== 'numeric') {
            return false;
        }
        if ($effectiveGroupType === 'climate') {
            return false;
        }
        if ($property !== 'temperature') {
            return false;
        }
        if ($unit !== '' && $unit !== '°C') {
            return false;
        }

        return !isset($feature['access']) || (((int) $feature['access'] & 2) !== 2);
    }

    /**
     * Setzt fuer Cover-Positionswerte die native Symcon-Rollladen-Darstellung.
     */
    protected function ApplyShutterFeaturePresentation(string $ident, array $feature, ?string $groupType = null): bool
    {
        $effectiveGroupType = $groupType ?? ($feature['group_type'] ?? null);
        $property = (string) ($feature['property'] ?? $ident);

        if ($effectiveGroupType !== 'cover' || !\in_array($property, ['position', 'position_left', 'position_right'], true)) {
            return false;
        }
        if (($feature['type'] ?? '') !== 'numeric') {
            return false;
        }
        if (!$this->SupportsShutterPresentation()) {
            return false;
        }

        $variableID = $this->GetObjectIDByIdent($ident);
        if ($variableID === false) {
            return true;
        }

        $min = isset($feature['value_min']) ? (float) $feature['value_min'] : 0.0;
        $max = isset($feature['value_max']) ? (float) $feature['value_max'] : 100.0;

        IPS_SetVariableCustomPresentation($variableID, [
            'PRESENTATION'        => \constant('VARIABLE_PRESENTATION_SHUTTER'),
            'USAGE_TYPE'          => 0,
            'OPEN_OUTSIDE_VALUE'  => $max,
            'CLOSE_INSIDE_VALUE'  => $min,
            'SUN_POSITION'        => 2
        ]);

        return true;
    }

    /**
     * Unterdrueckt Slider fuer Werte, die in Spezialkacheln gezielter bedient werden.
     */
    private function ShouldSuppressNumericSliderPresentation(string $ident, array $feature): bool
    {
        $property = (string) ($feature['property'] ?? $ident);
        return \in_array($property, ['temperature_calibration', 'local_temperature_calibration'], true);
    }

    /**
     * Entfernt eine zuvor gesetzte Custom Presentation.
     */
    private function ResetCustomPresentation(string $ident): void
    {
        if (!$this->SupportsCustomPresentation()) {
            return;
        }

        $variableID = $this->GetObjectIDByIdent($ident);
        if ($variableID === false) {
            return;
        }

        $variable = IPS_GetVariable($variableID);
        if (!isset($variable['VariableCustomPresentation']) || !\is_array($variable['VariableCustomPresentation']) || count($variable['VariableCustomPresentation']) === 0) {
            return;
        }

        IPS_SetVariableCustomPresentation($variableID, []);
    }

    /**
     * Setzt fuer reine Temperaturmesswerte die native Symcon-Temperatur-Wertanzeige.
     */
    private function ApplyTemperatureValuePresentation(string $ident, array $feature): void
    {
        if (!$this->SupportsValuePresentation()) {
            return;
        }

        $variableID = $this->GetObjectIDByIdent($ident);
        if ($variableID === false) {
            return;
        }

        [$min, $max] = $this->GetTemperaturePresentationRange($feature);

        $unit = isset($feature['unit']) && \is_string($feature['unit']) ? $feature['unit'] : '°C';
        IPS_SetVariableCustomPresentation($variableID, [
            'PRESENTATION'        => \constant('VARIABLE_PRESENTATION_VALUE_PRESENTATION'),
            'ICON'                => 'temperature-half',
            'COLOR'               => -1,
            'PREFIX'              => '',
            'SUFFIX'              => $unit === '' ? ' °C' : ' ' . $unit,
            'USAGE_TYPE'          => 1,
            'PERCENTAGE'          => false,
            'MIN'                 => $min,
            'MAX'                 => $max,
            'THOUSANDS_SEPARATOR' => '',
            'DIGITS'              => isset($feature['value_step']) ? $this->GetDigitsFromStepSize((float) $feature['value_step']) : 1,
            'DECIMAL_SEPARATOR'   => 'Client',
            'INTERVALS_ACTIVE'    => false,
            'INTERVALS'           => '[]'
        ]);
    }

    /**
     * Liefert den Temperaturbereich aus dem Expose oder aus dem konfigurierten Fallback.
     */
    private function GetTemperaturePresentationRange(array $feature): array
    {
        if (isset($feature['value_min'], $feature['value_max'])) {
            $min = (float) $feature['value_min'];
            $max = (float) $feature['value_max'];
        } else {
            $min = $this->ReadTemperaturePresentationFallback(self::PROPERTY_TEMPERATURE_PRESENTATION_FALLBACK_MIN, -40.0);
            $max = $this->ReadTemperaturePresentationFallback(self::PROPERTY_TEMPERATURE_PRESENTATION_FALLBACK_MAX, 80.0);
        }

        if ($min > $max) {
            [$min, $max] = [$max, $min];
        }
        if ($min === $max) {
            $max = $min + 1.0;
        }

        return [$min, $max];
    }

    /**
     * Liest Fallback-Grenzen robust, damit bestehende Instanzen beim Modulupdate weiterlaufen.
     */
    private function ReadTemperaturePresentationFallback(string $property, float $default): float
    {
        try {
            return $this->ReadPropertyFloatSafe($property, $default);
        } catch (\Throwable) {
            return $default;
        }
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
     * Setzt eine moderne Schalter-Darstellung nur fuer echte Standard-Schalter.
     */
    protected function ApplyBinaryFeaturePresentation(string $ident, array $feature): void
    {
        if (!$this->SupportsSwitchPresentation()) {
            return;
        }
        if (!isset($feature['access']) || (((int) $feature['access'] & 2) !== 2)) {
            return;
        }

        $variableID = $this->GetObjectIDByIdent($ident);
        if ($variableID === false) {
            return;
        }

        $variable = IPS_GetVariable($variableID);
        $profileName = $variable['VariableCustomProfile'] !== '' ? $variable['VariableCustomProfile'] : $variable['VariableProfile'];
        if ($profileName !== '~Switch') {
            return;
        }

        IPS_SetVariableCustomPresentation($variableID, [
            'PRESENTATION'     => \constant('VARIABLE_PRESENTATION_SWITCH'),
            'USE_ICON_FALSE'   => true,
            'ICON_TRUE'        => $this->GetBinaryPresentationIcon($feature, true),
            'ICON_FALSE'       => $this->GetBinaryPresentationIcon($feature, false),
            'GLOW_COLOR'       => $this->GetBinaryGlowColor($feature),
            'GLOW_INTENSITY'   => 60,
            'USAGE_TYPE'       => 0
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
     * Prueft, ob Schalter-Darstellungen verfuegbar sind.
     */
    private function SupportsSwitchPresentation(): bool
    {
        return \function_exists('IPS_SetVariableCustomPresentation') && \defined('VARIABLE_PRESENTATION_SWITCH');
    }

    /**
     * Prueft, ob Wertanzeige-Darstellungen verfuegbar sind.
     */
    private function SupportsValuePresentation(): bool
    {
        return \function_exists('IPS_SetVariableCustomPresentation') && \defined('VARIABLE_PRESENTATION_VALUE_PRESENTATION');
    }

    /**
     * Prueft, ob Rollladen-Darstellungen verfuegbar sind.
     */
    private function SupportsShutterPresentation(): bool
    {
        return \function_exists('IPS_SetVariableCustomPresentation') && \defined('VARIABLE_PRESENTATION_SHUTTER');
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
     * Ermittelt Icons fuer Standard-Schalter.
     */
    private function GetBinaryPresentationIcon(array $feature, bool $active): string
    {
        $property = (string) ($feature['property'] ?? '');

        if (strpos($property, 'boost') !== false || strpos($property, 'heating') !== false) {
            return 'flame';
        }

        return $active ? 'power' : 'power-off';
    }

    /**
     * Ermittelt die aktive Leuchtfarbe fuer Standard-Schalter.
     */
    private function GetBinaryGlowColor(array $feature): int
    {
        $property = (string) ($feature['property'] ?? '');

        if (strpos($property, 'boost') !== false || strpos($property, 'heating') !== false) {
            return 0xFF8A00;
        }

        return 0x00C853;
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
