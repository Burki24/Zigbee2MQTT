<?php

declare(strict_types=1);

namespace Zigbee2MQTT;

/**
 * Trait mit Hilfsfunktionen fuer moderne Variablendarstellungen in der Tile-Visualisierung.
 */
trait VariablePresentationHelper
{
    /**
     * Erstellt eine passende Variablendarstellung fuer bekannte Expose-Typen.
     *
     * Die Darstellung wird ausschliesslich bei der Variablenerstellung an die
     * RegisterVariable*-Methoden uebergeben. Bestehende Variablen werden damit
     * nicht nachtraeglich geaendert.
     */
    protected function BuildFeaturePresentation(array $feature, ?string $groupType = null, string $profileName = ''): ?array
    {
        if (!isset($feature['type'])) {
            return null;
        }

        $shutterPresentation = $this->BuildShutterFeaturePresentation($feature, $groupType);
        if ($shutterPresentation !== null) {
            return $shutterPresentation;
        }

        switch ($feature['type']) {
            case 'binary':
                return $this->BuildBinaryFeaturePresentation($feature, $profileName);
            case 'numeric':
                return $this->BuildNumericFeaturePresentation($feature, $groupType);
            case 'enum':
                return $this->BuildEnumerationPresentation($feature);
        }

        return null;
    }

