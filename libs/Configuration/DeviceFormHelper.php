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
        $this->ConfigureDeviceFormColorTemperatureVisualization($form);
        $this->ConfigureDeviceFormDeviceOptions($form);
        $this->ConfigureDeviceFormBindingReporting($form);
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
        $sensorSelectable = $sensorAvailable && $this->HasSensorTileActuatorExposeGroup();
        $tiles[self::PROPERTY_USE_SENSOR_TILE] = [
            'available' => $sensorSelectable,
            'enabled'   => $sensorSelectable && $this->ReadPropertyBooleanSafe(self::PROPERTY_USE_SENSOR_TILE, false),
            'label'     => $this->Translate('Sensor tile')
        ];
        $tiles['SensorTile'] = [
            'available' => $sensorAvailable,
            'enabled'   => $this->ShouldUseSensorTile(),
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
            'enabled'   => $available && !$this->ReadPropertyBooleanSafe($disableProperty, false),
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
            $this->Translate('Active visualization:') . ' ' . $this->GetDeviceFormActiveVisualizationLabel($tiles)
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
            $this->ReadPropertyFloatSafe(self::PROPERTY_TEMPERATURE_PRESENTATION_FALLBACK_MIN, -40.0) !== -40.0
            || $this->ReadPropertyFloatSafe(self::PROPERTY_TEMPERATURE_PRESENTATION_FALLBACK_MAX, 80.0) !== 80.0;

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
     * Zeigt den optionalen Kelvin-Bereich fuer Farbtemperatur-Leuchten.
     */
    private function ConfigureDeviceFormColorTemperatureVisualization(array &$form): void
    {
        $this->SetDeviceFormField(
            $form,
            'ColorTemperatureVisualization',
            'visible',
            $this->HasExposeProperty('color_temp')
        );
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
     * Fuellt die dynamische Verwaltung fuer Zigbee2MQTT-Geraeteoptionen.
     */
    private function ConfigureDeviceFormDeviceOptions(array &$form): void
    {
        $values = $this->BuildDeviceOptionFormValues();
        $this->SetDeviceFormField($form, 'DeviceOptionsSettings', 'visible', \count($values) > 0);
        $this->SetDeviceFormField($form, 'DeviceOptionList', 'values', $values);
        $this->SetDeviceFormField($form, 'DeviceOptionList', 'rowCount', min(12, max(4, \count($values) + 1)));
    }

    /**
     * Fuellt die dynamische Verwaltung fuer Zigbee-Binding und Reporting.
     */
    private function ConfigureDeviceFormBindingReporting(array &$form): void
    {
        $values = $this->BuildEndpointFormValues();
        $bindingValues = $this->BuildBindingOverviewFormValues();
        $reportingValues = $this->BuildReportingOverviewFormValues();
        $visible = \count($values) > 0 || trim($this->ReadPropertyString(self::MQTT_TOPIC)) !== '';
        $this->SetDeviceFormField($form, 'BindingReportingSettings', 'visible', $visible);
        $this->SetDeviceFormField(
            $form,
            'EndpointDataHint',
            'caption',
            $this->Translate('No endpoint data is available yet. Update the device information and make sure the Symcon extension is current.')
        );
        $this->SetDeviceFormField($form, 'EndpointDataHint', 'visible', $visible && \count($values) === 0);
        $this->SetDeviceFormField($form, 'EndpointList', 'values', $values);
        $this->SetDeviceFormField($form, 'EndpointList', 'rowCount', min(10, max(4, \count($values) + 1)));
        $this->SetDeviceFormField($form, 'BindingOverviewList', 'visible', $visible);
        $this->SetDeviceFormField($form, 'BindingOverviewList', 'values', $bindingValues);
        $this->SetDeviceFormField($form, 'BindingOverviewList', 'rowCount', min(10, max(4, \count($bindingValues) + 1)));
        $this->SetDeviceFormField($form, 'ReportingOverviewList', 'visible', $visible);
        $this->SetDeviceFormField($form, 'ReportingOverviewList', 'values', $reportingValues);
        $this->SetDeviceFormField($form, 'ReportingOverviewList', 'rowCount', min(10, max(4, \count($reportingValues) + 1)));
        $this->SetDeviceFormField($form, 'BindingSourceEndpoint', 'options', $this->BuildBindingSourceEndpointOptions());
        $this->SetDeviceFormField($form, 'BindingTarget', 'options', $this->BuildBindingTargetOptions());
        $this->SetDeviceFormField($form, 'BindingClusters', 'options', $this->BuildBindingClusterOptions());
    }

    /**
     * Uebernimmt eine Zeile aus der Optionsliste in die Eingabefelder.
     */
    protected function SelectDeviceOptionFromForm(mixed $value): bool
    {
        $selection = $this->DecodeDeviceOptionFormPayload($value);
        if ($selection === null) {
            return false;
        }

        $this->UpdateFormField('DeviceOptionName', 'value', $selection['name'] ?? '');
        $this->ConfigureDeviceOptionEditor((string) ($selection['name'] ?? ''), (string) ($selection['value'] ?? ''));

        return true;
    }

    /**
     * Speichert eine einzelne Geraeteoption in Zigbee2MQTT.
     */
    protected function ApplyDeviceOptionFromForm(mixed $value): bool
    {
        $selection = $this->DecodeDeviceOptionFormPayload($value);
        if ($selection === null) {
            return false;
        }

        $name = trim((string) ($selection['name'] ?? ''));
        if ($name === '') {
            trigger_error($this->Translate('No device option selected.'), E_USER_NOTICE);
            return false;
        }
        if ($this->ShouldSkipDeviceOption($name)) {
            trigger_error($this->Translate('This device option must be changed through a dedicated Zigbee2MQTT function.'), E_USER_NOTICE);
            return false;
        }

        try {
            $parsedValue = $this->ParseDeviceOptionValue($name, $this->ResolveDeviceOptionRawValue($name, $selection));
        } catch (\InvalidArgumentException $e) {
            trigger_error($this->Translate($e->getMessage()), E_USER_NOTICE);
            return false;
        }

        if (!$this->SendDeviceOptionsRequest([$name => $parsedValue])) {
            return false;
        }

        $options = $this->ReadAttributeArray(self::ATTRIBUTE_DEVICE_OPTIONS);
        $options[$name] = $parsedValue;
        $this->WriteAttributeArray(self::ATTRIBUTE_DEVICE_OPTIONS, $options);
        if ($name === 'filtered_attributes' && \is_array($parsedValue)) {
            $this->WriteAttributeArray(self::ATTRIBUTE_FILTERED, $parsedValue);
        }

        $values = $this->BuildDeviceOptionFormValues();
        $this->UpdateFormField('DeviceOptionList', 'values', json_encode($values));

        return true;
    }

    /**
     * Fuehrt ein Binding aus.
     */
    protected function ApplyBindingFromForm(mixed $value): bool
    {
        return $this->ApplyBindingRequestFromForm($value, false);
    }

    /**
     * Entfernt ein Binding.
     */
    protected function ApplyUnbindingFromForm(mixed $value): bool
    {
        return $this->ApplyBindingRequestFromForm($value, true);
    }

    /**
     * Entfernt alle Bindings dieses Geraets.
     */
    protected function ClearBindingsFromForm(): bool
    {
        return $this->CallMatchingBridgeFunction('ClearBinds', [$this->ReadPropertyString(self::MQTT_TOPIC)]) === true;
    }

    /**
     * Aktualisiert die Cluster-Auswahl bei Wechsel des Quell-Endpoints oder Ziels.
     */
    protected function UpdateBindingClustersFromForm(mixed $value): bool
    {
        $selection = $this->DecodeDeviceOptionFormPayload($value);
        if ($selection === null) {
            return false;
        }

        $endpoint = trim((string) ($selection['endpoint'] ?? ''));
        $target = trim((string) ($selection['target'] ?? ''));
        $currentCluster = trim((string) ($selection['cluster'] ?? ''));
        $options = $this->BuildBindingClusterOptions($endpoint, $target);
        $selectedCluster = $this->ResolveBindingClusterSelection($currentCluster, $options);

        $this->UpdateFormField('BindingClusters', 'options', json_encode($options));
        $this->UpdateFormField('BindingClusters', 'value', $selectedCluster);

        return true;
    }

    /**
     * Konfiguriert Zigbee-Reporting fuer ein Attribut.
     */
    protected function ConfigureReportingFromForm(mixed $value): bool
    {
        $selection = $this->DecodeDeviceOptionFormPayload($value);
        if ($selection === null) {
            return false;
        }

        $endpoint = trim((string) ($selection['endpoint'] ?? ''));
        $cluster = trim((string) ($selection['cluster'] ?? ''));
        $attribute = trim((string) ($selection['attribute'] ?? ''));
        if ($cluster === '' || $attribute === '') {
            trigger_error($this->Translate('Cluster and attribute are required.'), E_USER_NOTICE);
            return false;
        }

        return $this->CallMatchingBridgeFunction('ConfigureReporting', [
            $this->ReadPropertyString(self::MQTT_TOPIC),
            $endpoint,
            $cluster,
            $attribute,
            (int) ($selection['minimum'] ?? 0),
            (int) ($selection['maximum'] ?? 65535),
            (string) ($selection['change'] ?? ''),
            (string) ($selection['options'] ?? '')
        ]) === true;
    }

    /**
     * Liest Zigbee-Reporting fuer ein Attribut und zeigt die Antwort in der Form.
     */
    protected function ReadReportingFromForm(mixed $value): bool
    {
        $selection = $this->DecodeDeviceOptionFormPayload($value);
        if ($selection === null) {
            return false;
        }

        $endpoint = trim((string) ($selection['endpoint'] ?? ''));
        $cluster = trim((string) ($selection['cluster'] ?? ''));
        $attribute = trim((string) ($selection['attribute'] ?? ''));
        if ($cluster === '' || $attribute === '') {
            trigger_error($this->Translate('Cluster and attribute are required.'), E_USER_NOTICE);
            return false;
        }

        $result = $this->CallMatchingBridgeFunction('ReadReporting', [
            $this->ReadPropertyString(self::MQTT_TOPIC),
            $endpoint,
            $cluster,
            json_encode([$attribute], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ''
        ]);
        if (!\is_string($result) || $result === '') {
            return false;
        }

        $this->UpdateFormField('ReportingResult', 'visible', true);
        $this->UpdateFormField('ReportingResult', 'caption', $this->Translate('Reporting result:') . ' ' . $result);

        return true;
    }

    /**
     * Baut die Zeilen der Optionsliste aus generischen, geraetespezifischen und aktuellen Optionen.
     */
    private function BuildDeviceOptionFormValues(): array
    {
        $definitions = $this->BuildDeviceOptionDefinitionMap();
        $options = $this->ReadAttributeArray(self::ATTRIBUTE_DEVICE_OPTIONS);

        foreach ($options as $name => $value) {
            if ($this->ShouldSkipDeviceOption((string) $name)) {
                continue;
            }

            $definitions[(string) $name] ??= [
                'name'        => (string) $name,
                'label'       => $this->BuildDeviceOptionLabel((string) $name),
                'type'        => $this->DetectDeviceOptionType($value),
                'description' => ''
            ];
        }

        ksort($definitions, SORT_NATURAL | SORT_FLAG_CASE);

        $values = [];
        foreach ($definitions as $name => $definition) {
            $type = $this->NormalizeDeviceOptionType((string) ($definition['type'] ?? 'mixed'));
            $currentValue = $options[$name] ?? null;
            $description = (string) ($definition['description'] ?? '');

            $values[] = [
                'label'       => $this->Translate((string) ($definition['label'] ?? $this->BuildDeviceOptionLabel($name))),
                'name'        => $name,
                'type'        => $this->Translate($this->FormatDeviceOptionType($type)),
                'current'     => \array_key_exists($name, $options) ? $this->FormatDeviceOptionValue($currentValue) : '',
                'description' => $description === '' ? '' : $this->Translate($description),
                'action'      => $this->Translate('Edit')
            ];
        }

        return $values;
    }

    /**
     * Baut die Endpoint-Uebersicht fuer Binding und Reporting.
     */
    private function BuildEndpointFormValues(): array
    {
        $values = [];
        foreach ($this->ReadAttributeArray(self::ATTRIBUTE_DEVICE_ENDPOINTS) as $endpointID => $endpoint) {
            if (!\is_array($endpoint)) {
                continue;
            }

            $clusters = \is_array($endpoint['clusters'] ?? null) ? $endpoint['clusters'] : [];
            $bindings = \is_array($endpoint['bindings'] ?? null) ? $endpoint['bindings'] : [];
            $reportings = \is_array($endpoint['configured_reportings'] ?? null) ? $endpoint['configured_reportings'] : [];

            $values[] = [
                'endpoint'   => (string) ($endpoint['id'] ?? $endpointID),
                'name'       => (string) ($endpoint['name'] ?? ''),
                'input'      => $this->FormatEndpointListValue($clusters['input'] ?? []),
                'output'     => $this->FormatEndpointListValue($clusters['output'] ?? []),
                'bindings'   => (string) \count($bindings),
                'reportings' => (string) \count($reportings)
            ];
        }

        return $values;
    }

    /**
     * Baut eine lesbare Uebersicht der bekannten Bindings.
     */
    private function BuildBindingOverviewFormValues(): array
    {
        $values = [];
        foreach ($this->ReadAttributeArray(self::ATTRIBUTE_DEVICE_ENDPOINTS) as $endpointID => $endpoint) {
            if (!\is_array($endpoint) || !\is_array($endpoint['bindings'] ?? null)) {
                continue;
            }

            $sourceEndpoint = trim((string) ($endpoint['id'] ?? $endpoint['ID'] ?? $endpointID));
            foreach ($endpoint['bindings'] as $binding) {
                if (!\is_array($binding)) {
                    continue;
                }

                $target = $binding['target'] ?? $binding['to'] ?? $binding['destination'] ?? null;
                $values[] = [
                    'source_endpoint' => $sourceEndpoint,
                    'cluster'         => $this->FormatBindingClusterValue($binding['cluster'] ?? $binding['clusterName'] ?? ''),
                    'target_type'     => $this->FormatBindingTargetType($target),
                    'target'          => $this->FormatBindingTargetValue($target),
                    'target_endpoint' => $this->FormatBindingTargetEndpoint($target, $binding)
                ];
            }
        }

        if ($values === []) {
            return [[
                'source_endpoint' => '',
                'cluster'         => '',
                'target_type'     => '',
                'target'          => $this->Translate('No bindings available'),
                'target_endpoint' => ''
            ]];
        }

        return $values;
    }

    /**
     * Baut eine lesbare Uebersicht der bekannten Reportings.
     */
    private function BuildReportingOverviewFormValues(): array
    {
        $values = [];
        foreach ($this->ReadAttributeArray(self::ATTRIBUTE_DEVICE_ENDPOINTS) as $endpointID => $endpoint) {
            if (!\is_array($endpoint) || !\is_array($endpoint['configured_reportings'] ?? null)) {
                continue;
            }

            $sourceEndpoint = trim((string) ($endpoint['id'] ?? $endpoint['ID'] ?? $endpointID));
            foreach ($endpoint['configured_reportings'] as $reporting) {
                if (!\is_array($reporting)) {
                    continue;
                }

                $values[] = [
                    'endpoint'          => $sourceEndpoint,
                    'cluster'           => $this->FormatBindingClusterValue($reporting['cluster'] ?? $reporting['clusterName'] ?? ''),
                    'attribute'         => trim((string) ($reporting['attribute'] ?? $reporting['attributeName'] ?? '')),
                    'minimum_interval'  => $this->FormatReportingIntervalValue($this->ReadReportingMinimumInterval($reporting)),
                    'maximum_interval'  => $this->FormatReportingIntervalValue($this->ReadReportingMaximumInterval($reporting)),
                    'reportable_change' => $this->FormatReportingValue($reporting['reportable_change'] ?? $reporting['reportableChange'] ?? $reporting['change'] ?? '')
                ];
            }
        }

        if ($values === []) {
            return [[
                'endpoint'          => '',
                'cluster'           => '',
                'attribute'         => $this->Translate('No reportings available'),
                'minimum_interval'  => '',
                'maximum_interval'  => '',
                'reportable_change' => ''
            ]];
        }

        return $values;
    }

    /**
     * Liest das minimale Reporting-Intervall aus bekannten Z2M-Feldvarianten.
     */
    private function ReadReportingMinimumInterval(array $reporting): mixed
    {
        foreach (['minimum_report_interval', 'minimumReportInterval', 'minimum_interval', 'minimumInterval', 'min_interval', 'minInterval', 'min'] as $key) {
            if (\array_key_exists($key, $reporting)) {
                return $reporting[$key];
            }
        }

        return '';
    }

    /**
     * Liest das maximale Reporting-Intervall aus bekannten Z2M-Feldvarianten.
     */
    private function ReadReportingMaximumInterval(array $reporting): mixed
    {
        foreach (['maximum_report_interval', 'maximumReportInterval', 'maximum_interval', 'maximumInterval', 'max_interval', 'maxInterval', 'max'] as $key) {
            if (\array_key_exists($key, $reporting)) {
                return $reporting[$key];
            }
        }

        return '';
    }

    /**
     * Formatiert ein Reporting-Intervall.
     */
    private function FormatReportingIntervalValue(mixed $value): string
    {
        $value = $this->FormatReportingValue($value);
        if ($value === '') {
            return '';
        }

        return $value . ' s';
    }

    /**
     * Formatiert einen Reporting-Wert.
     */
    private function FormatReportingValue(mixed $value): string
    {
        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (\is_array($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return $encoded === false ? '' : $encoded;
        }

        return trim((string) $value);
    }

    /**
     * Formatiert einen Binding-Cluster fuer die Uebersicht.
     */
    private function FormatBindingClusterValue(mixed $cluster): string
    {
        if (!\is_array($cluster)) {
            return trim((string) $cluster);
        }

        return trim((string) ($cluster['name'] ?? $cluster['ID'] ?? $cluster['id'] ?? $cluster['clusterID'] ?? ''));
    }

    /**
     * Formatiert den Zieltyp eines Bindings.
     */
    private function FormatBindingTargetType(mixed $target): string
    {
        if (!\is_array($target)) {
            return '';
        }

        $type = strtolower(trim((string) ($target['type'] ?? '')));
        if ($type === 'group' || isset($target['groupID']) || isset($target['group_id'])) {
            return $this->Translate('Group');
        }
        if (\in_array($type, ['device', 'endpoint'], true) || isset($target['deviceIeeeAddress']) || isset($target['ieeeAddr'])) {
            return $type === 'endpoint' ? $this->Translate('Endpoint') : $this->Translate('Device');
        }

        return $type === '' ? '' : ucfirst($type);
    }

    /**
     * Formatiert das Binding-Ziel.
     */
    private function FormatBindingTargetValue(mixed $target): string
    {
        if (!\is_array($target)) {
            return trim((string) $target);
        }

        $type = strtolower(trim((string) ($target['type'] ?? '')));
        if ($type === 'group' || isset($target['groupID']) || isset($target['group_id'])) {
            return trim((string) ($target['friendly_name'] ?? $target['name'] ?? $target['groupID'] ?? $target['group_id'] ?? $target['ID'] ?? $target['id'] ?? ''));
        }

        $device = $target['device'] ?? null;
        if (\is_array($device)) {
            $value = trim((string) ($device['friendly_name'] ?? $device['name'] ?? $device['ieeeAddr'] ?? $device['ieee_address'] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        $value = trim((string) ($target['friendly_name'] ?? $target['name'] ?? $target['deviceIeeeAddress'] ?? $target['ieeeAddr'] ?? $target['ieee_address'] ?? ''));
        if ($value !== '') {
            return $value;
        }

        return $this->FormatBindingFallbackValue($target);
    }

    /**
     * Formatiert den Ziel-Endpoint eines Bindings.
     */
    private function FormatBindingTargetEndpoint(mixed $target, array $binding): string
    {
        $endpoint = trim((string) ($binding['to_endpoint'] ?? $binding['target_endpoint'] ?? ''));
        if ($endpoint !== '' || !\is_array($target)) {
            return $endpoint;
        }

        $type = strtolower(trim((string) ($target['type'] ?? '')));
        if ($type === 'group' || isset($target['groupID']) || isset($target['group_id'])) {
            return '';
        }

        return trim((string) ($target['endpoint'] ?? $target['endpointID'] ?? $target['endpoint_id'] ?? $target['ID'] ?? $target['id'] ?? ''));
    }

    /**
     * Liefert eine kompakte Rueckfallanzeige fuer unbekannte Binding-Ziele.
     */
    private function FormatBindingFallbackValue(array $target): string
    {
        $encoded = json_encode($target, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return $encoded === false ? '' : $encoded;
    }

    /**
     * Baut die Quell-Endpoint-Auswahl fuer Binding-Requests.
     */
    private function BuildBindingSourceEndpointOptions(): array
    {
        $options = [
            ['caption' => '-', 'value' => '']
        ];

        foreach ($this->ReadAttributeArray(self::ATTRIBUTE_DEVICE_ENDPOINTS) as $endpointID => $endpoint) {
            if (!\is_array($endpoint)) {
                continue;
            }

            $id = trim((string) ($endpoint['id'] ?? $endpoint['ID'] ?? $endpointID));
            if ($id === '') {
                continue;
            }

            $name = trim((string) ($endpoint['name'] ?? ''));
            $options[$id] = [
                'caption' => $name === '' ? $id : $id . ' (' . $name . ')',
                'value'   => $id
            ];
        }

        return array_values($options);
    }

    /**
     * Baut die Zielauswahl fuer Binding-Requests aus lokalen Instanzen und Z2M-Listen.
     */
    private function BuildBindingTargetOptions(): array
    {
        $targets = [];
        foreach ([
            $this->LoadBindingTargetDevicesFromInstances(),
            $this->LoadBindingTargetGroupsFromInstances(),
            $this->LoadBindingTargetDevicesFromExtension(),
            $this->LoadBindingTargetGroupsFromExtension()
        ] as $source) {
            foreach ($source as $target) {
                $value = trim((string) ($target['value'] ?? ''));
                if ($value === '') {
                    continue;
                }

                $targets[$value] = $target;
            }
        }

        uasort($targets, static fn (array $left, array $right): int => strnatcasecmp((string) ($left['caption'] ?? ''), (string) ($right['caption'] ?? '')));

        $options = [
            ['caption' => '-', 'value' => '']
        ];
        foreach ($targets as $target) {
            $options[] = [
                'caption' => (string) ($target['caption'] ?? $target['value']),
                'value'   => (string) $target['value']
            ];
        }

        return $options;
    }

    /**
     * Baut die Cluster-Auswahl fuer Binding-Requests.
     */
    private function BuildBindingClusterOptions(string $sourceEndpoint = '', string $target = ''): array
    {
        $sourceClusters = $this->BuildBindingSourceClusterValues($sourceEndpoint);
        $targetClusters = $this->BuildBindingTargetClusterValues($target);

        if ($sourceClusters !== [] && $targetClusters !== []) {
            $matchingClusters = array_values(array_intersect($sourceClusters, $targetClusters));
            $clusters = $matchingClusters !== [] ? $matchingClusters : $sourceClusters;
        } elseif ($sourceClusters !== []) {
            $clusters = $sourceClusters;
        } else {
            $clusters = $targetClusters;
        }

        $clusters = $this->FilterSupportedBindingClusterValues($clusters);
        $options = [
            ['caption' => '-', 'value' => '']
        ];
        foreach ($clusters as $cluster) {
            $cluster = trim((string) $cluster);
            if ($cluster === '') {
                continue;
            }

            $options[$cluster] = [
                'caption' => $cluster,
                'value'   => $cluster
            ];
        }

        return array_values($options);
    }

    /**
     * Liefert die Cluster-Werte des ausgewaehlten Quell-Endpoints.
     */
    private function BuildBindingSourceClusterValues(string $sourceEndpoint): array
    {
        $clusters = [];
        foreach ($this->ReadAttributeArray(self::ATTRIBUTE_DEVICE_ENDPOINTS) as $endpointID => $endpoint) {
            if (!\is_array($endpoint)) {
                continue;
            }

            $id = trim((string) ($endpoint['id'] ?? $endpoint['ID'] ?? $endpointID));
            if ($sourceEndpoint !== '' && $id !== $sourceEndpoint) {
                continue;
            }

            $clusters = array_merge($clusters, $this->ExtractBindingClusterValues($endpoint['clusters'] ?? []));
        }

        return $this->NormalizeBindingClusterValues($clusters);
    }

    /**
     * Liefert bekannte Cluster eines ausgewaehlten Binding-Ziels.
     */
    private function BuildBindingTargetClusterValues(string $target): array
    {
        if ($target === '' || !$this->HasActiveParent()) {
            return [];
        }

        $devices = $this->ReadBindingTargetDeviceList();
        foreach ($devices as $device) {
            if (!\is_array($device) || trim((string) ($device['friendly_name'] ?? '')) !== $target) {
                continue;
            }

            return $this->ExtractBindingEndpointClusterValues($device['endpoints'] ?? []);
        }

        foreach ($this->ReadBindingTargetGroupList() as $group) {
            if (!\is_array($group) || trim((string) ($group['friendly_name'] ?? '')) !== $target) {
                continue;
            }

            return $this->ExtractBindingGroupMemberClusterValues($group, $devices);
        }

        return [];
    }

    /**
     * Fragt die von der Extension bekannten Geraete fuer Binding-Auswahlen ab.
     */
    private function ReadBindingTargetDeviceList(): array
    {
        $result = @$this->SendData(self::SYMCON_EXTENSION_LIST_REQUEST . 'getDevices', [], 2500);
        if (!\is_array($result) || !\is_array($result['list'] ?? null)) {
            return [];
        }

        return $result['list'];
    }

    /**
     * Fragt die von der Extension bekannten Gruppen fuer Binding-Auswahlen ab.
     */
    private function ReadBindingTargetGroupList(): array
    {
        $result = @$this->SendData(self::SYMCON_EXTENSION_LIST_REQUEST . 'getGroups', [], 2500);
        if (!\is_array($result) || !\is_array($result['list'] ?? null)) {
            return [];
        }

        return $result['list'];
    }

    /**
     * Leitet die Cluster einer Gruppe aus ihren bekannten Mitgliedern ab.
     */
    private function ExtractBindingGroupMemberClusterValues(array $group, array $devices): array
    {
        $deviceIndex = $this->IndexBindingDevices($devices);
        $clusters = [];
        foreach ($this->BuildBindingGroupMemberReferences($group) as $reference) {
            $device = $this->FindIndexedBindingDevice($deviceIndex, (string) ($reference['device'] ?? ''));
            if ($device === null) {
                continue;
            }

            $clusters = array_merge(
                $clusters,
                $this->ExtractBindingEndpointClusterValues($device['endpoints'] ?? [], (string) ($reference['endpoint'] ?? ''))
            );
        }

        return $this->NormalizeBindingClusterValues($clusters);
    }

    /**
     * Baut einen schnellen Zugriff auf Geraete per Friendly Name und IEEE-Adresse.
     */
    private function IndexBindingDevices(array $devices): array
    {
        $index = [];
        foreach ($devices as $device) {
            if (!\is_array($device)) {
                continue;
            }

            foreach (['friendly_name', 'ieeeAddr', 'ieee_address'] as $key) {
                $identifier = strtolower(trim((string) ($device[$key] ?? '')));
                if ($identifier !== '') {
                    $index[$identifier] = $device;
                }
            }
        }

        return $index;
    }

    /**
     * Sucht ein indexiertes Binding-Geraet.
     */
    private function FindIndexedBindingDevice(array $deviceIndex, string $identifier): ?array
    {
        $identifier = strtolower(trim($identifier));
        if ($identifier === '' || !\is_array($deviceIndex[$identifier] ?? null)) {
            return null;
        }

        return $deviceIndex[$identifier];
    }

    /**
     * Normalisiert Gruppenmitglieder fuer die Cluster-Ableitung.
     */
    private function BuildBindingGroupMemberReferences(array $group): array
    {
        $references = [];
        foreach (['members', 'devices'] as $key) {
            if (!\is_array($group[$key] ?? null)) {
                continue;
            }

            foreach ($group[$key] as $entry) {
                $reference = $this->BuildBindingGroupMemberReference($entry);
                if ($reference === null) {
                    continue;
                }

                $references[$reference['device'] . '#' . $reference['endpoint']] = $reference;
            }
        }

        return array_values($references);
    }

    /**
     * Normalisiert ein Gruppenmitglied fuer die Cluster-Ableitung.
     */
    private function BuildBindingGroupMemberReference(mixed $entry): ?array
    {
        if (\is_string($entry) || \is_int($entry)) {
            $device = trim((string) $entry);
            return $device === '' ? null : ['device' => $device, 'endpoint' => ''];
        }

        if (!\is_array($entry)) {
            return null;
        }

        $device = trim((string) ($entry['device'] ?? $entry['friendly_name'] ?? $entry['ieee_address'] ?? $entry['ieeeAddr'] ?? ''));
        if ($device === '') {
            return null;
        }

        return [
            'device'   => $device,
            'endpoint' => trim((string) ($entry['endpoint'] ?? $entry['endpoint_id'] ?? $entry['endpointID'] ?? ''))
        ];
    }

    /**
     * Extrahiert Cluster aus einer Endpoint-Liste.
     */
    private function ExtractBindingEndpointClusterValues(mixed $endpoints, string $endpointFilter = ''): array
    {
        if (!\is_array($endpoints)) {
            return [];
        }

        $clusters = [];
        foreach ($endpoints as $endpointID => $endpoint) {
            if (!\is_array($endpoint)) {
                continue;
            }

            $id = trim((string) ($endpoint['id'] ?? $endpoint['ID'] ?? $endpointID));
            if ($endpointFilter !== '' && $id !== $endpointFilter) {
                continue;
            }

            $clusters = array_merge($clusters, $this->ExtractBindingClusterValues($endpoint['clusters'] ?? []));
        }

        return $this->NormalizeBindingClusterValues($clusters);
    }

    /**
     * Extrahiert Eingangs- und Ausgangscluster aus einer Cluster-Struktur.
     */
    private function ExtractBindingClusterValues(mixed $clusters): array
    {
        if (!\is_array($clusters)) {
            return [];
        }

        $values = [];
        foreach (['input', 'output'] as $clusterDirection) {
            if (!\is_array($clusters[$clusterDirection] ?? null)) {
                continue;
            }

            foreach ($clusters[$clusterDirection] as $cluster) {
                $values[] = $cluster;
            }
        }

        return $values;
    }

    /**
     * Normalisiert Cluster-Werte fuer Auswahlfelder.
     */
    private function NormalizeBindingClusterValues(array $clusters): array
    {
        $values = [];
        foreach ($clusters as $cluster) {
            $cluster = trim((string) $cluster);
            if ($cluster === '') {
                continue;
            }

            $values[$cluster] = $cluster;
        }

        $values = array_values($values);
        usort($values, static fn (string $left, string $right): int => strnatcasecmp($left, $right));
        return $values;
    }

    /**
     * Beschraenkt Cluster auf die von Zigbee2MQTT fuer Bindings unterstuetzten Werte.
     */
    private function FilterSupportedBindingClusterValues(array $clusters): array
    {
        $supportedClusters = array_flip($this->GetSupportedBindingClusterValues());
        $values = [];
        foreach ($clusters as $cluster) {
            $cluster = $this->NormalizeBindingClusterValue($cluster);
            if ($cluster === '' || !\array_key_exists($cluster, $supportedClusters)) {
                continue;
            }

            $values[] = $cluster;
        }

        return $this->NormalizeBindingClusterValues($values);
    }

    /**
     * Normalisiert numerische Cluster-IDs auf die von Zigbee2MQTT erwarteten Binding-Namen.
     */
    private function NormalizeBindingClusterValue(mixed $cluster): string
    {
        if (\is_array($cluster)) {
            $cluster = $cluster['name'] ?? $cluster['ID'] ?? $cluster['id'] ?? $cluster['clusterID'] ?? '';
        }

        $cluster = trim((string) $cluster);
        if ($cluster === '') {
            return '';
        }

        return $this->GetSupportedBindingClusterMap()[$cluster] ?? $cluster;
    }

    /**
     * Liefert die von Zigbee2MQTT dokumentierten Binding-Cluster.
     */
    private function GetSupportedBindingClusterValues(): array
    {
        return array_values(array_unique($this->GetSupportedBindingClusterMap()));
    }

    /**
     * Liefert die von Zigbee2MQTT dokumentierten Binding-Cluster mit numerischen Zigbee-IDs.
     */
    private function GetSupportedBindingClusterMap(): array
    {
        return [
            '5'                       => 'genScenes',
            '6'                       => 'genOnOff',
            '8'                       => 'genLevelCtrl',
            '258'                     => 'closuresWindowCovering',
            '768'                     => 'lightingColorCtrl',
            'genScenes'               => 'genScenes',
            'genOnOff'                => 'genOnOff',
            'genLevelCtrl'            => 'genLevelCtrl',
            'closuresWindowCovering'  => 'closuresWindowCovering',
            'lightingColorCtrl'       => 'lightingColorCtrl'
        ];
    }

    /**
     * Erhaelt einen aktuell gewaehlt Cluster, wenn er weiter verfuegbar ist.
     */
    private function ResolveBindingClusterSelection(string $currentCluster, array $options): string
    {
        $values = array_column($options, 'value');
        if ($currentCluster !== '' && \in_array($currentCluster, $values, true)) {
            return $currentCluster;
        }

        foreach ($values as $value) {
            if ($value !== '') {
                return (string) $value;
            }
        }

        return '';
    }

    /**
     * Liest lokale Zigbee2MQTT-Geraeteinstanzen als Binding-Ziele.
     */
    private function LoadBindingTargetDevicesFromInstances(): array
    {
        $baseTopic = $this->ReadPropertyString(self::MQTT_BASE_TOPIC);
        if ($baseTopic === '') {
            return [];
        }

        $targets = [];
        foreach (IPS_GetInstanceListByModuleID(self::GUID_MODULE_DEVICE) as $instanceID) {
            if ($instanceID === $this->InstanceID || @IPS_GetProperty($instanceID, self::MQTT_BASE_TOPIC) !== $baseTopic) {
                continue;
            }

            $topic = trim((string) @IPS_GetProperty($instanceID, self::MQTT_TOPIC));
            if ($topic === '') {
                continue;
            }

            $targets[] = [
                'caption' => $this->BuildBindingTargetCaption('Device', $topic, @IPS_GetName($instanceID)),
                'value'   => $topic
            ];
        }

        return $targets;
    }

    /**
     * Liest lokale Zigbee2MQTT-Gruppeninstanzen als Binding-Ziele.
     */
    private function LoadBindingTargetGroupsFromInstances(): array
    {
        $baseTopic = $this->ReadPropertyString(self::MQTT_BASE_TOPIC);
        if ($baseTopic === '') {
            return [];
        }

        $targets = [];
        foreach (IPS_GetInstanceListByModuleID(self::GUID_MODULE_GROUP) as $instanceID) {
            if (@IPS_GetProperty($instanceID, self::MQTT_BASE_TOPIC) !== $baseTopic) {
                continue;
            }

            $topic = trim((string) @IPS_GetProperty($instanceID, self::MQTT_TOPIC));
            if ($topic === '') {
                continue;
            }

            $targets[] = [
                'caption' => $this->BuildBindingTargetCaption('Group', $topic, @IPS_GetName($instanceID)),
                'value'   => $topic
            ];
        }

        return $targets;
    }

    /**
     * Fragt Zigbee2MQTT nach bekannten Geraeten als Binding-Ziele.
     */
    private function LoadBindingTargetDevicesFromExtension(): array
    {
        if (!$this->HasActiveParent()) {
            return [];
        }

        $currentTopic = $this->ReadPropertyString(self::MQTT_TOPIC);
        $result = @$this->SendData(self::SYMCON_EXTENSION_LIST_REQUEST . 'getDevices', [], 2500);
        if (!\is_array($result) || !\is_array($result['list'] ?? null)) {
            return [];
        }

        $targets = [];
        foreach ($result['list'] as $device) {
            if (!\is_array($device) || ($device['type'] ?? '') === 'Coordinator') {
                continue;
            }

            $topic = trim((string) ($device['friendly_name'] ?? ''));
            if ($topic === '' || $topic === $currentTopic) {
                continue;
            }

            $targets[] = [
                'caption' => $this->BuildBindingTargetCaption('Device', $topic, (string) ($device['model'] ?? '')),
                'value'   => $topic
            ];
        }

        return $targets;
    }

    /**
     * Fragt Zigbee2MQTT nach bekannten Gruppen als Binding-Ziele.
     */
    private function LoadBindingTargetGroupsFromExtension(): array
    {
        if (!$this->HasActiveParent()) {
            return [];
        }

        $result = @$this->SendData(self::SYMCON_EXTENSION_LIST_REQUEST . 'getGroups', [], 2500);
        if (!\is_array($result) || !\is_array($result['list'] ?? null)) {
            return [];
        }

        $targets = [];
        foreach ($result['list'] as $group) {
            if (!\is_array($group)) {
                continue;
            }

            $topic = trim((string) ($group['friendly_name'] ?? ''));
            if ($topic === '') {
                continue;
            }

            $targets[] = [
                'caption' => $this->BuildBindingTargetCaption('Group', $topic, ''),
                'value'   => $topic
            ];
        }

        return $targets;
    }

    /**
     * Erzeugt eine lesbare Beschriftung fuer Binding-Zielauswahlen.
     */
    private function BuildBindingTargetCaption(string $type, string $topic, string $suffix): string
    {
        $suffix = trim($suffix);
        $caption = $this->Translate($type) . ': ' . $topic;
        if ($suffix === '' || $suffix === $topic) {
            return $caption;
        }

        return $caption . ' (' . $suffix . ')';
    }

    /**
     * Ermittelt alle bekannten Optionsdefinitionen.
     */
    private function BuildDeviceOptionDefinitionMap(): array
    {
        $definitions = $this->BuildGenericDeviceOptionDefinitions();
        foreach ($this->ReadAttributeArray(self::ATTRIBUTE_DEVICE_OPTION_DEFINITIONS) as $definition) {
            if (!\is_array($definition)) {
                continue;
            }

            $name = (string) ($definition['property'] ?? $definition['name'] ?? '');
            if ($name === '' || $this->ShouldSkipDeviceOption($name)) {
                continue;
            }

            $definitions[$name] = array_merge(
                $definitions[$name] ?? [],
                $definition,
                ['name' => $name]
            );
        }

        return $definitions;
    }

    /**
     * Liefert die wichtigsten allgemeinen Z2M-Geraeteoptionen.
     */
    private function BuildGenericDeviceOptionDefinitions(): array
    {
        return [
            'debounce' => [
                'name'        => 'debounce',
                'label'       => 'Debounce',
                'type'        => 'numeric',
                'description' => 'Delay publishing repeated device messages.'
            ],
            'debounce_ignore' => [
                'name'        => 'debounce_ignore',
                'label'       => 'Debounce ignore',
                'type'        => 'array',
                'description' => 'Attributes ignored by debounce.',
                'editor'      => 'attributes'
            ],
            'disable_automatic_update_check' => [
                'name'        => 'disable_automatic_update_check',
                'label'       => 'Disable automatic update check',
                'type'        => 'binary',
                'description' => 'Disable automatic firmware update checks initiated by Zigbee2MQTT for this device.'
            ],
            'disabled' => [
                'name'        => 'disabled',
                'label'       => 'Disabled',
                'type'        => 'binary',
                'description' => 'Disable this device in Zigbee2MQTT.'
            ],
            'filtered_attributes' => [
                'name'        => 'filtered_attributes',
                'label'       => 'Filtered attributes',
                'type'        => 'array',
                'description' => 'Attributes not published by Zigbee2MQTT.',
                'editor'      => 'attributes'
            ],
            'filtered_cache' => [
                'name'        => 'filtered_cache',
                'label'       => 'Filtered cache',
                'type'        => 'array',
                'description' => 'Attributes not written to the Zigbee2MQTT cache.',
                'editor'      => 'attributes'
            ],
            'filtered_optimistic' => [
                'name'        => 'filtered_optimistic',
                'label'       => 'Filtered optimistic',
                'type'        => 'array',
                'description' => 'Optimistic attributes not published by Zigbee2MQTT.',
                'editor'      => 'attributes'
            ],
            'homeassistant' => [
                'name'        => 'homeassistant',
                'label'       => 'Home Assistant',
                'type'        => 'object',
                'description' => 'Override Home Assistant discovery properties for this device.'
            ],
            'icon' => [
                'name'        => 'icon',
                'label'       => 'Icon',
                'type'        => 'text',
                'description' => 'Override the Zigbee2MQTT frontend icon.'
            ],
            'optimistic' => [
                'name'        => 'optimistic',
                'label'       => 'Optimistic',
                'type'        => 'binary',
                'description' => 'Update the state optimistically after successful commands.'
            ],
            'qos' => [
                'name'        => 'qos',
                'label'       => 'QoS',
                'type'        => 'enum',
                'description' => 'MQTT quality of service.',
                'default'     => null,
                'values'      => [
                    ['caption' => '-', 'value' => 'null', 'actual' => null],
                    ['caption' => '0', 'value' => '0', 'actual' => 0],
                    ['caption' => '1', 'value' => '1', 'actual' => 1],
                    ['caption' => '2', 'value' => '2', 'actual' => 2]
                ]
            ],
            'retain' => [
                'name'        => 'retain',
                'label'       => 'Retain',
                'type'        => 'binary',
                'description' => 'Retain MQTT messages for this device.'
            ],
            'retention' => [
                'name'        => 'retention',
                'label'       => 'Retention',
                'type'        => 'numeric',
                'description' => 'MQTT message expiry in seconds.'
            ],
            'throttle' => [
                'name'        => 'throttle',
                'label'       => 'Throttle',
                'type'        => 'numeric',
                'description' => 'Throttle processing of messages from this device.'
            ],
            'transition' => [
                'name'        => 'transition',
                'label'       => 'Transition',
                'type'        => 'numeric',
                'description' => 'Default transition time in seconds.'
            ]
        ];
    }

    /**
     * Dekodiert den JSON-Payload aus Formularaktionen.
     */
    private function DecodeDeviceOptionFormPayload(mixed $value): ?array
    {
        if (\is_array($value)) {
            return $value;
        }

        if (!\is_string($value)) {
            return null;
        }

        $decoded = json_decode($value, true);
        return \is_array($decoded) ? $decoded : null;
    }

    /**
     * Schaltet den passenden Editor fuer eine Geraeteoption sichtbar.
     */
    private function ConfigureDeviceOptionEditor(string $name, string $rawValue): void
    {
        $definition = $this->GetDeviceOptionDefinition($name);
        $editor = $this->GetDeviceOptionEditor($definition);

        $this->UpdateFormField('DeviceOptionEditor', 'value', $editor);
        $this->UpdateFormField('DeviceOptionValue', 'visible', $editor === 'text');
        $this->UpdateFormField('DeviceOptionBoolean', 'visible', $editor === 'boolean');
        $this->UpdateFormField('DeviceOptionSelect', 'visible', $editor === 'select');
        $this->UpdateFormField('DeviceOptionAttributeList', 'visible', $editor === 'attributes');
        $this->UpdateFormField('DeviceOptionAttributeEditor', 'visible', $editor === 'attributes');

        if ($editor === 'boolean') {
            $this->UpdateFormField('DeviceOptionBoolean', 'value', $this->ParseDeviceOptionBoolean($rawValue));
            return;
        }

        if ($editor === 'select') {
            $options = $this->BuildDeviceOptionSelectOptions($definition);
            $this->UpdateFormField('DeviceOptionSelect', 'options', json_encode($options));
            $this->UpdateFormField('DeviceOptionSelect', 'value', $this->NormalizeDeviceOptionSelectValue($rawValue, $definition));
            return;
        }

        if ($editor === 'attributes') {
            $selected = $this->DecodeDeviceOptionAttributeSelection($rawValue);
            $this->UpdateDeviceOptionAttributeEditor($selected);
            return;
        }

        $this->UpdateFormField('DeviceOptionValue', 'value', $rawValue);
    }

    /**
     * Ermittelt aus den sichtbaren Formularfeldern den Rohwert.
     */
    private function ResolveDeviceOptionRawValue(string $name, array $selection): mixed
    {
        $definition = $this->GetDeviceOptionDefinition($name);
        $editor = trim((string) ($selection['editor'] ?? ''));
        if ($editor === '') {
            $editor = $this->GetDeviceOptionEditor($definition);
        }

        return match ($editor) {
            'boolean'   => $selection['boolean'] ?? $selection['value'] ?? false,
            'select'    => (string) ($selection['selection'] ?? $selection['value'] ?? ''),
            'attributes' => (string) ($selection['value'] ?? '[]'),
            default     => (string) ($selection['value'] ?? '')
        };
    }

    /**
     * Liefert die bekannte Definition einer Geraeteoption.
     */
    private function GetDeviceOptionDefinition(string $name): array
    {
        return $this->BuildDeviceOptionDefinitionMap()[$name] ?? [
            'type'        => 'mixed',
            'description' => 'Option returned by Zigbee2MQTT.'
        ];
    }

    /**
     * Liefert den Formulareditor passend zum Optionstyp.
     */
    private function GetDeviceOptionEditor(array $definition): string
    {
        if (($definition['editor'] ?? '') === 'attributes') {
            return 'attributes';
        }

        $type = $this->NormalizeDeviceOptionType((string) ($definition['type'] ?? 'mixed'));
        if ($type === 'binary') {
            return 'boolean';
        }
        if ($type === 'enum' && \is_array($definition['values'] ?? null)) {
            return 'select';
        }

        return 'text';
    }

    /**
     * Baut die Auswahlwerte fuer eine Enum-Option.
     */
    private function BuildDeviceOptionSelectOptions(array $definition): array
    {
        $options = [];
        foreach (($definition['values'] ?? []) as $value) {
            if (\is_array($value)) {
                $optionValue = (string) ($value['value'] ?? $value['name'] ?? '');
                $options[] = [
                    'caption' => (string) ($value['caption'] ?? $value['label'] ?? $optionValue),
                    'value'   => $optionValue
                ];
                continue;
            }

            $optionValue = (string) $value;
            $options[] = [
                'caption' => $optionValue,
                'value'   => $optionValue
            ];
        }

        return $options;
    }

    /**
     * Normalisiert den aktuellen Wert fuer das Select-Feld.
     */
    private function NormalizeDeviceOptionSelectValue(string $rawValue, array $definition): string
    {
        if ($rawValue === '' && \array_key_exists('default', $definition)) {
            return $this->FormatDeviceOptionValue($definition['default']);
        }

        if ($rawValue === '-') {
            return 'null';
        }

        return $rawValue;
    }

    /**
     * Fuegt ein Attribut in einen Listen-Editor ein.
     */
    protected function AddDeviceOptionAttributeFromForm(mixed $value): bool
    {
        $selection = $this->DecodeDeviceOptionFormPayload($value);
        if ($selection === null) {
            return false;
        }

        $attribute = trim((string) ($selection['attribute'] ?? ''));
        $selected = $this->DecodeDeviceOptionAttributeSelection((string) ($selection['value'] ?? '[]'));
        if ($attribute !== '' && !\in_array($attribute, $selected, true)) {
            $selected[] = $attribute;
        }

        $this->UpdateDeviceOptionAttributeEditor($selected);
        return true;
    }

    /**
     * Entfernt ein Attribut aus einem Listen-Editor.
     */
    protected function RemoveDeviceOptionAttributeFromForm(mixed $value): bool
    {
        $selection = $this->DecodeDeviceOptionFormPayload($value);
        if ($selection === null) {
            return false;
        }

        $attribute = trim((string) ($selection['attribute'] ?? ''));
        $selected = array_values(array_filter(
            $this->DecodeDeviceOptionAttributeSelection((string) ($selection['value'] ?? '[]')),
            static fn (string $entry): bool => $entry !== $attribute
        ));

        $this->UpdateDeviceOptionAttributeEditor($selected);
        return true;
    }

    /**
     * Aktualisiert Listen, Auswahl und Hidden-JSON fuer Attribut-Optionen.
     */
    private function UpdateDeviceOptionAttributeEditor(array $selected): void
    {
        $selected = $this->NormalizeDeviceOptionAttributes($selected);
        $listValues = $this->BuildDeviceOptionAttributeFormValues($selected);
        $candidateOptions = $this->BuildDeviceOptionAttributeCandidateOptions($selected);

        $this->UpdateFormField('DeviceOptionValue', 'value', $this->FormatDeviceOptionValue($selected));
        $this->UpdateFormField('DeviceOptionAttributeList', 'values', json_encode($listValues));
        $this->UpdateFormField('DeviceOptionAttributeList', 'rowCount', min(8, max(3, \count($listValues) + 1)));
        $this->UpdateFormField('DeviceOptionAttributeCandidate', 'options', json_encode($candidateOptions));
        $this->UpdateFormField('DeviceOptionAttributeCandidate', 'value', (string) ($candidateOptions[0]['value'] ?? ''));
    }

    /**
     * Baut die Liste der aktuell gewaehlten Attribute.
     */
    private function BuildDeviceOptionAttributeFormValues(array $selected): array
    {
        $values = [];
        foreach ($this->NormalizeDeviceOptionAttributes($selected) as $attribute) {
            $values[] = [
                'attribute' => $attribute,
                'action'    => $this->Translate('Remove')
            ];
        }

        return $values;
    }

    /**
     * Baut die noch waehlbaren Attribute aus Exposes, Variablen und aktuellem Wert.
     */
    private function BuildDeviceOptionAttributeCandidateOptions(array $selected): array
    {
        $selected = $this->NormalizeDeviceOptionAttributes($selected);
        $candidates = array_values(array_diff($this->BuildDeviceOptionAttributeCandidates($selected), $selected));

        return array_map(
            static fn (string $attribute): array => [
                'caption' => $attribute,
                'value'   => $attribute
            ],
            $candidates
        );
    }

    /**
     * Ermittelt die Attribute, die dieses Geraet potentiell im Payload anbietet.
     */
    private function BuildDeviceOptionAttributeCandidates(array $selected = []): array
    {
        $candidates = [];
        $this->CollectDevicePayloadAttributes($this->ReadAttributeArray(self::ATTRIBUTE_EXPOSES), $candidates);
        $candidates = array_merge($candidates, array_keys($this->ReadAttributeArray(self::ATTRIBUTE_VARIABLE_CATALOG)));

        if (@IPS_ObjectExists($this->InstanceID)) {
            foreach (IPS_GetChildrenIDs($this->InstanceID) as $childID) {
                $object = @IPS_GetObject($childID);
                $ident = (string) ($object['ObjectIdent'] ?? '');
                if ($ident !== '') {
                    $candidates[] = $ident;
                }
            }
        }

        $candidates = array_merge($candidates, $selected);
        $candidates = array_values(array_unique(array_filter(array_map('strval', $candidates), [$this, 'IsDeviceOptionAttributeCandidate'])));
        usort($candidates, static fn (string $left, string $right): int => strnatcasecmp($left, $right));
        return $candidates;
    }

    /**
     * Sammelt Payload-Properties aus Expose-Strukturen.
     */
    private function CollectDevicePayloadAttributes(mixed $node, array &$candidates): void
    {
        if (!\is_array($node)) {
            return;
        }

        if (array_is_list($node)) {
            foreach ($node as $child) {
                $this->CollectDevicePayloadAttributes($child, $candidates);
            }

            return;
        }

        $property = (string) ($node['property'] ?? '');
        if ($property !== '') {
            $candidates[] = $property;
        }

        foreach ($node as $key => $value) {
            if (!\is_array($value)) {
                continue;
            }

            if (\in_array($key, ['features', 'exposes'], true) || array_is_list($value)) {
                $this->CollectDevicePayloadAttributes($value, $candidates);
            }
        }
    }

    /**
     * Normalisiert den aktuellen Wert fuer Attribut-Optionen.
     */
    private function DecodeDeviceOptionAttributeSelection(string $rawValue): array
    {
        try {
            return $this->ParseDeviceOptionArray($rawValue);
        } catch (\InvalidArgumentException) {
            return [];
        }
    }

    /**
     * Entfernt leere und doppelte Eintraege.
     */
    private function NormalizeDeviceOptionAttributes(array $attributes): array
    {
        $normalized = array_values(array_unique(array_filter(array_map(
            static fn (mixed $attribute): string => trim((string) $attribute),
            $attributes
        ), [$this, 'IsDeviceOptionAttributeCandidate'])));

        usort($normalized, static fn (string $left, string $right): int => strnatcasecmp($left, $right));
        return $normalized;
    }

    /**
     * Filtert technische oder leere Eintraege aus der Attributauswahl.
     */
    private function IsDeviceOptionAttributeCandidate(string $attribute): bool
    {
        return $attribute !== ''
            && !\in_array($attribute, [
                'debounce_ignore',
                'filtered_attributes',
                'filtered_cache',
                'filtered_optimistic',
                'friendly_name'
            ], true);
    }

    /**
     * Konvertiert den Formularwert passend zum bekannten Optionstyp.
     */
    private function ParseDeviceOptionValue(string $name, mixed $rawValue): mixed
    {
        $definition = $this->BuildDeviceOptionDefinitionMap()[$name] ?? [];
        $type = $this->NormalizeDeviceOptionType((string) ($definition['type'] ?? 'mixed'));

        return match ($type) {
            'binary'  => $this->ParseDeviceOptionBoolean($rawValue),
            'numeric' => $this->ParseDeviceOptionNumber((string) $rawValue),
            'enum'    => $this->ParseDeviceOptionEnum((string) $rawValue, $definition),
            'array'   => $this->ParseDeviceOptionArray((string) $rawValue),
            'object'  => $this->ParseDeviceOptionObject((string) $rawValue),
            'text'    => (string) $rawValue,
            default   => $this->ParseDeviceOptionMixed((string) $rawValue)
        };
    }

    /**
     * Sendet die Aenderung bevorzugt ueber die Bridge-Instanz mit Antwortpruefung.
     */
    private function SendDeviceOptionsRequest(array $options): bool
    {
        $deviceName = $this->ReadPropertyString(self::MQTT_TOPIC);
        if ($deviceName === '') {
            trigger_error($this->Translate('MQTTTopic not configured.'), E_USER_NOTICE);
            return false;
        }

        $bridgeID = $this->FindMatchingBridgeInstanceID();
        if ($bridgeID !== false && \function_exists('Z2M_SetDeviceOptions')) {
            return \Z2M_SetDeviceOptions(
                $bridgeID,
                $deviceName,
                json_encode($options, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            );
        }

        return $this->SendData('/bridge/request/device/options', [
            'id'      => $deviceName,
            'options' => $options
        ], 0) === true;
    }

    /**
     * Fuehrt Binding oder Unbinding aus.
     */
    private function ApplyBindingRequestFromForm(mixed $value, bool $unbind): bool
    {
        $selection = $this->DecodeDeviceOptionFormPayload($value);
        if ($selection === null) {
            return false;
        }

        $target = trim((string) ($selection['target'] ?? ''));
        if ($target === '') {
            trigger_error($this->Translate('Target device or group is required.'), E_USER_NOTICE);
            return false;
        }

        $endpoint = trim((string) ($selection['endpoint'] ?? ''));
        $source = $this->BuildEndpointQualifiedDeviceName($endpoint);
        $clusters = trim((string) ($selection['clusters'] ?? ''));
        $skipDisableReporting = (bool) ($selection['skip_disable_reporting'] ?? false);

        return $this->CallMatchingBridgeFunction($unbind ? 'UnbindWithOptions' : 'BindWithOptions', [
            $source,
            $target,
            $clusters,
            $skipDisableReporting
        ]) === true;
    }

    /**
     * Baut einen Device-Namen mit optionalem Endpoint-Suffix.
     */
    private function BuildEndpointQualifiedDeviceName(string $endpoint): string
    {
        $deviceName = $this->ReadPropertyString(self::MQTT_TOPIC);
        if ($endpoint === '') {
            return $deviceName;
        }

        return $deviceName . '/' . $endpoint;
    }

    /**
     * Ruft eine Funktion der passenden Bridge-Instanz auf.
     */
    protected function CallMatchingBridgeFunction(string $function, array $arguments): mixed
    {
        $bridgeID = $this->FindMatchingBridgeInstanceID();
        $functionName = 'Z2M_' . $function;
        if ($bridgeID === false || !\function_exists($functionName)) {
            trigger_error($this->Translate('No matching bridge instance found.'), E_USER_NOTICE);
            return false;
        }

        return $functionName($bridgeID, ...$arguments);
    }

    /**
     * Findet die Bridge-Instanz zum gleichen MQTT-Basistopic.
     */
    protected function FindMatchingBridgeInstanceID(): int|false
    {
        $baseTopic = $this->ReadPropertyString(self::MQTT_BASE_TOPIC);
        foreach (IPS_GetInstanceListByModuleID(self::GUID_MODULE_BRIDGE) as $bridgeID) {
            if (@IPS_GetProperty($bridgeID, self::MQTT_BASE_TOPIC) === $baseTopic) {
                return $bridgeID;
            }
        }

        return false;
    }

    /**
     * Normalisiert Z2M- und Formular-Typbezeichnungen.
     */
    private function NormalizeDeviceOptionType(string $type): string
    {
        return match (strtolower($type)) {
            'bool',
            'boolean',
            'binary' => 'binary',
            'float',
            'integer',
            'number',
            'numeric' => 'numeric',
            'list',
            'array' => 'array',
            'composite',
            'object' => 'object',
            'enum',
            'select' => 'enum',
            'string',
            'text' => 'text',
            default => 'mixed'
        };
    }

    /**
     * Liefert eine lesbare Typbezeichnung fuer die Optionsliste.
     */
    private function FormatDeviceOptionType(string $type): string
    {
        return match ($this->NormalizeDeviceOptionType($type)) {
            'binary'  => 'Boolean',
            'numeric' => 'Number',
            'enum'    => 'Selection',
            'array'   => 'Array',
            'object'  => 'Object',
            'text'    => 'Text',
            default   => 'Mixed'
        };
    }

    /**
     * Erkennt den Typ aus einem aktuellen Optionswert.
     */
    private function DetectDeviceOptionType(mixed $value): string
    {
        if (\is_bool($value)) {
            return 'binary';
        }
        if (\is_int($value) || \is_float($value)) {
            return 'numeric';
        }
        if (\is_array($value)) {
            return array_is_list($value) ? 'array' : 'object';
        }
        if (\is_string($value)) {
            return 'text';
        }

        return 'mixed';
    }

    /**
     * Formatiert aktuelle Optionswerte fuer die Liste.
     */
    private function FormatDeviceOptionValue(mixed $value): string
    {
        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if ($value === null) {
            return 'null';
        }
        if (\is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return (string) $value;
    }

    /**
     * Baut eine lesbare Optionsbeschriftung aus dem Optionsnamen.
     */
    private function BuildDeviceOptionLabel(string $name): string
    {
        return ucwords(str_replace('_', ' ', $name));
    }

    /**
     * Formatiert Endpoint-Clusterlisten kompakt fuer die Formularliste.
     */
    private function FormatEndpointListValue(mixed $value): string
    {
        if (!\is_array($value)) {
            return '';
        }

        return implode(', ', array_map(static fn (mixed $entry): string => (string) $entry, $value));
    }

    /**
     * Blendet Optionen aus, die ueber dedizierte Z2M-Endpunkte gepflegt werden sollten.
     */
    private function ShouldSkipDeviceOption(string $name): bool
    {
        return \in_array($name, ['friendly_name'], true);
    }

    /**
     * Wandelt Formularwerte in boolesche Optionswerte.
     */
    private function ParseDeviceOptionBoolean(mixed $rawValue): bool
    {
        if (\is_bool($rawValue)) {
            return $rawValue;
        }

        $normalized = strtolower(trim((string) $rawValue));
        if (\in_array($normalized, ['true', '1', 'on', 'yes', 'ja', 'an'], true)) {
            return true;
        }
        if (\in_array($normalized, ['false', '0', 'off', 'no', 'nein', 'aus', ''], true)) {
            return false;
        }

        throw new \InvalidArgumentException('Device option value must be true or false.');
    }

    /**
     * Wandelt Formularwerte in numerische Optionswerte.
     */
    private function ParseDeviceOptionNumber(string $rawValue): int|float
    {
        $normalized = str_replace(',', '.', $rawValue);
        if (!is_numeric($normalized)) {
            throw new \InvalidArgumentException('Device option value must be numeric.');
        }

        return str_contains($normalized, '.') ? (float) $normalized : (int) $normalized;
    }

    /**
     * Wandelt Formularwerte in Enum-Optionswerte.
     */
    private function ParseDeviceOptionEnum(string $rawValue, array $definition): mixed
    {
        if (!\is_array($definition['values'] ?? null)) {
            return $rawValue;
        }

        foreach ($definition['values'] as $option) {
            if (\is_array($option)) {
                $optionValue = (string) ($option['value'] ?? $option['name'] ?? '');
                if ($optionValue !== $rawValue) {
                    continue;
                }

                return \array_key_exists('actual', $option) ? $option['actual'] : $optionValue;
            }

            if ((string) $option === $rawValue) {
                return (string) $option;
            }
        }

        throw new \InvalidArgumentException('Device option value is not allowed.');
    }

    /**
     * Wandelt Formularwerte in Array-Optionswerte.
     */
    private function ParseDeviceOptionArray(string $rawValue): array
    {
        if ($rawValue === '') {
            return [];
        }

        $decoded = json_decode($rawValue, true);
        if (\is_array($decoded) && array_is_list($decoded)) {
            return $decoded;
        }

        if (str_starts_with($rawValue, '[')) {
            throw new \InvalidArgumentException('Device option value must be a JSON array.');
        }

        return array_values(array_filter(array_map('trim', explode(',', $rawValue)), static fn (string $entry): bool => $entry !== ''));
    }

    /**
     * Wandelt Formularwerte in Objekt-Optionswerte.
     */
    private function ParseDeviceOptionObject(string $rawValue): array
    {
        $rawValue = trim($rawValue);
        if ($rawValue === '') {
            return [];
        }

        $decoded = json_decode($rawValue, true);
        if (\is_array($decoded) && !array_is_list($decoded)) {
            return $decoded;
        }

        throw new \InvalidArgumentException('Device option value must be a JSON object.');
    }

    /**
     * Wandelt unbekannte Typen bestmoeglich in JSON-, Zahlen-, Boolean- oder Textwerte.
     */
    private function ParseDeviceOptionMixed(string $rawValue): mixed
    {
        if ($rawValue === '') {
            return '';
        }

        $decoded = json_decode($rawValue, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        if (is_numeric(str_replace(',', '.', $rawValue))) {
            return $this->ParseDeviceOptionNumber($rawValue);
        }

        return $rawValue;
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
        if ($tiles['SensorTile']['enabled'] && $this->ShouldForceSensorTile()) {
            return $tiles['SensorTile']['label'];
        }
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
