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
            'enabled'   => $sensorSelectable && $this->ReadPropertyBoolean(self::PROPERTY_USE_SENSOR_TILE),
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
     * Zeigt den optionalen Kelvin-Bereich fuer Farbtemperatur-Leuchten.
     */
    private function ConfigureDeviceFormColorTemperatureVisualization(array &$form): void
    {
        $hasColorTemperature = $this->GetObjectIDByIdent('color_temp_kelvin') !== false
            || $this->DeviceFormHasExposeProperty('color_temp');
        $hasCustomColorTemperatureRange =
            $this->ReadPropertyInteger(self::PROPERTY_COLOR_TEMPERATURE_PRESENTATION_MIN) > 0
            || $this->ReadPropertyInteger(self::PROPERTY_COLOR_TEMPERATURE_PRESENTATION_MAX) > 0;

        $this->SetDeviceFormField(
            $form,
            'ColorTemperatureVisualization',
            'visible',
            $hasColorTemperature || $hasCustomColorTemperatureRange
        );
    }

    /**
     * Prueft die gespeicherten Exposes rekursiv auf eine Property.
     */
    private function DeviceFormHasExposeProperty(string $property): bool
    {
        foreach ($this->ReadAttributeArray(self::ATTRIBUTE_EXPOSES) as $expose) {
            if (\is_array($expose) && $this->DeviceFormFeatureTreeHasProperty($expose, $property)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Rekursive Suche in einem Expose-Baum.
     */
    private function DeviceFormFeatureTreeHasProperty(array $feature, string $property): bool
    {
        if (($feature['property'] ?? null) === $property) {
            return true;
        }

        $subFeatures = $feature['features'] ?? [];
        if (!\is_array($subFeatures)) {
            return false;
        }

        foreach ($subFeatures as $subFeature) {
            if (\is_array($subFeature) && $this->DeviceFormFeatureTreeHasProperty($subFeature, $property)) {
                return true;
            }
        }

        return false;
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
        $this->SetDeviceFormField($form, 'BindingReportingSettings', 'visible', \count($values) > 0);
        $this->SetDeviceFormField($form, 'EndpointList', 'values', $values);
        $this->SetDeviceFormField($form, 'EndpointList', 'rowCount', min(10, max(4, \count($values) + 1)));
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
        $this->UpdateFormField('DeviceOptionValue', 'value', $selection['value'] ?? '');

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
            $parsedValue = $this->ParseDeviceOptionValue($name, (string) ($selection['value'] ?? ''));
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
                'type'        => $this->Translate($type),
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
                'description' => 'Attributes ignored by debounce.'
            ],
            'filtered_attributes' => [
                'name'        => 'filtered_attributes',
                'label'       => 'Filtered attributes',
                'type'        => 'array',
                'description' => 'Attributes not published by Zigbee2MQTT.'
            ],
            'filtered_cache' => [
                'name'        => 'filtered_cache',
                'label'       => 'Filtered cache',
                'type'        => 'array',
                'description' => 'Attributes not written to the Zigbee2MQTT cache.'
            ],
            'filtered_optimistic' => [
                'name'        => 'filtered_optimistic',
                'label'       => 'Filtered optimistic',
                'type'        => 'array',
                'description' => 'Optimistic attributes not published by Zigbee2MQTT.'
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
                'type'        => 'numeric',
                'description' => 'MQTT quality of service.'
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
     * Konvertiert den Formularwert passend zum bekannten Optionstyp.
     */
    private function ParseDeviceOptionValue(string $name, string $rawValue): mixed
    {
        $rawValue = trim($rawValue);
        $definition = $this->BuildDeviceOptionDefinitionMap()[$name] ?? [];
        $type = $this->NormalizeDeviceOptionType((string) ($definition['type'] ?? 'mixed'));

        return match ($type) {
            'binary'  => $this->ParseDeviceOptionBoolean($rawValue),
            'numeric' => $this->ParseDeviceOptionNumber($rawValue),
            'array'   => $this->ParseDeviceOptionArray($rawValue),
            'object'  => $this->ParseDeviceOptionObject($rawValue),
            'text',
            'enum'    => $rawValue,
            default   => $this->ParseDeviceOptionMixed($rawValue)
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
            'enum' => 'enum',
            'string',
            'text' => 'text',
            default => 'mixed'
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
    private function ParseDeviceOptionBoolean(string $rawValue): bool
    {
        $normalized = strtolower($rawValue);
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
