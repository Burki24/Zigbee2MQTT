<?php

declare(strict_types=1);

namespace Zigbee2MQTT;

/**
 * Trait fuer eine HTML-SDK-Kachel fuer Temperaturfuehler mit Einstellungen.
 */
trait TemperatureTileHelper
{
    /**
     * Prueft, ob die Temperatur-Kachel aktiv verwendet werden soll.
     */
    protected function ShouldUseTemperatureTile(): bool
    {
        return !$this->ReadPropertyBoolean(self::PROPERTY_DISABLE_TEMPERATURE_TILE) && $this->HasTemperatureTileCapabilities();
    }

    /**
     * Prueft, ob diese Instanz als reine Temperatur-Kachel dargestellt werden kann.
     */
    protected function HasTemperatureTileCapabilities(): bool
    {
        if ($this->GetObjectIDByIdent('temperature') === false || $this->GetObjectIDByIdent('occupied_heating_setpoint') !== false) {
            return false;
        }

        foreach ($this->GetTemperatureTileControlIdents() as $ident) {
            if ($this->GetObjectIDByIdent($ident) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verarbeitet Aktionen der Temperatur-Kachel.
     */
    protected function HandleTemperatureTileAction(string $ident, mixed $value): bool
    {
        switch ($ident) {
            case 'TemperatureTile.Action':
                if (\is_string($value)) {
                    $decoded = json_decode($value, true);
                    if (\is_array($decoded)) {
                        $value = $decoded;
                    }
                }

                if (!\is_array($value)) {
                    return true;
                }

                $targetIdent = (string) ($value['ident'] ?? '');
                if (!\in_array($targetIdent, $this->GetTemperatureTileIdents(), true)) {
                    return true;
                }

                $variableID = $this->GetObjectIDByIdent($targetIdent);
                if ($variableID === false) {
                    return true;
                }

                $targetValue = $value['value'] ?? null;
                $variable = IPS_GetVariable($variableID);
                if ($variable['VariableType'] === VARIABLETYPE_BOOLEAN) {
                    $targetValue = $this->NormalizeTemperatureTileBooleanValue($targetValue);
                } elseif ($variable['VariableType'] === VARIABLETYPE_INTEGER || $variable['VariableType'] === VARIABLETYPE_FLOAT) {
                    $targetValue = $this->NormalizeTemperatureTileNumericValue($targetIdent, $targetValue);
                }

                $this->RequestAction($targetIdent, $targetValue);
                return true;

            case 'TemperatureTile.Refresh':
                $this->UpdateTemperatureTileValue();
                return true;
        }

        return false;
    }

    /**
     * Sendet den aktuellen Kachelzustand an die HTML-SDK-Visualisierung.
     */
    protected function UpdateTemperatureTileValue(): void
    {
        if (!$this->ShouldUseTemperatureTile()) {
            return;
        }

        $this->UpdateVisualizationValue(json_encode(
            $this->BuildTemperatureTileData(),
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
        ));
    }

    /**
     * Aktualisiert die Kachel nur, wenn ein relevanter Wert geaendert wurde.
     */
    protected function UpdateTemperatureTileValueIfRelevant(string $ident): void
    {
        if (!\in_array($ident, $this->GetTemperatureTileIdents(), true)) {
            return;
        }

        $this->UpdateTemperatureTileValue();
    }

    /**
     * Baut die Datenstruktur fuer die HTML-Kachel.
     */
    protected function BuildTemperatureTileData(): array
    {
        $values = [];
        $features = [];

        foreach ($this->GetTemperatureTileIdents() as $ident) {
            $feature = $this->FindTemperatureTileFeature($ident);
            if ($feature !== null) {
                $features[$ident] = $this->BuildTemperatureTileFeatureData($feature);
            }

            $variableID = $this->GetObjectIDByIdent($ident);
            if ($variableID === false) {
                continue;
            }

            $rawValue = GetValue($variableID);
            $values[$ident] = [
                'available' => true,
                'value'     => $rawValue,
                'formatted' => $this->FormatTemperatureTileValue($ident, $rawValue)
            ];
        }

        if (!isset($features['temperature'])) {
            $features['temperature'] = [
                'type'     => 'numeric',
                'unit'     => "\u{00B0}C",
                'min'      => -30,
                'max'      => 50,
                'step'     => 0.1,
                'digits'   => 1,
                'writable' => false
            ];
        }

        return [
            'type'     => 'temperature',
            'name'     => IPS_GetName($this->InstanceID),
            'features' => $features,
            'values'   => $values
        ];
    }

    /**
     * Liefert alle Idents, welche die Temperatur-Kachel beobachten oder bedienen kann.
     */
    protected function GetTemperatureTileIdents(): array
    {
        return array_values(array_unique(array_merge(['temperature'], $this->GetTemperatureTileSettingIdents())));
    }

    /**
     * Liefert alle Einstellungs-Idents der Temperatur-Kachel.
     */
    private function GetTemperatureTileSettingIdents(): array
    {
        return array_merge($this->GetTemperatureTileControlIdents(), [
            'battery',
            'battery_low',
            'linkquality',
            'last_seen'
        ]);
    }

    /**
     * Liefert alle bedienbaren Temperatur-Einstellungs-Idents.
     */
    private function GetTemperatureTileControlIdents(): array
    {
        return [
            'temperature_calibration',
            'temperature_unit',
            'temperature_units',
            'temperature_unit_convert'
        ];
    }

    /**
     * Baut Feature-Metadaten fuer die HTML-Kachel.
     */
    private function BuildTemperatureTileFeatureData(array $feature): array
    {
        $step = isset($feature['value_step']) ? (float) $feature['value_step'] : 0.0;
        $data = [
            'type'     => (string) ($feature['type'] ?? ''),
            'unit'     => (string) ($feature['unit'] ?? ''),
            'writable' => (((int) ($feature['access'] ?? 0)) & 2) > 0
        ];

        if (isset($feature['value_min'])) {
            $data['min'] = (float) $feature['value_min'];
        }
        if (isset($feature['value_max'])) {
            $data['max'] = (float) $feature['value_max'];
        }
        if ($step > 0) {
            $data['step'] = $step;
            $data['digits'] = $this->GetTemperatureTileDigitsFromStep($step);
        } elseif (($data['unit'] === "\u{00B0}C") || strpos((string) ($feature['property'] ?? ''), 'temperature') !== false) {
            $data['step'] = 0.1;
            $data['digits'] = 1;
        }
        if (isset($feature['values']) && \is_array($feature['values'])) {
            $data['values'] = array_values($feature['values']);
        }
        if (isset($feature['value_on'])) {
            $data['value_on'] = $feature['value_on'];
        }
        if (isset($feature['value_off'])) {
            $data['value_off'] = $feature['value_off'];
        }

        return $data;
    }

    /**
     * Sucht ein Expose-Feature anhand seiner Property.
     */
    private function FindTemperatureTileFeature(string $property): ?array
    {
        $exposes = $this->ReadAttributeArray(self::ATTRIBUTE_EXPOSES);
        foreach ($exposes as $expose) {
            $found = $this->FindTemperatureTileFeatureRecursive($expose, $property);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * Rekursive Suche in gruppierten Exposes.
     */
    private function FindTemperatureTileFeatureRecursive(array $feature, string $property): ?array
    {
        if (($feature['property'] ?? '') === $property) {
            return $feature;
        }

        if (!isset($feature['features']) || !\is_array($feature['features'])) {
            return null;
        }

        foreach ($feature['features'] as $subFeature) {
            if (!\is_array($subFeature)) {
                continue;
            }

            $found = $this->FindTemperatureTileFeatureRecursive($subFeature, $property);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * Normalisiert numerische Werte gemaess Expose-Grenzen.
     */
    private function NormalizeTemperatureTileNumericValue(string $ident, mixed $value): float|int
    {
        $feature = $this->FindTemperatureTileFeature($ident);
        $number = (float) $value;
        $min = isset($feature['value_min']) ? (float) $feature['value_min'] : null;
        $max = isset($feature['value_max']) ? (float) $feature['value_max'] : null;
        $step = isset($feature['value_step']) ? (float) $feature['value_step'] : 0.0;

        if ($min !== null && $number < $min) {
            $number = $min;
        }
        if ($max !== null && $number > $max) {
            $number = $max;
        }
        if ($step > 0) {
            $base = $min ?? 0.0;
            $number = $base + (round(($number - $base) / $step) * $step);
            $number = round($number, $this->GetTemperatureTileDigitsFromStep($step));
        }

        $variableID = $this->GetObjectIDByIdent($ident);
        if ($variableID !== false && IPS_GetVariable($variableID)['VariableType'] === VARIABLETYPE_INTEGER) {
            return (int) round($number);
        }

        return $number;
    }

    /**
     * Normalisiert boolesche Werte aus der HTML-Kachel.
     */
    private function NormalizeTemperatureTileBooleanValue(mixed $value): bool
    {
        if (\is_bool($value)) {
            return $value;
        }
        if (\is_string($value)) {
            return \in_array(strtolower($value), ['1', 'true', 'on', 'yes', 'lock'], true);
        }

        return (bool) $value;
    }

    /**
     * Formatiert einen Kachelwert.
     */
    private function FormatTemperatureTileValue(string $ident, mixed $value): string
    {
        if (\is_bool($value)) {
            return $value ? $this->Translate('On') : $this->Translate('Off');
        }

        if ($ident === 'last_seen') {
            return \date('d.m.Y H:i:s', (int) $value);
        }

        $feature = $this->FindTemperatureTileFeature($ident);
        if ($feature !== null && (($feature['type'] ?? '') === 'numeric')) {
            $unit = (string) ($feature['unit'] ?? '');
            $digits = $this->GetTemperatureTileDigits($ident, $feature);
            return number_format((float) $value, $digits, ',', '.') . ($unit !== '' ? ' ' . $unit : '');
        }

        if (\is_string($value)) {
            return $this->TranslateTemperatureTileValue($value);
        }

        return (string) $value;
    }

    /**
     * Ermittelt die Anzeige-Nachkommastellen fuer einen Temperatur-Kachelwert.
     */
    private function GetTemperatureTileDigits(string $ident, ?array $feature): int
    {
        if ($feature !== null && isset($feature['value_step'])) {
            return $this->GetTemperatureTileDigitsFromStep((float) $feature['value_step']);
        }

        $unit = (string) ($feature['unit'] ?? '');
        if ($unit === "\u{00B0}C" || strpos($ident, 'temperature') !== false) {
            return 1;
        }
        if ($unit === '%' || $unit === 'lqi') {
            return 0;
        }

        return 2;
    }

    /**
     * Liefert die Nachkommastellen passend zur Schrittweite.
     */
    private function GetTemperatureTileDigitsFromStep(float $step): int
    {
        if ($step <= 0) {
            return 0;
        }

        $text = rtrim(rtrim(sprintf('%.6F', $step), '0'), '.');
        $pos = strpos($text, '.');
        return $pos === false ? 0 : strlen($text) - $pos - 1;
    }

    /**
     * Einfache lesbare Bezeichnungen fuer bekannte Enum-Werte.
     */
    private function TranslateTemperatureTileValue(string $value): string
    {
        $labels = [
            'celsius'    => 'Celsius',
            'fahrenheit' => 'Fahrenheit',
            'C'          => 'Celsius',
            'F'          => 'Fahrenheit'
        ];

        return $labels[$value] ?? $value;
    }
}
