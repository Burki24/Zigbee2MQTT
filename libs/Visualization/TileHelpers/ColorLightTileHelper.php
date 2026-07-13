<?php

declare(strict_types=1);

namespace Zigbee2MQTT;

/**
 * HTML-SDK-Kachel fuer RGB-, RGBW- und RGBWW-Leuchten.
 */
trait ColorLightTileHelper
{
    protected function ShouldUseColorLightTile(): bool
    {
        return !$this->ReadPropertyBooleanSafe(self::PROPERTY_DISABLE_COLOR_LIGHT_TILE, false)
            && $this->HasColorLightTileCapabilities();
    }

    protected function HasColorLightTileCapabilities(): bool
    {
        return method_exists($this, 'GetVisualizationTile')
            && $this->GetObjectIDByIdent('state') !== false
            && $this->GetColorLightTileColorIdent() !== null
            && $this->HasColorLightTileNativeColorExpose();
    }

    protected function HandleColorLightTileAction(string $ident, mixed $value): bool
    {
        switch ($ident) {
            case 'ColorLightTile.Toggle':
                $stateID = $this->GetObjectIDByIdent('state');
                if ($stateID !== false) {
                    $this->RequestAction('state', !((bool) \GetValue($stateID)));
                }
                return true;

            case 'ColorLightTile.SetBrightness':
                if ($this->GetObjectIDByIdent('brightness') === false || !\is_numeric($value)) {
                    return true;
                }
                $range = $this->GetTunableWhiteTileNumericRange('brightness', 0.0, 254.0, 1.0);
                $brightness = max($range['min'], min($range['max'], (float) $value));
                $this->RequestAction('brightness', $brightness);
                return true;

            case 'ColorLightTile.SetColor':
                $colorIdent = $this->GetColorLightTileColorIdent();
                if ($colorIdent === null || !\is_numeric($value)) {
                    return true;
                }
                $this->RequestAction($colorIdent, max(0, min(0xFFFFFF, (int) $value)));
                return true;

            case 'ColorLightTile.SetColorTemperature':
                if (!$this->HasColorLightTileColorTemperature() || !\is_numeric($value)) {
                    return true;
                }
                [$minimum, $maximum] = $this->GetTunableWhiteTileKelvinRange();
                $this->RequestAction('color_temp_kelvin', max($minimum, min($maximum, (int) round((float) $value))));
                return true;

            case 'ColorLightTile.SetPreset':
                if (!\is_numeric($value) || !$this->IsTunableWhiteTilePresetValue((float) $value)) {
                    return true;
                }
                $this->RequestAction('color_temp_presets', (int) round((float) $value));
                return true;

            case 'ColorLightTile.Refresh':
                $this->UpdateColorLightTileValue();
                return true;
        }

        return false;
    }

    protected function UpdateColorLightTileValue(): void
    {
        if (!$this->ShouldUseColorLightTile()) {
            return;
        }

        $this->UpdateCustomTileVisualizationValue(json_encode(
            $this->BuildColorLightTileData(),
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
        ));
    }

    protected function UpdateColorLightTileValueIfRelevant(string $ident): void
    {
        if (!\in_array($ident, $this->GetColorLightTileIdents(), true)) {
            return;
        }
        $this->UpdateColorLightTileValue();
    }

