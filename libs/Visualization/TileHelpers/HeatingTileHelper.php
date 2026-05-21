<?php

declare(strict_types=1);

namespace Zigbee2MQTT;

/**
 * Trait fuer eine HTML-SDK-Kachel fuer Heizungs-/Thermostatgeraete.
 */
trait HeatingTileHelper
{
    /**
     * Prueft, ob die Heizungs-Kachel aktiv verwendet werden soll.
     */
    protected function ShouldUseHeatingTile(): bool
    {
        return !$this->ReadPropertyBooleanSafe(self::PROPERTY_DISABLE_HEATING_TILE, false) && $this->HasHeatingTileCapabilities();
    }

    /**
     * Prueft, ob diese Instanz als Heizung/Thermostat dargestellt werden kann.
     */
    protected function HasHeatingTileCapabilities(): bool
    {
        if ($this->GetObjectIDByIdent('occupied_heating_setpoint') === false) {
            return false;
        }

        return $this->HasHeatingTileValveCapability();
    }

    /**
     * Reine Raumthermostate sollen die Symcon-Standardkachel nutzen.
     * Die eigene HTML-Kachel bleibt fuer Heizventile/TRVs mit Ventilstatus.
     */
    private function HasHeatingTileValveCapability(): bool
    {
        foreach ($this->GetHeatingTileValveIdents() as $ident) {
            if ($this->GetObjectIDByIdent($ident) !== false || $this->FindHeatingTileFeature($ident) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Liefert typische Zigbee2MQTT-Idents fuer Heizventile.
     */
    private function GetHeatingTileValveIdents(): array
    {
        return [
            'pi_heating_demand',
            'valve',
            'valve_position',
            'valve_state',
            'valve_adapt_status',
            'valve_adapt_process',
            'automatic_valve_adapt',
            'valve_detection',
            'valve_opening_degree',
            'valve_closing_degree',
            'valve_opening_limit_voltage',
            'valve_closing_limit_voltage',
            'valve_motor_running_voltage'
        ];
    }

    /**
     * Verarbeitet Aktionen der Heizungs-Kachel.
     */
    protected function HandleHeatingTileAction(string $ident, mixed $value): bool
    {
        switch ($ident) {
            case 'HeatingTile.SetSetpoint':
                $this->RequestAction(
                    'occupied_heating_setpoint',
                    $this->NormalizeHeatingTileNumericValue('occupied_heating_setpoint', $value)
                );
                return true;

            case 'HeatingTile.Action':
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
                if (!\in_array($targetIdent, $this->GetHeatingTileIdents(), true)) {
                    return true;
                }

                $variableID = $this->GetObjectIDByIdent($targetIdent);
                if ($variableID === false) {
                    return true;
                }

                $targetValue = $value['value'] ?? null;
                $variable = IPS_GetVariable($variableID);
                if ($variable['VariableType'] === VARIABLETYPE_BOOLEAN) {
                    $targetValue = $this->NormalizeHeatingTileBooleanValue($targetValue);
                } elseif ($variable['VariableType'] === VARIABLETYPE_INTEGER || $variable['VariableType'] === VARIABLETYPE_FLOAT) {
                    $targetValue = $this->NormalizeHeatingTileNumericValue($targetIdent, $targetValue);
                }

                $this->RequestAction($targetIdent, $targetValue);
                return true;

            case 'HeatingTile.Refresh':
                $this->UpdateHeatingTileValue();
                return true;
        }

        return false;
    }

    /**
     * Sendet den aktuellen Kachelzustand an die HTML-SDK-Visualisierung.
     */
    protected function UpdateHeatingTileValue(): void
    {
        if (!$this->ShouldUseHeatingTile()) {
            return;
        }

        $this->UpdateCustomTileVisualizationValue(json_encode(
            $this->BuildHeatingTileData(),
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
        ));
    }

    /**
     * Aktualisiert die Kachel nur, wenn ein relevanter Wert geaendert wurde.
     */
    protected function UpdateHeatingTileValueIfRelevant(string $ident): void
    {
        if (!\in_array($ident, $this->GetHeatingTileIdents(), true)) {
            return;
        }

        $this->UpdateHeatingTileValue();
    }

    /**
     * Baut die Datenstruktur fuer die HTML-Kachel.
     */
    protected function BuildHeatingTileData(): array
    {
        $values = [];
        $features = [];

        foreach ($this->GetHeatingTileIdents() as $ident) {
            $feature = $this->FindHeatingTileFeature($ident);
            if ($feature !== null) {
                $features[$ident] = $this->BuildHeatingTileFeatureData($feature);
            }

            $variableID = $this->GetObjectIDByIdent($ident);
            if ($variableID === false) {
                continue;
            }

            $rawValue = GetValue($variableID);
            $values[$ident] = [
                'available' => true,
                'value'     => $rawValue,
                'formatted' => $this->FormatHeatingTileValue($ident, $rawValue)
            ];
        }

        if (!isset($features['occupied_heating_setpoint'])) {
            $features['occupied_heating_setpoint'] = [
                'type'     => 'numeric',
                'unit'     => "\u{00B0}C",
                'min'      => 5,
                'max'      => 30,
                'step'     => 0.5,
                'digits'   => 1,
                'writable' => true
            ];
        }

        return [
            'type'     => 'heating',
            'name'     => IPS_GetName($this->InstanceID),
            'features' => $features,
            'values'   => $values,
            'presets'  => $this->GetHeatingTilePresetValues()
        ];
    }

    /**
     * Liefert die konfigurierten Solltemperatur-Presets fuer die Kachel.
     */
    private function GetHeatingTilePresetValues(): array
    {
        return [
            $this->ReadPropertyFloatSafe(self::PROPERTY_HEATING_TILE_PRESET_1, 18.0),
            $this->ReadPropertyFloatSafe(self::PROPERTY_HEATING_TILE_PRESET_2, 20.0),
            $this->ReadPropertyFloatSafe(self::PROPERTY_HEATING_TILE_PRESET_3, 22.0)
        ];
    }

    /**
     * Liefert alle Idents, welche die Heizungs-Kachel beobachten oder bedienen kann.
     */
    protected function GetHeatingTileIdents(): array
    {
        return [
            'occupied_heating_setpoint',
            'local_temperature',
            'local_temperature_calibration',
            'system_mode',
            'running_state',
            'pi_heating_demand',
            'setpoint_change_source',
            'operating_mode',
            'window_detection',
            'boost_heating',
            'remote_temperature',
            'child_lock',
            'display_brightness',
            'display_switch_on_duration',
            'display_orientation',
            'displayed_temperature',
            'valve_adapt_status',
            'automatic_valve_adapt',
            'valve_adapt_process',
            'error_state',
            'battery',
            'battery_low',
            'linkquality'
        ];
    }

    /**
     * Baut Feature-Metadaten fuer die HTML-Kachel.
     */
    private function BuildHeatingTileFeatureData(array $feature): array
    {
        $step = isset($feature['value_step']) ? (float) $feature['value_step'] : 1.0;
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
        if (isset($feature['value_step'])) {
            $data['step'] = $step;
            $data['digits'] = $this->GetHeatingTileDigitsFromStep($step);
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
    private function FindHeatingTileFeature(string $property): ?array
    {
        $exposes = $this->ReadAttributeArray(self::ATTRIBUTE_EXPOSES);
        foreach ($exposes as $expose) {
            $found = $this->FindHeatingTileFeatureRecursive($expose, $property);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * Rekursive Suche in gruppierten Exposes.
     */
    private function FindHeatingTileFeatureRecursive(array $feature, string $property): ?array
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

            $found = $this->FindHeatingTileFeatureRecursive($subFeature, $property);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * Normalisiert numerische Werte gemaess Expose-Grenzen.
     */
    private function NormalizeHeatingTileNumericValue(string $ident, mixed $value): float|int
    {
        $feature = $this->FindHeatingTileFeature($ident);
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
            $number = round($number, $this->GetHeatingTileDigitsFromStep($step));
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
    private function NormalizeHeatingTileBooleanValue(mixed $value): bool
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
    private function FormatHeatingTileValue(string $ident, mixed $value): string
    {
        if (\is_bool($value)) {
            return $value ? $this->Translate('On') : $this->Translate('Off');
        }

        $feature = $this->FindHeatingTileFeature($ident);
        if ($feature !== null && (($feature['type'] ?? '') === 'numeric')) {
            $step = isset($feature['value_step']) ? (float) $feature['value_step'] : 1.0;
            $digits = $this->GetHeatingTileDigitsFromStep($step);
            $unit = (string) ($feature['unit'] ?? '');
            return number_format((float) $value, $digits, ',', '.') . ($unit !== '' ? ' ' . $unit : '');
        }

        if (\is_string($value)) {
            return $this->TranslateHeatingTileValue($value);
        }

        return (string) $value;
    }

    /**
     * Liefert die Nachkommastellen passend zur Schrittweite.
     */
    private function GetHeatingTileDigitsFromStep(float $step): int
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
    private function TranslateHeatingTileValue(string $value): string
    {
        $labels = [
            'heat'                       => 'Heizen',
            'idle'                       => 'Inaktiv',
            'schedule'                   => 'Zeitplan',
            'manual'                     => 'Manuell',
            'pause'                      => 'Pause',
            'externally'                 => 'Extern',
            'standard_arrangement'       => 'Standard',
            'rotated_by_180_degrees'     => '180 Grad',
            'set_temperature'            => 'Solltemperatur',
            'measured_temperature'       => 'Isttemperatur',
            'none'                       => 'Keine',
            'ready_to_calibrate'         => 'Bereit',
            'calibration_in_progress'    => 'Kalibrierung',
            'error'                      => 'Fehler',
            'success'                    => 'Erfolgreich',
            'adapt'                      => 'Anpassen'
        ];

        return $labels[$value] ?? $value;
    }
}
