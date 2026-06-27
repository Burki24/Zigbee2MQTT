<?php

declare(strict_types=1);

namespace Zigbee2MQTT;

/**
 * Trait fuer eine HTML-SDK-Kachel fuer Sensoren mit Messwerten und Status.
 */
trait SensorTileHelper
{
    /**
     * Prueft, ob die Sensor-Kachel aktiv verwendet werden soll.
     */
    protected function ShouldUseSensorTile(): bool
    {
        if (!$this->HasSensorTileCapabilities()) {
            return false;
        }

        return $this->ShouldForceSensorTile() || !$this->HasSensorTileActuatorExposeGroup();
    }

    /**
     * Prueft, ob diese Instanz Sensorwerte fuer die Sensor-Kachel besitzt.
     */
    protected function HasSensorTileCapabilities(): bool
    {
        if ($this->GetObjectIDByIdent('occupied_heating_setpoint') !== false) {
            return false;
        }

        foreach ($this->GetSensorTilePrimaryIdents() as $ident) {
            if ($this->GetObjectIDByIdent($ident) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Prueft, ob die Sensor-Kachel bewusst als Visualisierung gewaehlt wurde.
     */
    protected function ShouldForceSensorTile(): bool
    {
        return $this->ReadPropertyBooleanSafe(self::PROPERTY_USE_SENSOR_TILE, false) && $this->HasSensorTileCapabilities();
    }

    /**
     * Verarbeitet Aktionen der Sensor-Kachel.
     */
    protected function HandleSensorTileAction(string $ident, mixed $value): bool
    {
        switch ($ident) {
            case 'SensorTile.Action':
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
                if (!\in_array($targetIdent, $this->GetSensorTileIdents(), true)) {
                    return true;
                }

                $variableID = $this->GetObjectIDByIdent($targetIdent);
                if ($variableID === false) {
                    return true;
                }

                $targetValue = $value['value'] ?? null;
                $variable = IPS_GetVariable($variableID);
                if ($variable['VariableType'] === VARIABLETYPE_BOOLEAN) {
                    $targetValue = $this->NormalizeSensorTileBooleanValue($targetValue);
                } elseif ($variable['VariableType'] === VARIABLETYPE_INTEGER || $variable['VariableType'] === VARIABLETYPE_FLOAT) {
                    $targetValue = $this->NormalizeSensorTileNumericValue($targetIdent, $targetValue);
                }

                $this->RequestAction($targetIdent, $targetValue);
                return true;

            case 'SensorTile.Refresh':
                $this->UpdateSensorTileValue();
                return true;
        }

        return false;
    }

    /**
     * Sendet den aktuellen Kachelzustand an die HTML-SDK-Visualisierung.
     */
    protected function UpdateSensorTileValue(): void
    {
        if (!$this->ShouldUseSensorTile()) {
            return;
        }

        $this->UpdateCustomTileVisualizationValue(json_encode(
            $this->BuildSensorTileData(),
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
        ));
    }

    /**
     * Aktualisiert die Kachel nur, wenn ein relevanter Wert geaendert wurde.
     */
    protected function UpdateSensorTileValueIfRelevant(string $ident): void
    {
        if (!\in_array($ident, $this->GetSensorTileIdents(), true)) {
            return;
        }

        $this->UpdateSensorTileValue();
    }

    /**
     * Baut die Datenstruktur fuer die HTML-Kachel.
     */
    protected function BuildSensorTileData(): array
    {
        $values = [];
        $features = [];

        foreach ($this->GetSensorTileIdents() as $ident) {
            $feature = $this->FindSensorTileFeature($ident);
            if ($feature !== null) {
                $features[$ident] = $this->BuildSensorTileFeatureData($feature);
            }

            $variableID = $this->GetObjectIDByIdent($ident);
            if ($variableID === false) {
                continue;
            }

            $rawValue = GetValue($variableID);
            $values[$ident] = [
                'available' => true,
                'value'     => $rawValue,
                'formatted' => $this->FormatSensorTileValue($ident, $rawValue, $variableID)
            ];
        }

        if ($this->GetObjectIDByIdent('temperature') !== false && !isset($features['temperature'])) {
            [$min, $max] = $this->GetSensorTileTemperatureRange(null);
            $features['temperature'] = [
                'type'     => 'numeric',
                'unit'     => "\u{00B0}C",
                'min'      => $min,
                'max'      => $max,
                'step'     => 0.1,
                'digits'   => 1,
                'writable' => false
            ];
        }

        return [
            'type'     => 'sensor',
            'name'     => IPS_GetName($this->InstanceID),
            'features' => $features,
            'values'   => $values
        ];
    }

    /**
     * Liefert alle Idents, welche die Sensor-Kachel beobachten oder bedienen kann.
     */
    protected function GetSensorTileIdents(): array
    {
        return array_values(array_unique(array_merge($this->GetSensorTilePrimaryIdents(), $this->GetSensorTileSettingIdents())));
    }

    /**
     * Liefert alle Hauptwerte der Sensor-Kachel.
     */
    private function GetSensorTilePrimaryIdents(): array
    {
        return [
            'presence',
            'occupancy',
            'motion',
            'motion_state',
            'temperature',
            'humidity',
            'soil_moisture',
            'illuminance',
            'illuminance_lux'
        ];
    }

    /**
     * Schuetzt kombinierte Aktor-/Sensorgeraete davor, faelschlich als reine Sensor-Kachel zu gelten.
     */
    protected function HasSensorTileActuatorExposeGroup(): bool
    {
        foreach ($this->ReadAttributeArray(self::ATTRIBUTE_EXPOSES) as $expose) {
            if (!\is_array($expose)) {
                continue;
            }

            if ($this->IsSensorTileActuatorExposeGroup($expose)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Erkennt rekursiv Zigbee2MQTT-Gruppen, die eine eigene Aktor-/Standarddarstellung benoetigen.
     */
    private function IsSensorTileActuatorExposeGroup(array $feature): bool
    {
        if (isset($feature['type']) && \in_array((string) $feature['type'], ['light', 'switch', 'lock', 'cover', 'climate', 'fan'], true)) {
            return true;
        }

        if (!isset($feature['features']) || !\is_array($feature['features'])) {
            return false;
        }

        foreach ($feature['features'] as $subFeature) {
            if (!\is_array($subFeature)) {
                continue;
            }

            if ($this->IsSensorTileActuatorExposeGroup($subFeature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Liefert alle Einstellungs-Idents der Sensor-Kachel.
     */
    private function GetSensorTileSettingIdents(): array
    {
        return array_merge($this->GetSensorTileControlIdents(), [
            'target_distance',
            'track_target_distance',
            'no_occupancy_since',
            'battery',
            'battery_low',
            'linkquality',
            'last_seen'
        ]);
    }

    /**
     * Liefert alle bedienbaren Sensor-Einstellungs-Idents.
     */
    private function GetSensorTileControlIdents(): array
    {
        return [
            'temperature_calibration',
            'temperature_unit',
            'temperature_units',
            'temperature_unit_convert',
            'illuminance_calibration',
            'illuminance_interval',
            'illuminance_report',
            'motion_sensitivity',
            'move_sensitivity',
            'presence_sensitivity',
            'presence_threshold',
            'presence_detection_options',
            'occupancy_timeout',
            'motion_detection_distance',
            'motion_detection_sensitivity',
            'static_detection_sensitivity',
            'presence_keep_time',
            'radar_sensitivity',
            'detection_range',
            'shield_range',
            'entry_sensitivity',
            'entry_distance_indentation',
            'entry_filter_time',
            'departure_delay',
            'block_time',
            'breaker_status',
            'breaker_mode',
            'illuminance_threshold',
            'status_indication',
            'sensor',
            'detection_delay',
            'fading_time',
            'minimum_range',
            'maximum_range',
            'large_motion_detection_sensitivity',
            'large_motion_detection_distance',
            'medium_motion_detection_sensitivity',
            'medium_motion_detection_distance',
            'small_detection_sensitivity',
            'small_detection_distance',
            'ai_sensitivity_adaptive',
            'indicator',
            'led_indicator',
            'self_test'
        ];
    }

    /**
     * Baut Feature-Metadaten fuer die HTML-Kachel.
     */
    private function BuildSensorTileFeatureData(array $feature): array
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
        if (($feature['property'] ?? '') === 'temperature') {
            [$data['min'], $data['max']] = $this->GetSensorTileTemperatureRange($feature);
        }
        if ($step > 0) {
            $data['step'] = $step;
            $data['digits'] = $this->GetSensorTileDigitsFromStep($step);
        } elseif ($this->IsSensorTileTemperatureFeature($feature)) {
            $data['step'] = 0.1;
            $data['digits'] = 1;
        } elseif ($this->IsSensorTileIlluminanceFeature($feature)) {
            $data['step'] = 1;
            $data['digits'] = 0;
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
     * Liefert den Temperaturbereich aus dem Expose oder aus dem konfigurierbaren Fallback.
     */
    private function GetSensorTileTemperatureRange(?array $feature): array
    {
        $min = isset($feature['value_min'])
            ? (float) $feature['value_min']
            : $this->ReadSensorTileFallbackRange(self::PROPERTY_TEMPERATURE_PRESENTATION_FALLBACK_MIN, -40.0);
        $max = isset($feature['value_max'])
            ? (float) $feature['value_max']
            : $this->ReadSensorTileFallbackRange(self::PROPERTY_TEMPERATURE_PRESENTATION_FALLBACK_MAX, 80.0);

        if ($max <= $min) {
            $max = $min + 1.0;
        }

        return [$min, $max];
    }

    /**
     * Liest einen Fallback-Wert, ohne aeltere Instanzen mit fehlender Property zu stoeren.
     */
    private function ReadSensorTileFallbackRange(string $property, float $default): float
    {
        try {
            return $this->ReadPropertyFloatSafe($property, $default);
        } catch (\Throwable) {
            return $default;
        }
    }

    /**
     * Sucht ein Expose-Feature anhand seiner Property.
     */
    private function FindSensorTileFeature(string $property): ?array
    {
        $exposes = $this->ReadAttributeArray(self::ATTRIBUTE_EXPOSES);
        foreach ($exposes as $expose) {
            $found = $this->FindSensorTileFeatureRecursive($expose, $property);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * Rekursive Suche in gruppierten Exposes.
     */
    private function FindSensorTileFeatureRecursive(array $feature, string $property): ?array
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

            $found = $this->FindSensorTileFeatureRecursive($subFeature, $property);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * Normalisiert numerische Werte gemaess Expose-Grenzen.
     */
    private function NormalizeSensorTileNumericValue(string $ident, mixed $value): float|int
    {
        $feature = $this->FindSensorTileFeature($ident);
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
            $number = round($number, $this->GetSensorTileDigitsFromStep($step));
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
    private function NormalizeSensorTileBooleanValue(mixed $value): bool
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
    private function FormatSensorTileValue(string $ident, mixed $value, ?int $variableID = null): string
    {
        if (\is_bool($value)) {
            return $this->FormatSensorTileBooleanValue($ident, $value);
        }

        if ($ident === 'last_seen') {
            return \date('d.m.Y H:i:s', (int) $value);
        }

        $feature = $this->FindSensorTileFeature($ident);
        if ($feature !== null && (($feature['type'] ?? '') === 'numeric')) {
            $unit = (string) ($feature['unit'] ?? '');
            $digits = $this->GetSensorTileDigits($ident, $feature);
            return number_format((float) $value, $digits, ',', '.') . ($unit !== '' ? ' ' . $unit : '');
        }
        if ($variableID !== null) {
            $formatted = $this->GetValueFormattedSafe($variableID);
            if ($formatted !== null) {
                return $formatted;
            }
        }

        if (\is_string($value)) {
            return $this->TranslateSensorTileValue($value);
        }

        return (string) $value;
    }

    /**
     * Formatiert boolesche Sensorwerte mit sprechenden Statusmeldungen.
     */
    private function FormatSensorTileBooleanValue(string $ident, bool $value): string
    {
        return match ($ident) {
            'presence'  => $value ? 'Anwesend' : 'Abwesend',
            'occupancy',
            'motion'    => $value ? 'Bewegung erkannt' : 'Keine Bewegung',
            default     => $value ? $this->Translate('On') : $this->Translate('Off')
        };
    }

    /**
     * Ermittelt die Anzeige-Nachkommastellen fuer einen Sensor-Kachelwert.
     */
    private function GetSensorTileDigits(string $ident, ?array $feature): int
    {
        if ($feature !== null && isset($feature['value_step'])) {
            return $this->GetSensorTileDigitsFromStep((float) $feature['value_step']);
        }

        if ($feature !== null && $this->IsSensorTileTemperatureFeature($feature)) {
            return 1;
        }
        $unit = (string) ($feature['unit'] ?? '');
        if ($unit === '%' || $unit === 'lqi') {
            return 0;
        }
        if ($unit === 'lx' || strpos($ident, 'illuminance') !== false) {
            return 0;
        }

        return 2;
    }

    /**
     * Prueft auf Temperatur-Exposes.
     */
    private function IsSensorTileTemperatureFeature(array $feature): bool
    {
        return ($feature['unit'] ?? '') === "\u{00B0}C" || strpos((string) ($feature['property'] ?? ''), 'temperature') !== false;
    }

    /**
     * Prueft auf Beleuchtungsstaerke-Exposes.
     */
    private function IsSensorTileIlluminanceFeature(array $feature): bool
    {
        return ($feature['unit'] ?? '') === 'lx' || strpos((string) ($feature['property'] ?? ''), 'illuminance') !== false;
    }

    /**
     * Liefert die Nachkommastellen passend zur Schrittweite.
     */
    private function GetSensorTileDigitsFromStep(float $step): int
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
    private function TranslateSensorTileValue(string $value): string
    {
        $labels = [
            'celsius'    => 'Celsius',
            'fahrenheit' => 'Fahrenheit',
            'C'          => 'Celsius',
            'F'          => 'Fahrenheit',
            'low'        => 'Niedrig',
            'medium'     => 'Mittel',
            'high'       => 'Hoch',
            'none'       => 'Keine Bewegung',
            'small'      => 'Kleine Bewegung',
            'large'      => 'Grosse Bewegung',
            'far'        => 'Entfernt',
            'near'       => 'Nah',
            'both'       => 'Beide',
            'radar'      => 'Radar',
            'pir'        => 'PIR'
        ];

        return $labels[$value] ?? $value;
    }
}