    protected function BuildColorLightTileData(): array
    {
        $stateID = $this->GetObjectIDByIdent('state');
        $state = $stateID !== false && (bool) \GetValue($stateID);
        $colorIdent = $this->GetColorLightTileColorIdent();
        $colorID = $colorIdent !== null ? $this->GetObjectIDByIdent($colorIdent) : false;
        $colorValue = $colorID !== false ? max(0, min(0xFFFFFF, (int) \GetValue($colorID))) : 0;
        $hasColorTemperature = $this->HasColorLightTileColorTemperature();
        $presets = $hasColorTemperature ? $this->GetTunableWhiteTilePresets() : [];

        return [
            'type'       => 'colorLight',
            'variant'    => !$hasColorTemperature ? 'rgb' : ($presets === [] ? 'rgbw' : 'rgbww'),
            'name'       => \IPS_GetName($this->InstanceID),
            'labels'     => [
                'brightness'       => $this->Translate('Brightness'),
                'color'            => $this->Translate('Color'),
                'openColorPicker'  => $this->Translate('Open color picker'),
                'colorTemperature' => $this->Translate('Color Temperature'),
                'toggle'           => $this->Translate('Turn light on/off')
            ],
            'state'      => [
                'available' => $stateID !== false,
                'value'     => $state,
                'formatted' => $this->Translate($state ? 'On' : 'Off')
            ],
            'brightness' => $this->BuildTunableWhiteTileBrightnessData(),
            'color'      => [
                'available' => $colorID !== false,
                'ident'     => $colorIdent,
                'objectID'  => $colorID === false ? 0 : $colorID,
                'value'     => $colorValue,
                'hex'       => \sprintf('#%06X', $colorValue)
            ],
            'colorTemperature' => $hasColorTemperature
                ? $this->BuildTunableWhiteTileColorTemperatureData()
                : ['available' => false],
            'presets' => $presets
        ];
    }

    private function GetColorLightTileColorIdent(): ?string
    {
        foreach (['color', 'color_rgb', 'color_hs'] as $ident) {
            if ($this->GetObjectIDByIdent($ident) !== false) {
                return $ident;
            }
        }
        return null;
    }

    private function HasColorLightTileColorTemperature(): bool
    {
        return $this->GetObjectIDByIdent('color_temp') !== false
            && $this->GetObjectIDByIdent('color_temp_kelvin') !== false
            && $this->FindColorLightTileFeature('color_temp') !== null;
    }

    private function HasColorLightTileNativeColorExpose(): bool
    {
        foreach ($this->ReadAttributeArray(self::ATTRIBUTE_EXPOSES) as $expose) {
            if (\is_array($expose) && $this->ColorLightTileFeatureContainsNativeColor($expose)) {
                return true;
            }
        }
        return false;
    }

    private function ColorLightTileFeatureContainsNativeColor(array $feature): bool
    {
        $property = strtolower((string) ($feature['property'] ?? ''));
        $name = strtolower((string) ($feature['name'] ?? ''));
        if ($property === 'color'
            || \in_array($property, ['color_hs', 'color_rgb', 'color_xy'], true)
            || \in_array($name, ['color_hs', 'color_rgb', 'color_xy'], true)
        ) {
            return !isset($feature['access']) || (((int) $feature['access']) & 0b010) !== 0;
        }
        foreach ($feature['features'] ?? [] as $subFeature) {
            if (\is_array($subFeature) && $this->ColorLightTileFeatureContainsNativeColor($subFeature)) {
                return true;
            }
        }
        return false;
    }

    private function FindColorLightTileFeature(string $property): ?array
    {
        foreach ($this->ReadAttributeArray(self::ATTRIBUTE_EXPOSES) as $expose) {
            if (!\is_array($expose)) {
                continue;
            }
            $result = $this->FindColorLightTileFeatureRecursive($expose, $property);
            if ($result !== null) {
                return $result;
            }
        }
        return null;
    }

    private function FindColorLightTileFeatureRecursive(array $feature, string $property): ?array
    {
        if (($feature['property'] ?? '') === $property) {
            return $feature;
        }
        foreach ($feature['features'] ?? [] as $subFeature) {
            if (\is_array($subFeature)) {
                $result = $this->FindColorLightTileFeatureRecursive($subFeature, $property);
                if ($result !== null) {
                    return $result;
                }
            }
        }
        return null;
    }

    private function GetColorLightTileIdents(): array
    {
        return ['state', 'brightness', 'color', 'color_hs', 'color_rgb', 'color_mode', 'color_temp', 'color_temp_kelvin', 'color_temp_presets'];
    }
}
