<?php

declare(strict_types=1);

namespace Zigbee2MQTT;

/**
 * Trait fuer die geraetespezifische Aufbereitung der statischen Konfigurationsform.
 */
trait DeviceFormHelper
{
    /**
     * Bereitet die statische Device-form.json passend zur aktuellen Instanz auf.
     */
    protected function BuildDeviceConfigurationForm(array $form): array
    {
        $tileStates = $this->BuildDeviceFormTileStates();

        $this->ConfigureDeviceFormHeader($form);
        $this->ConfigureDeviceFormVisualization($form, $tileStates);
        $this->ConfigureDeviceFormTemperatureVisualization($form, $tileStates);
        $this->ConfigureDeviceFormVariableSelection($form);
        $this->ConfigureDeviceFormDiagnostics($form);

        return $form;
    }

    /**
     * Setzt Geraetebild, Geraetelink und Debug-Downloadnamen.
     */
    private function ConfigureDeviceFormHeader(array &$form): void
    {
        $model = $this->ReadAttributeString('Model');
        $this->SetDeviceFormField($form, 'DeviceImage', 'image', $this->ReadAttributeString('Icon'));

        if ($model !== '') {
            $modelUrl = str_replace([' ', '/'], '_', $model);
            $encodedModel = rawurlencode($modelUrl);
            $this->SetDeviceFormField(
                $form,
                'DeviceInformationLink',
                'caption',
                $this->Translate('Link to device information: ') . 'https://www.zigbee2mqtt.io/devices/' . $encodedModel . '.html'
            );
            $this->SetDeviceFormField($form, 'DeviceInformationLink', 'visible', true);
            $this->SetDeviceFormField($form, 'DownloadDebugData', 'download', 'Z2M_Debug_' . $encodedModel . '.json');
        } else {
            $this->SetDeviceFormField($form, 'DeviceInformationLink', 'visible', false);
        }
    }

    /**
     * Ermittelt die relevanten Kachelzustaende einmalig fuer die komplette Form.
     */
    private function BuildDeviceFormTileStates(): array
    {
        $tiles = [
            self::PROPERTY_DISABLE_METERED_SWITCH_TILE => $this->BuildDeviceFormTileState(
                $this->HasMeteredSwitchTileCapabilities(),
                self::PROPERTY_DISABLE_METERED_SWITCH_TILE,
                $this->Translate('Metered switch tile')
            ),
            self::PROPERTY_DISABLE_HEATING_TILE => $this->BuildDeviceFormTileState(
                $this->HasHeatingTileCapabilities(),
                self::PROPERTY_DISABLE_HEATING_TILE,
                $this->Translate('Heating tile')
            ),
            self::PROPERTY_DISABLE_SECURITY_TILE => $this->BuildDeviceFormTileState(
                $this->HasSecurityTileCapabilities(),
                self::PROPERTY_DISABLE_SECURITY_TILE,
                $this->Translate('Security tile')
            ),
            self::PROPERTY_DISABLE_WINDOW_HANDLE_TILE => $this->BuildDeviceFormTileState(
                $this->HasWindowHandleTileCapabilities(),
                self::PROPERTY_DISABLE_WINDOW_HANDLE_TILE,
                $this->Translate('Window handle tile')
            )
        ];

        $hasActiveSpecificTile =
            $tiles[self::PROPERTY_DISABLE_METERED_SWITCH_TILE]['enabled']
            || $tiles[self::PROPERTY_DISABLE_HEATING_TILE]['enabled']
            || $tiles[self::PROPERTY_DISABLE_SECURITY_TILE]['enabled']
            || $tiles[self::PROPERTY_DISABLE_WINDOW_HANDLE_TILE]['enabled'];

        $actionAvailable = $this->HasActionTileCapabilities() && !$hasActiveSpecificTile;
        $tiles[self::PROPERTY_DISABLE_ACTION_TILE] = $this->BuildDeviceFormTileState(
            $actionAvailable,
            self::PROPERTY_DISABLE_ACTION_TILE,
            $this->Translate('Action tile')
        );
        $sensorAvailable = $this->HasSensorTileCapabilities();
        $tiles['SensorTile'] = [
            'available' => $sensorAvailable,
            'enabled'   => $sensorAvailable,
            'label'     => $this->Translate('Sensor tile')
        ];

        return $tiles;
    }

    /**
     * Baut einen einzelnen Kachelstatus.
     */
    private function BuildDeviceFormTileState(bool $available, string $disableProperty, string $label): array
    {
        return [
            'available' => $available,
            'enabled'   => $available && !$this->ReadPropertyBoolean($disableProperty),
            'label'     => $label
        ];
    }

