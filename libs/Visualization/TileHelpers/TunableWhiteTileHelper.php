<?php

declare(strict_types=1);

namespace Zigbee2MQTT;

/**
 * Trait fuer eine HTML-SDK-Kachel fuer dimmbare Tunable-White-Leuchten.
 */
trait TunableWhiteTileHelper
{
    /**
     * Prueft, ob die Tunable-White-Kachel aktiv verwendet werden soll.
     */
    protected function ShouldUseTunableWhiteTile(): bool
    {
        return !$this->ReadPropertyBooleanSafe(self::PROPERTY_DISABLE_TUNABLE_WHITE_TILE, false)
            && $this->HasTunableWhiteTileCapabilities();
    }

    /**
     * Prueft, ob Status und Farbtemperatur fuer die Kachel vorhanden sind.
     */
    protected function HasTunableWhiteTileCapabilities(): bool
    {
        $colorTemperatureFeature = $this->FindTunableWhiteTileFeature('color_temp');
        if ($colorTemperatureFeature === null) {
            return false;
        }
        if (isset($colorTemperatureFeature['access'])
            && (((int) $colorTemperatureFeature['access']) & 0b010) === 0
        ) {
            return false;
        }
        if (isset($colorTemperatureFeature['value_min'], $colorTemperatureFeature['value_max'])
            && (float) $colorTemperatureFeature['value_max'] <= (float) $colorTemperatureFeature['value_min']
        ) {
            return false;
        }

        return method_exists($this, 'GetVisualizationTile')
            && $this->GetObjectIDByIdent('state') !== false
            && $this->GetObjectIDByIdent('color_temp') !== false
            && $this->GetObjectIDByIdent('color_temp_kelvin') !== false
            && $colorTemperatureFeature !== null;
    }

    /**
     * Verarbeitet Aktionen der Tunable-White-Kachel.
     */
    protected function HandleTunableWhiteTileAction(string $ident, mixed $value): bool
    {
        switch ($ident) {
            case 'TunableWhiteTile.Toggle':
                $stateID = $this->GetObjectIDByIdent('state');
                if ($stateID !== false) {
                    $this->RequestAction('state', !((bool) \GetValue($stateID)));
                }
                return true;

            case 'TunableWhiteTile.SetBrightness':
                if ($this->GetObjectIDByIdent('brightness') === false || !\is_numeric($value)) {
                    return true;
                }
                $range = $this->GetTunableWhiteTileNumericRange('brightness', 0.0, 254.0, 1.0);
                $brightness = max($range['min'], min($range['max'], (float) $value));
                $this->RequestAction('brightness', $this->NormalizeTunableWhiteTileNumber($brightness, $range['step']));
                return true;

            case 'TunableWhiteTile.SetColorTemperature':
                if ($this->GetObjectIDByIdent('color_temp_kelvin') === false || !\is_numeric($value)) {
                    return true;
                }
                [$minimum, $maximum] = $this->GetTunableWhiteTileKelvinRange();
                $kelvin = max($minimum, min($maximum, (int) round((float) $value)));
                $this->RequestAction('color_temp_kelvin', $kelvin);
                return true;

            case 'TunableWhiteTile.SetPreset':
                if (!\is_numeric($value) || !$this->IsTunableWhiteTilePresetValue((float) $value)) {
                    return true;
                }
                $this->RequestAction('color_temp_presets', (int) round((float) $value));
                return true;

            case 'TunableWhiteTile.Refresh':
                $this->UpdateTunableWhiteTileValue();
                return true;
        }

        return false;
    }

    /**
     * Sendet den aktuellen Kachelzustand an die HTML-SDK-Visualisierung.
     */
    protected function UpdateTunableWhiteTileValue(): void
    {
        if (!$this->ShouldUseTunableWhiteTile()) {
            return;
        }

        $this->UpdateCustomTileVisualizationValue(json_encode(
            $this->BuildTunableWhiteTileData(),
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
        ));
    }

    /**
     * Aktualisiert die Kachel nur fuer relevante Variablen.
     */
    protected function UpdateTunableWhiteTileValueIfRelevant(string $ident): void
    {
        if (!\in_array($ident, $this->GetTunableWhiteTileIdents(), true)) {
            return;
        }

        $this->UpdateTunableWhiteTileValue();
    }

    /**
     * Baut die komplette Datenstruktur fuer die Tunable-White-Kachel.
     */
    protected function BuildTunableWhiteTileData(): array
    {
        $stateID = $this->GetObjectIDByIdent('state');
        $state = $stateID !== false && (bool) \GetValue($stateID);
        $brightness = $this->BuildTunableWhiteTileBrightnessData();
        $temperature = $this->BuildTunableWhiteTileColorTemperatureData();

        return [
            'type'             => 'tunableWhite',
            'name'             => \IPS_GetName($this->InstanceID),
            'labels'           => [
                'brightness'       => $this->Translate('Brightness'),
                'colorTemperature' => $this->Translate('Color Temperature'),
                'toggle'           => $this->Translate('Turn light on/off')
            ],
            'state'            => [
                'available' => $stateID !== false,
                'value'     => $state,
                'formatted' => $this->Translate($state ? 'On' : 'Off')
            ],
            'brightness'       => $brightness,
            'colorTemperature' => $temperature,
            'presets'          => $this->GetTunableWhiteTilePresets()
        ];
    }