    /**
     * Erstellt fuer die Kelvin-Farbtemperaturvariable die moderne Variablendarstellung.
     *
     * Zigbee2MQTT liefert color_temp in Mired. Fuer die Bedienung in der Tile-Visu
     * wird die zusaetzliche color_temp_kelvin-Variable verwendet und der Bereich
     * aus den Mired-Grenzen des Exposes nach Kelvin umgerechnet.
     */
    protected function BuildColorTemperaturePresentation(array $feature): ?array
    {
        [$minKelvin, $maxKelvin] = $this->GetColorTemperaturePresentationRange($feature);

        return $this->BuildSliderPresentation([
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
     * Erstellt fuer Sekundenwerte eine lesbare Dauerdarstellung.
     */
    protected function BuildDurationPresentation(): ?array
    {
        if (!$this->SupportsDurationPresentation()) {
            return null;
        }

        return [
            'PRESENTATION'   => \constant('VARIABLE_PRESENTATION_DURATION'),
            'COUNTDOWN_TYPE' => 0,
            'FORMAT'         => 2,
            'MILLISECONDS'   => false
        ];
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
     * Erstellt eine generische Schieberegler-Darstellung.
     *
     * Die Methode ist bewusst als Basis fuer weitere Tile-Visualisierungen gedacht.
     */
    protected function BuildSliderPresentation(array $presentation): ?array
    {
        if (!$this->SupportsCustomPresentation()) {
            return null;
        }

        return array_merge([
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
        ], $presentation);
    }

    /**
     * Erstellt fuer numerische Exposes mit Wertebereich eine passende moderne Darstellung.
     *
     * Schreibbare Werte werden als Slider dargestellt. Reine Statuswerte mit
     * Wertebereich erhalten eine Wertdarstellung, damit dafuer kein eigenes
     * dynamisches Variablenprofil erzeugt werden muss.
     */
    protected function BuildNumericFeaturePresentation(array $feature, ?string $groupType = null): ?array
    {
        if ($this->ShouldSuppressNumericSliderPresentation($feature)) {
            return null;
        }

        if ($this->IsThermometerValue($feature, $groupType)) {
            return $this->BuildTemperatureValuePresentation($feature);
        }

        if (!isset($feature['value_min'], $feature['value_max'])) {
            return null;
        }
        if (!$this->IsWritableFeature($feature)) {
            return $this->BuildNumericValuePresentation($feature);
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

        if ($this->IsRoomTemperatureSetpoint($feature, $groupType) && \defined('VARIABLE_TEMPLATE_SLIDER_ROOM_TEMPERATURE')) {
            $presentation['TEMPLATE'] = \constant('VARIABLE_TEMPLATE_SLIDER_ROOM_TEMPERATURE');
        }

        return $this->BuildSliderPresentation($presentation);
    }

    /**
     * Erstellt fuer nicht schreibbare numerische Statuswerte eine native Wertdarstellung.
     */
    private function BuildNumericValuePresentation(array $feature): ?array
    {
        if (!$this->SupportsValuePresentation() || !isset($feature['value_min'], $feature['value_max'])) {
            return null;
        }

        $min = (float) $feature['value_min'];
        $max = (float) $feature['value_max'];
        if ($min > $max) {
            [$min, $max] = [$max, $min];
        }
        if ($min === $max) {
            $max = $min + 1.0;
        }

        $unit = isset($feature['unit']) && \is_string($feature['unit']) ? $feature['unit'] : '';
        return [
            'PRESENTATION'        => \constant('VARIABLE_PRESENTATION_VALUE_PRESENTATION'),
            'ICON'                => $this->GetNumericPresentationIcon($feature),
            'COLOR'               => -1,
            'PREFIX'              => '',
            'SUFFIX'              => $unit === '' ? '' : ' ' . $unit,
            'USAGE_TYPE'          => 0,
            'PERCENTAGE'          => $unit === '%',
            'MIN'                 => $min,
            'MAX'                 => $max,
            'THOUSANDS_SEPARATOR' => '',
            'DIGITS'              => isset($feature['value_step']) ? $this->GetDigitsFromStepSize((float) $feature['value_step']) : 0,
            'DECIMAL_SEPARATOR'   => 'Client',
            'INTERVALS_ACTIVE'    => false,
            'INTERVALS'           => '[]'
        ];
    }

    /**
     * Erkennt Solltemperaturen, die in Symcon als Raumtemperatur-Slider dargestellt werden sollen.
     */
    private function IsRoomTemperatureSetpoint(array $feature, ?string $groupType = null): bool
    {
        $effectiveGroupType = $groupType ?? ($feature['group_type'] ?? null);
        $property = (string) ($feature['property'] ?? '');

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
    private function IsThermometerValue(array $feature, ?string $groupType = null): bool
    {
        $effectiveGroupType = $groupType ?? ($feature['group_type'] ?? null);
        $property = (string) ($feature['property'] ?? '');
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

        return !$this->IsWritableFeature($feature);
    }

    /**
     * Erstellt fuer Cover-Positionswerte die native Symcon-Rollladen-Darstellung.
     */
    protected function BuildShutterFeaturePresentation(array $feature, ?string $groupType = null): ?array
    {
        $effectiveGroupType = $groupType ?? ($feature['group_type'] ?? null);
        $property = (string) ($feature['property'] ?? '');

        if ($effectiveGroupType !== 'cover' || !\in_array($property, ['position', 'position_left', 'position_right'], true)) {
            return null;
        }
        if (($feature['type'] ?? '') !== 'numeric') {
            return null;
        }
        if (!$this->SupportsShutterPresentation()) {
            return null;
        }

        $min = isset($feature['value_min']) ? (float) $feature['value_min'] : 0.0;
        $max = isset($feature['value_max']) ? (float) $feature['value_max'] : 100.0;

        return [
            'PRESENTATION'        => \constant('VARIABLE_PRESENTATION_SHUTTER'),
            'USAGE_TYPE'          => 0,
            'OPEN_OUTSIDE_VALUE'  => $max,
            'CLOSE_INSIDE_VALUE'  => $min,
            'SUN_POSITION'        => 2
        ];
    }

    /**
     * Unterdrueckt Slider fuer Werte, die in Spezialkacheln gezielter bedient werden.
     */
    private function ShouldSuppressNumericSliderPresentation(array $feature): bool
    {
        $property = (string) ($feature['property'] ?? '');
        return \in_array($property, ['temperature_calibration', 'local_temperature_calibration'], true);
    }

    /**
     * Erstellt fuer reine Temperaturmesswerte die native Symcon-Temperatur-Wertanzeige.
     */
    private function BuildTemperatureValuePresentation(array $feature): ?array
    {
        if (!$this->SupportsValuePresentation()) {
            return null;
        }

        [$min, $max] = $this->GetTemperaturePresentationRange($feature);

        $unit = isset($feature['unit']) && \is_string($feature['unit']) ? $feature['unit'] : '°C';
        return [
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
        ];
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
     * Erstellt fuer Enum-Exposes eine passende native Darstellung.
     *
     * Beschreibbare Enums erhalten die interaktive Aufzaehlung. Reine
     * Statuswerte verwenden dagegen die normale Wertanzeige, weil die
     * Aufzaehlungsdarstellung in Symcon eine Variablenaktion voraussetzt.
     */
    protected function BuildEnumerationPresentation(array $feature): ?array
    {
        if (!isset($feature['values']) || !\is_array($feature['values'])) {
            return null;
        }

        $options = $this->BuildEnumerationPresentationOptions($feature['values']);
        if ($this->IsWritableFeature($feature)) {
            return $this->BuildWritableEnumerationPresentation($feature, $options);
        }

        return $this->BuildReadOnlyEnumerationPresentation($feature, $options);
    }

    /**
     * Erstellt die Optionsliste fuer Enum-Darstellungen.
     */
    private function BuildEnumerationPresentationOptions(array $values): array
    {
        $options = [];
        foreach ($values as $value) {
            $options[] = [
                'Value'      => (string) $value,
                'Caption'    => $this->CreatePresentationCaption((string) $value),
                'IconActive' => false,
                'IconValue'  => '',
                'Color'      => -1
            ];
        }

        return $options;
    }

    /**
     * Erstellt fuer beschreibbare Enums die interaktive Aufzaehlung.
     */
    private function BuildWritableEnumerationPresentation(array $feature, array $options): ?array
    {
        if (!$this->SupportsEnumerationPresentation()) {
            return null;
        }

        return [
            'PRESENTATION' => \constant('VARIABLE_PRESENTATION_ENUMERATION'),
            'OPTIONS'      => json_encode($options),
            'LAYOUT'       => count($options) <= 3 ? 1 : 0,
            'DISPLAY'      => 0,
            'ICON'         => $this->GetEnumerationPresentationIcon($feature)
        ];
    }

    /**
     * Erstellt fuer nicht beschreibbare Enums eine reine Wertanzeige.
     */
    private function BuildReadOnlyEnumerationPresentation(array $feature, array $options): ?array
    {
        if (!$this->SupportsValuePresentation()) {
            return null;
        }

        return [
            'PRESENTATION' => \constant('VARIABLE_PRESENTATION_VALUE_PRESENTATION'),
            'ICON'         => $this->GetEnumerationPresentationIcon($feature),
            'COLOR'        => -1,
            'PREFIX'       => '',
            'SUFFIX'       => '',
            'OPTIONS'      => json_encode($options)
        ];
    }

    /**
     * Erstellt eine moderne Schalter-Darstellung nur fuer echte Standard-Schalter.
     */
    protected function BuildBinaryFeaturePresentation(array $feature, string $profileName = ''): ?array
    {
        if (!$this->SupportsSwitchPresentation()) {
            return null;
        }
        if (!$this->IsWritableFeature($feature)) {
            return null;
        }

        if ($profileName !== '~Switch' && ($profileName !== '' || !$this->IsStandardBinaryPresentationFeature($feature))) {
            return null;
        }

        return [
            'PRESENTATION'     => \constant('VARIABLE_PRESENTATION_SWITCH'),
            'USE_ICON_FALSE'   => true,
            'ICON_TRUE'        => $this->GetBinaryPresentationIcon($feature, true),
            'ICON_FALSE'       => $this->GetBinaryPresentationIcon($feature, false),
            'GLOW_COLOR'       => $this->GetBinaryGlowColor($feature),
            'GLOW_INTENSITY'   => 60,
            'USAGE_TYPE'       => 0
        ];
    }

    /**
     * Prueft, ob ein Expose durch den Anwender beschreibbar ist.
     */
    private function IsWritableFeature(array $feature): bool
    {
        return isset($feature['access']) && (((int) $feature['access'] & 2) === 2);
    }

    /**
     * Erkennt einfache ON/OFF- und true/false-Schalter ohne semantisches Sonderprofil.
     */
    private function IsStandardBinaryPresentationFeature(array $feature): bool
    {
        if (!isset($feature['value_on'], $feature['value_off'])) {
            return true;
        }

        $valueOn = $feature['value_on'];
        $valueOff = $feature['value_off'];

        if (\is_bool($valueOn) && \is_bool($valueOff)) {
            return $valueOn !== $valueOff;
        }
        if (!\is_string($valueOn) || !\is_string($valueOff)) {
            return false;
        }

        $normalizedOn = strtoupper($valueOn);
        $normalizedOff = strtoupper($valueOff);

        return ($normalizedOn === 'ON' && $normalizedOff === 'OFF')
            || ($normalizedOn === 'TRUE' && $normalizedOff === 'FALSE');
    }

    /**
     * Prueft, ob die laufende Symcon-Version moderne Variablendarstellungen unterstuetzt.
     */
    private function SupportsCustomPresentation(): bool
    {
        return \defined('VARIABLE_PRESENTATION_SLIDER');
    }

    /**
     * Prueft, ob Aufzaehlungs-Darstellungen verfuegbar sind.
     */
    private function SupportsEnumerationPresentation(): bool
    {
        return \defined('VARIABLE_PRESENTATION_ENUMERATION');
    }

    /**
     * Prueft, ob Schalter-Darstellungen verfuegbar sind.
     */
    private function SupportsSwitchPresentation(): bool
    {
        return \defined('VARIABLE_PRESENTATION_SWITCH');
    }

    /**
     * Prueft, ob Wertanzeige-Darstellungen verfuegbar sind.
     */
    private function SupportsValuePresentation(): bool
    {
        return \defined('VARIABLE_PRESENTATION_VALUE_PRESENTATION');
    }

    /**
     * Prueft, ob Rollladen-Darstellungen verfuegbar sind.
     */
    private function SupportsShutterPresentation(): bool
    {
        return \defined('VARIABLE_PRESENTATION_SHUTTER');
    }

    /**
     * Prueft, ob Dauerdarstellungen verfuegbar sind.
     */
    private function SupportsDurationPresentation(): bool
    {
        return \defined('VARIABLE_PRESENTATION_DURATION');
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