    /**
     * Zeigt nur die Kacheloptionen, die fuer diese Instanz fachlich passen.
     */
    private function ConfigureDeviceFormVisualization(array &$form, array $tiles): void
    {
        $hasVisibleTileOption = false;
        foreach ($tiles as $property => $tile) {
            if ($property === 'SensorTile') {
                continue;
            }

            $visible = (bool) $tile['available'];
            $this->SetDeviceFormField($form, $property, 'visible', $visible);
            $hasVisibleTileOption = $hasVisibleTileOption || $visible;
        }

        $this->SetDeviceFormField($form, 'VisualizationSettings', 'visible', $hasVisibleTileOption);
        $this->SetDeviceFormField(
            $form,
            'VisualizationStatus',
            'caption',
            $this->Translate('Active visualization: ') . $this->GetDeviceFormActiveVisualizationLabel($tiles)
        );
        $this->SetDeviceFormField(
            $form,
            'HeatingTilePresetSettings',
            'visible',
            (bool) $tiles[self::PROPERTY_DISABLE_HEATING_TILE]['available']
        );
    }

    /**
     * Zeigt den Temperaturbereich nur dort, wo er in der normalen Bedienung relevant ist.
     */
    private function ConfigureDeviceFormTemperatureVisualization(array &$form, array $tiles): void
    {
        $hasTemperature = $this->GetObjectIDByIdent('temperature') !== false;
        $hasCustomTemperatureRange =
            $this->ReadPropertyFloat(self::PROPERTY_TEMPERATURE_PRESENTATION_FALLBACK_MIN) !== -40.0
            || $this->ReadPropertyFloat(self::PROPERTY_TEMPERATURE_PRESENTATION_FALLBACK_MAX) !== 80.0;

        $visible = $hasTemperature
            && (
                $hasCustomTemperatureRange
                || ($tiles['SensorTile']['available']
                    && !$tiles[self::PROPERTY_DISABLE_SECURITY_TILE]['available']
                    && !$tiles[self::PROPERTY_DISABLE_WINDOW_HANDLE_TILE]['available'])
            );

        $this->SetDeviceFormField($form, 'TemperatureVisualization', 'visible', $visible);
    }

    /**
     * Fuellt die dynamische Variablenverwaltung.
     */
    private function ConfigureDeviceFormVariableSelection(array &$form): void
    {
        $values = $this->BuildVariableSelectionFormValues();
        $this->SetDeviceFormField($form, 'VariableSelectionSettings', 'visible', \count($values) > 0);
        $this->SetDeviceFormField($form, 'VariableSelectionList', 'values', $values);
        $this->SetDeviceFormField($form, 'VariableSelectionList', 'rowCount', min(12, max(4, \count($values) + 1)));
    }

    /**
     * Setzt Diagnoseelemente wie fehlende Uebersetzungen.
     */
    private function ConfigureDeviceFormDiagnostics(array &$form): void
    {
        $this->SetDeviceFormField($form, 'ShowMissingTranslationsButton', 'visible', count($this->missingTranslations) > 0);
    }

    /**
     * Ermittelt die aktuell aktive Visualisierung nach derselben Prioritaet wie GetVisualizationTile().
     */
    private function GetDeviceFormActiveVisualizationLabel(array $tiles): string
    {
        if ($tiles[self::PROPERTY_DISABLE_HEATING_TILE]['enabled']) {
            return $tiles[self::PROPERTY_DISABLE_HEATING_TILE]['label'];
        }
        if ($tiles[self::PROPERTY_DISABLE_METERED_SWITCH_TILE]['enabled']) {
            return $tiles[self::PROPERTY_DISABLE_METERED_SWITCH_TILE]['label'];
        }
        if ($tiles[self::PROPERTY_DISABLE_WINDOW_HANDLE_TILE]['enabled']) {
            return $tiles[self::PROPERTY_DISABLE_WINDOW_HANDLE_TILE]['label'];
        }
        if ($tiles[self::PROPERTY_DISABLE_SECURITY_TILE]['enabled']) {
            return $tiles[self::PROPERTY_DISABLE_SECURITY_TILE]['label'];
        }
        if ($tiles[self::PROPERTY_DISABLE_ACTION_TILE]['enabled']) {
            return $tiles[self::PROPERTY_DISABLE_ACTION_TILE]['label'];
        }
        if ($tiles['SensorTile']['enabled']) {
            return $tiles['SensorTile']['label'];
        }

        return $this->Translate('Standard visualization');
    }

    /**
     * Setzt ein Feld in der verschachtelten Symcon-Form anhand seines Namens.
     */
    private function SetDeviceFormField(array &$node, string $name, string $field, mixed $value): bool
    {
        if (($node['name'] ?? null) === $name) {
            $node[$field] = $value;
            return true;
        }

        foreach ($node as &$child) {
            if (!\is_array($child)) {
                continue;
            }

            if ($this->SetDeviceFormField($child, $name, $field, $value)) {
                return true;
            }
        }

        return false;
    }
}