    /**
     * Liefert Helligkeitswert, Bereich und Prozentanzeige.
     */
    private function BuildTunableWhiteTileBrightnessData(): array
    {
        $variableID = $this->GetObjectIDByIdent('brightness');
        $range = $this->GetTunableWhiteTileNumericRange('brightness', 0.0, 254.0, 1.0);
        if ($variableID === false) {
            return [
                'available' => false,
                'value'     => $range['min'],
                'percent'   => 0,
                'min'       => $range['min'],
                'max'       => $range['max'],
                'step'      => $range['step']
            ];
        }

        $value = (float) \GetValue($variableID);
        $span = $range['max'] - $range['min'];
        $percent = $span > 0.0 ? (int) round(($value - $range['min']) * 100 / $span) : 0;

        return [
            'available' => true,
            'value'     => $value,
            'percent'   => max(0, min(100, $percent)),
            'min'       => $range['min'],
            'max'       => $range['max'],
            'step'      => $range['step']
        ];
    }

    /**
     * Liefert aktuellen Kelvin- und Mired-Wert samt Darstellungsbereich.
     */
    private function BuildTunableWhiteTileColorTemperatureData(): array
    {
        $kelvinID = $this->GetObjectIDByIdent('color_temp_kelvin');
        $miredID = $this->GetObjectIDByIdent('color_temp');
        [$minimum, $maximum] = $this->GetTunableWhiteTileKelvinRange();

        return [
            'available' => $kelvinID !== false,
            'value'     => $kelvinID !== false ? (int) \GetValue($kelvinID) : $minimum,
            'mired'     => $miredID !== false ? (int) \GetValue($miredID) : 0,
            'min'       => $minimum,
            'max'       => $maximum,
            'step'      => 1
        ];
    }

    /**
     * Liest die bereits normalisierten Presets aus der nativen Variablendarstellung.
     */
    private function GetTunableWhiteTilePresets(): array
    {
        $variableID = $this->GetObjectIDByIdent('color_temp_presets');
        if ($variableID === false) {
            return [];
        }

        $presentation = \IPS_GetVariable($variableID)['VariablePresentation'] ?? [];
        $options = json_decode((string) ($presentation['OPTIONS'] ?? '[]'), true);
        if (!\is_array($options)) {
            return [];
        }

        $presets = [];
        foreach ($options as $option) {
            if (!\is_array($option) || !isset($option['Value']) || !\is_numeric($option['Value'])) {
                continue;
            }

            $mired = (int) round((float) $option['Value']);
            if ($mired <= 0 || $mired > 10000) {
                continue;
            }
            $presets[] = [
                'value'   => $mired,
                'kelvin'  => $this->convertMiredToKelvin($mired),
                'caption' => (string) ($option['Caption'] ?? $mired)
            ];
        }

        return $presets;
    }

    /**
     * Prueft einen Preset-Aktionswert gegen die aktuell dargestellten Optionen.
     */
    private function IsTunableWhiteTilePresetValue(float $value): bool
    {
        foreach ($this->GetTunableWhiteTilePresets() as $preset) {
            if ((float) $preset['value'] === $value) {
                return true;
            }
        }
        return false;
    }

    /**
     * Liefert den Kelvin-Bereich aus der aktuellen Variablendarstellung.
     */
    private function GetTunableWhiteTileKelvinRange(): array
    {
        $feature = $this->FindTunableWhiteTileFeature('color_temp') ?? [];
        return $this->GetColorTemperaturePresentationRange($feature);
    }

    /**
     * Liefert Min, Max und Schritt eines numerischen Expose-Features.
     */
    private function GetTunableWhiteTileNumericRange(string $property, float $defaultMin, float $defaultMax, float $defaultStep): array
    {
        $feature = $this->FindTunableWhiteTileFeature($property) ?? [];
        $minimum = isset($feature['value_min']) && \is_numeric($feature['value_min']) ? (float) $feature['value_min'] : $defaultMin;
        $maximum = isset($feature['value_max']) && \is_numeric($feature['value_max']) ? (float) $feature['value_max'] : $defaultMax;
        $step = isset($feature['value_step']) && \is_numeric($feature['value_step']) ? abs((float) $feature['value_step']) : $defaultStep;

        if ($maximum <= $minimum) {
            $minimum = $defaultMin;
            $maximum = $defaultMax;
        }
        if ($step <= 0.0) {
            $step = $defaultStep;
        }

        return ['min' => $minimum, 'max' => $maximum, 'step' => $step];
    }

    /**
     * Rundet einen Kachelwert passend zur Expose-Schrittweite.
     */
    private function NormalizeTunableWhiteTileNumber(float $value, float $step): int|float
    {
        if ($step >= 1.0 && floor($step) === $step) {
            return (int) round($value / $step) * (int) $step;
        }
        return round($value / $step) * $step;
    }

    /**
     * Sucht ein Feature rekursiv in den aktuellen Exposes.
     */
    private function FindTunableWhiteTileFeature(string $property): ?array
    {
        foreach ($this->ReadAttributeArray(self::ATTRIBUTE_EXPOSES) as $expose) {
            if (!\is_array($expose)) {
                continue;
            }
            $feature = $this->FindTunableWhiteTileFeatureRecursive($expose, $property);
            if ($feature !== null) {
                return $feature;
            }
        }
        return null;
    }

    /**
     * Rekursive Feature-Suche fuer die Kachel.
     */
    private function FindTunableWhiteTileFeatureRecursive(array $feature, string $property): ?array
    {
        if (($feature['property'] ?? '') === $property) {
            return $feature;
        }
        foreach ($feature['features'] ?? [] as $subFeature) {
            if (!\is_array($subFeature)) {
                continue;
            }
            $result = $this->FindTunableWhiteTileFeatureRecursive($subFeature, $property);
            if ($result !== null) {
                return $result;
            }
        }
        return null;
    }

    /**
     * Liefert alle Variablen, deren Aenderung die Kachel aktualisiert.
     */
    private function GetTunableWhiteTileIdents(): array
    {
        return ['state', 'brightness', 'color_temp', 'color_temp_kelvin', 'color_temp_presets'];
    }
}
