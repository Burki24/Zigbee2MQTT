<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/libs/ModulBase.php';
require_once dirname(__DIR__) . '/libs/RoutingTableRefresh.php';

/**
 * Repräsentiert ein einzelnes Zigbee2MQTT-Gerät in Symcon.
 */
class Zigbee2MQTTDevice extends \Zigbee2MQTT\ModulBase
{
    use \Zigbee2MQTT\RoutingTableRefresh;

    private const DEVICE_ROUTING_TABLE = 'DeviceRoutingTable';
    private const DEVICE_OBSERVED_ROUTE = 'DeviceObservedRoute';
    private const OBSERVED_ROUTE_TOPIC = '/SymconExtension/route/';
    private const ICON_CACHE_DIRECTORY = 'icons';
    private const ICON_DOWNLOAD_MAX_BYTES = 2 * 1024 * 1024;
    private const ICON_DOWNLOAD_TIMEOUT_SECONDS = 5;
    private const ICON_MAX_DIMENSION = 4096;

    /** @var mixed $ExtensionTopic Topic für den ReceiveFilter*/
    protected static $ExtensionTopic = 'getDeviceInfo/';

    /** Temporär erkannte IEEE-Adresse für die aktuell verarbeitete Formularanfrage. */
    private string $detectedIEEEFormValue = '';

    /**
     * Initialisiert Eigenschaften, Attribute und Status der Geräteinstanz.
     *
     * @return void
     */
    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyString('IEEE', '');
        $this->RegisterAttributeString('IEEE', '');
        $this->RegisterAttributeString('Model', '');
        $this->RegisterAttributeString('Icon', '');
        $this->RegisterAttributeArray(self::DEVICE_ROUTING_TABLE, []);
        $this->RegisterAttributeArray(self::DEVICE_OBSERVED_ROUTE, []);
        $this->RegisterRoutingTableRefresh();
        $this->RegisterMessage($this->InstanceID, IM_CHANGEATTRIBUTE);
    }

    /**
     * ApplyChanges
     *
     * @return void
     */
    public function ApplyChanges(): void
    {
        $ieee = $this->ReadPropertyString('IEEE');
        $this->SetSummary($ieee);
        if (empty($ieee)) {
            if ($this->GetStatus() == IS_ACTIVE) {
                $this->LogMessage($this->Translate('No IEEE address configured'), KL_WARNING);
            }
        } else {
            if ($this->ReadAttributeString('IEEE') != $ieee) {
                $this->WriteAttributeString('IEEE', $ieee);
            }
        }

        // Führe parent::ApplyChanges zuerst aus
        parent::ApplyChanges();
        $this->WithRoutingTableLock(fn () => $this->ClearRoutingTableRefresh());
        $this->MigratePersistedDeviceIconToCache();
    }

    /**
     * MessageSink
     *
     * @param  mixed $Time
     * @param  mixed $SenderID
     * @param  mixed $Message
     * @param  mixed $Data
     * @return void
     */
    public function MessageSink(int $Time, int $SenderID, int $Message, array $Data): void
    {
        parent::MessageSink($Time, $SenderID, $Message, $Data);
        if ($SenderID != $this->InstanceID) {
            return;
        }
        switch ($Message) {
            case IM_CHANGEATTRIBUTE:
                if (($Data[0] == 'IEEE') && (!empty($Data[1])) && ($this->GetStatus() == IS_CREATING)) {
                    $this->UpdateDeviceInfo();
                }
                return;
        }
    }

    /**
     * GetConfigurationForm
     *
     * @return string
     */
    public function GetConfigurationForm(): string
    {
        return \Zigbee2MQTT\ModuleUpdateGuard::Execute(
            fn (): string => $this->GetConfigurationFormWithAvailableInstanceInterface(),
            '{}'
        );
    }

    /**
     * GetVisualizationTile
     *
     * Liefert die passende HTML-SDK-Kachel fuer das aktuelle Geraet.
     *
     * @return string
     */
    public function GetVisualizationTile(): string
    {
        $tile = $this->GetVisualizationTileDefinition();
        if ($tile === null) {
            return '';
        }

        $tilePath = dirname(__DIR__) . '/libs/Visualization/tiles/';
        $html = file_get_contents($tilePath . $tile['template']);
        $themeSupport = is_file($tilePath . 'theme_support.html') ? file_get_contents($tilePath . 'theme_support.html') : '';
        if (!\is_string($themeSupport)) {
            $themeSupport = '';
        }
        $data = $tile['data']();

        return str_replace(
            ['__THEME_SUPPORT__', '__INITIAL_DATA__'],
            [
                $themeSupport,
                json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)
            ],
            $html
        );
    }
    /**
     * RequestAction
     *
     * @param  string $ident
     * @param  mixed $value
     * @return void
     */
    public function RequestAction(string $ident, mixed $value): void
    {
        switch ($ident) {
            case 'RefreshDeviceRoutingTable':
                $this->WithRoutingTableLock(fn () => $this->RefreshDeviceRoutingTable());
                return;

            case 'CheckRoutingTableRefresh':
                $this->CheckRoutingTableRefresh();
                return;

            case 'UpdateInfo':
                if (!$this->UpdateDeviceInfo()) {
                    $this->ShowDeviceInfoRequestError();
                } else {
                    $this->PopulateDetectedIEEEFormField();
                    $this->ShowDeviceInfoRequestSuccess();
                }
                return;

            case 'RequestDeviceInterview':
                $this->UpdateFormField('DeviceInterviewWarning', 'visible', true);
                return;

            case 'ConfirmDeviceInterview':
                $this->UpdateFormField('DeviceInterviewWarning', 'visible', false);
                $this->RunDeviceMaintenanceRequest(
                    'InterviewDevice',
                    'Device interview successful',
                    'The device interview completed successfully. Refresh the device information afterwards to import changed exposes into Symcon.',
                    'Device interview failed'
                );
                return;

            case 'RequestDeviceConfigure':
                $this->UpdateFormField('DeviceConfigureWarning', 'visible', true);
                return;

            case 'ConfirmDeviceConfigure':
                $this->UpdateFormField('DeviceConfigureWarning', 'visible', false);
                $this->RunDeviceMaintenanceRequest(
                    'ConfigureDevice',
                    'Device configuration successful',
                    'The device configuration completed successfully. Refresh endpoint data afterwards to update binding and reporting information.',
                    'Device configuration failed'
                );
                return;

            case 'RequestDeviceRemoval':
                $this->UpdateFormField('DeviceRemovalWarning', 'visible', true);
                return;

            case 'ConfirmDeviceRemoval':
                $this->UpdateFormField('DeviceRemovalWarning', 'visible', false);
                $this->RunDeviceRemovalRequest(
                    false,
                    false,
                    'Device removed',
                    'Zigbee2MQTT removed the device from the Zigbee network. The Symcon instance and its variables remain unchanged.'
                );
                return;

            case 'RequestForceDeviceRemoval':
                $this->UpdateFormField('ForceDeviceRemovalWarning', 'visible', true);
                return;

            case 'ConfirmForceDeviceRemoval':
                $this->UpdateFormField('ForceDeviceRemovalWarning', 'visible', false);
                $this->RunDeviceRemovalRequest(
                    true,
                    false,
                    'Device force removed',
                    'Zigbee2MQTT removed the device from its database. Factory-reset the device before pairing it again. The Symcon instance and its variables remain unchanged.'
                );
                return;

            case 'RequestBlockDeviceRemoval':
                $this->UpdateFormField('BlockDeviceRemovalWarning', 'visible', true);
                return;

            case 'ConfirmBlockDeviceRemoval':
                $this->UpdateFormField('BlockDeviceRemovalWarning', 'visible', false);
                $this->RunDeviceRemovalRequest(
                    false,
                    true,
                    'Device removed and blocked',
                    'Zigbee2MQTT removed the device and added it to the blocklist. The Symcon instance and its variables remain unchanged.'
                );
                return;

            case 'ShowIeeeEditWarning':
                $this->UpdateFormField('IeeeWarning', 'visible', true);
                return;

            case 'EnableIeeeEdit':
                $this->UpdateFormField('IEEE', 'enabled', true);
                return;

            case 'SelectDeviceOption':
                $this->SelectDeviceOptionFromForm($value);
                return;

            case 'ApplyDeviceOption':
                $this->ApplyDeviceOptionFromForm($value);
                return;

            case 'AddDeviceOptionAttribute':
                $this->AddDeviceOptionAttributeFromForm($value);
                return;

            case 'RemoveDeviceOptionAttribute':
                $this->RemoveDeviceOptionAttributeFromForm($value);
                return;

            case 'ApplyBinding':
                $bound = $this->ApplyBindingFromForm($value);
                $this->UpdateFormField(
                    'BindingResult',
                    'caption',
                    $this->Translate($bound ? 'Zigbee2MQTT confirmed the binding.' : 'Zigbee2MQTT did not confirm the binding.')
                );
                $this->UpdateFormField('BindingResult', 'visible', true);
                return;

            case 'ApplyUnbinding':
                $this->ApplyUnbindingFromForm($value);
                return;

            case 'ClearBindings':
                $this->ClearBindingsFromForm();
                return;

            case 'RefreshBindingReportingInfo':
                $this->RefreshBindingReportingInfoFromForm();
                return;

            case 'SelectExistingBinding':
                $this->SelectExistingBindingFromForm($value);
                return;

            case 'SelectExistingReporting':
                $this->SelectExistingReportingFromForm($value);
                return;

            case 'UpdateBindingClusters':
            case 'UpdateBindingWizardClusters':
                $this->UpdateBindingClustersFromForm($value, $ident === 'UpdateBindingWizardClusters');
                return;

            case 'UpdateReportingSelection':
                $this->UpdateReportingSelectionFromForm($value);
                return;

            case 'ConfigureReporting':
                $configured = $this->ConfigureReportingFromForm($value);
                $this->UpdateFormField(
                    'ReportingConfigurationResult',
                    'caption',
                    $this->Translate($configured ? 'Zigbee2MQTT confirmed the reporting configuration.' : 'Zigbee2MQTT did not confirm the reporting configuration.')
                );
                $this->UpdateFormField('ReportingConfigurationResult', 'visible', true);
                return;

            case 'ReadReporting':
                $this->ReadReportingFromForm($value);
                return;

            default:
                parent::RequestAction($ident, $value);
        }
    }

    /**
     * Liefert die Anzeigetexte der letzten Beobachtung im aktuellen MQTT-Netz.
     */
    public function GetObservedRouteView(): array
    {
        $route = $this->ReadAttributeArray(self::DEVICE_OBSERVED_ROUTE);
        if ($this->ReadPropertyString(self::MQTT_TOPIC) === '' || ($route['scope'] ?? null) !== $this->GetObservedRouteScope()) {
            return [];
        }

        return $this->FormatObservedRouteView($route);
    }

    /**
     * UpdateDeviceInfo
     *
     * Exposes von der Erweiterung in Z2M anfordern und verarbeiten.
     *
     * @return bool
     */
    protected function UpdateDeviceInfo(): bool
    {
        $this->detectedIEEEFormValue = '';

        // Aufruf der Methode aus der ModulBase-Klasse
        $Result = $this->LoadDeviceInfo();
        if (!$Result) {
            return false;
        }
        if (!count($Result)) {
            trigger_error($this->Translate('Device not found. Check topic.'), E_USER_NOTICE);
            return false;

        }
        if (!isset($Result['ieeeAddr'])) {
            $this->LogMessage($this->Translate('IEEE-Address missing.'), KL_WARNING);
            $Result['ieeeAddr'] = '';
        }

        $currentIEEE = $this->ReadPropertyString('IEEE');
        $detectedIEEE = trim((string) $Result['ieeeAddr']);
        if ($currentIEEE === '' && $detectedIEEE !== '') {
            $this->detectedIEEEFormValue = $detectedIEEE;
        }

        // Model und Icon verarbeiten
        if (isset($Result['model']) && $Result['model'] !== 'Unknown Model') {
            $Model = $Result['model'];
            if ($this->ReadAttributeString('Model') !== $Model) {
                $this->UpdateDeviceIcon($Model);
            }
        }

        // Exposes enthalten?
        if (!isset($Result['exposes'])) {
            return false;
        }

        $deviceOptions = \is_array($Result['options'] ?? null) ? $Result['options'] : [];
        $definitionOptions = \is_array($Result['definition_options'] ?? null) ? $Result['definition_options'] : [];
        $filteredAttributes = \is_array($deviceOptions['filtered_attributes'] ?? null)
            ? $deviceOptions['filtered_attributes']
            : ($Result['filtered_attributes'] ?? []);

        $this->WriteAttributeArray(parent::ATTRIBUTE_DEVICE_OPTIONS, $deviceOptions);
        $this->WriteAttributeArray(parent::ATTRIBUTE_DEVICE_OPTION_DEFINITIONS, $definitionOptions);
        $endpoints = \is_array($Result['endpoints'] ?? null) ? $Result['endpoints'] : [];
        $this->WriteAttributeArray(parent::ATTRIBUTE_DEVICE_ENDPOINTS, $this->MergeBridgeCachedDeviceEndpoints($endpoints));
        $this->WriteAttributeArray(parent::ATTRIBUTE_FILTERED, \is_array($filteredAttributes) ? $filteredAttributes : []);
        $this->WriteAttributeBoolean(parent::ATTRIBUTE_DEVICE_SUPPORTS_OTA, (bool) ($Result['supports_ota'] ?? false));

        $this->WriteAttributeArray(parent::ATTRIBUTE_EXPOSES, $Result['exposes']);
        $this->mapExposesToVariables($Result['exposes']);
        return true;
    }

    /**
     * Liefert das Geraetebild nur fuer das geoeffnete Konfigurationsformular.
     *
     * Das Bild wird bewusst nicht dauerhaft als Base64-Attribut gespeichert, da
     * Instanzattribute Bestandteil von IPS_GetSnapshot() sind.
     */
    protected function ReadDeviceIconForForm(): string
    {
        $model = $this->ReadAttributeString('Model');
        if ($model !== '') {
            $imageRaw = $this->ReadDeviceIconCache($model);
            if ($imageRaw !== null) {
                return 'data:image/png;base64,' . base64_encode($imageRaw);
            }
        }

        // Kompatibilitaetsfallback bis ein bestehendes Base64-Attribut migriert wurde.
        $imageRaw = $this->ReadPersistedDeviceIcon();
        return $imageRaw !== null
            ? 'data:image/png;base64,' . base64_encode($imageRaw)
            : '';
    }

    protected function GetAdditionalMqttResponseTopics(): array
    {
        $topic = $this->GetObservedRouteTopic();
        return $topic === '' ? [self::ROUTING_TABLE_RESPONSE_TOPIC] : [self::ROUTING_TABLE_RESPONSE_TOPIC, $topic];
    }

    protected function HandleAdditionalMqttResponse(string $topic, array $payload): void
    {
        if ($topic === self::ROUTING_TABLE_RESPONSE_TOPIC) {
            $this->ReceiveRoutingTableResponse($payload);
            return;
        }
        $this->ReceiveObservedRoute($topic, $payload);
    }

    protected function RoutingTableRefreshStateChanged(bool $running): void
    {
        $this->UpdateFormField('RefreshDeviceRoutingTable', 'enabled', !$running);
        $this->UpdateFormField('DeviceRoutingTableProgress', 'visible', $running);
    }

    protected function RoutingTableRefreshCompleted(string $context, array $routes): void
    {
        if ($context !== strtolower(trim($this->ReadPropertyString('IEEE')))) {
            return;
        }
        $snapshot = [
            'ieee'         => $context,
            'base_topic'   => $this->ReadPropertyString(self::MQTT_BASE_TOPIC),
            'refreshed_at' => time(),
            'routes'       => $routes
        ];
        $this->WriteAttributeArray(self::DEVICE_ROUTING_TABLE, $snapshot);
        [$rows, $caption] = $this->BuildDeviceRoutingTableView($snapshot);
        $this->UpdateFormField('DeviceRoutingTableList', 'values', json_encode($rows));
        $this->UpdateFormField('DeviceRoutingTableStatus', 'caption', $caption);
    }

    protected function RoutingTableRefreshFailed(string $message): void
    {
        $this->ShowDeviceMaintenanceMessage('Route refresh failed', $message);
    }

    /**
     * Baut das Geraeteformular nach Aktivierung des Reload-Schutzes auf.
     */
    private function GetConfigurationFormWithAvailableInstanceInterface(): string
    {
        $form = $this->BuildDeviceConfigurationForm(json_decode(file_get_contents(__DIR__ . '/form.json'), true));
        $this->PopulateObservedRouteForm($form);
        $target = $this->GetDeviceRoutingTableTarget();
        if ($target !== null) {
            $this->SetDeviceFormField($form, 'DeviceRoutingTablePanel', 'visible', true);
            $refreshing = $this->HasRoutingTableRefresh();
            $this->SetDeviceFormField($form, 'RefreshDeviceRoutingTable', 'enabled', !$refreshing);
            $this->SetDeviceFormField($form, 'DeviceRoutingTableProgress', 'visible', $refreshing);
            $snapshot = $this->ReadAttributeArray(self::DEVICE_ROUTING_TABLE);
            if (($snapshot['ieee'] ?? '') === $target['ieee']
                && ($snapshot['base_topic'] ?? '') === $this->ReadPropertyString(self::MQTT_BASE_TOPIC)) {
                [$rows, $caption] = $this->BuildDeviceRoutingTableView($snapshot);
                $this->SetDeviceFormField($form, 'DeviceRoutingTableList', 'values', $rows);
                $this->SetDeviceFormField($form, 'DeviceRoutingTableStatus', 'caption', $caption);
            }
        }

        return json_encode($this->PrepareLocalVariableMaintenanceForm($form));
    }

    private function GetObservedRouteTopic(): string
    {
        $ieee = strtolower(trim($this->ReadPropertyString('IEEE')));
        return preg_match('/^0x(?!0{16}$)[a-f0-9]{16}$/D', $ieee) ? self::OBSERVED_ROUTE_TOPIC . $ieee : '';
    }

    private function GetObservedRouteScope(): array
    {
        return [
            'topic'       => $this->ReadPropertyString(self::MQTT_BASE_TOPIC) . $this->GetObservedRouteTopic(),
            'splitter_id' => IPS_InstanceExists($this->InstanceID) ? (int) IPS_GetInstance($this->InstanceID)['ConnectionID'] : 0
        ];
    }

    private function PopulateObservedRouteForm(array &$form): void
    {
        if ($this->GetObservedRouteTopic() === '' || $this->ReadPropertyString(self::MQTT_BASE_TOPIC) === ''
            || $this->ReadPropertyString(self::MQTT_TOPIC) === '') {
            return;
        }
        $this->SetDeviceFormField($form, 'DeviceObservedRoutePanel', 'visible', true);
        $view = $this->GetObservedRouteView();
        if ($view !== []) {
            $this->SetDeviceFormField($form, 'DeviceObservedRoute', 'caption', $view['path']);
            $this->SetDeviceFormField($form, 'DeviceObservedRouteTime', 'caption', $view['time']);
            $this->SetDeviceFormField($form, 'DeviceObservedRouteTime', 'visible', true);
        }
    }

    private function ReceiveObservedRoute(string $topic, array $payload): void
    {
        $ieee = $payload['ieee_address'] ?? null;
        $observedAt = $payload['observed_at'] ?? null;
        $hops = $payload['hops'] ?? null;
        if (!\is_string($ieee) || $topic !== self::OBSERVED_ROUTE_TOPIC . $ieee || $this->GetObservedRouteTopic() !== $topic
            || !\is_int($observedAt) || $observedAt < 1000 || !\is_array($hops) || !array_is_list($hops) || \count($hops) > 32) {
            return;
        }
        $names = [];
        foreach ($hops as $hop) {
            $address = $hop['network_address'] ?? null;
            $name = $hop['name'] ?? null;
            if (!\is_int($address) || $address <= 0 || $address > 0xFFF7 || isset($names[$address]) || !\is_string($name)) {
                return;
            }
            $names[$address] = trim($name) !== '' ? trim($name) : self::FormatNetworkAddress($address);
        }

        $lock = 'Zigbee2MQTT.DeviceObservedRoute.' . $this->InstanceID;
        if (!IPS_SemaphoreEnter($lock, 1000)) {
            return;
        }
        try {
            $scope = $this->GetObservedRouteScope();
            $previous = $this->ReadAttributeArray(self::DEVICE_OBSERVED_ROUTE);
            if (($previous['scope'] ?? null) === $scope && $previous['observed_at'] >= $observedAt) {
                return;
            }
            $route = ['scope' => $scope, 'observed_at' => $observedAt, 'hops' => array_values($names)];
            $this->WriteAttributeArray(self::DEVICE_OBSERVED_ROUTE, $route);
            $view = $this->FormatObservedRouteView($route);
            $this->UpdateFormField('DeviceObservedRoute', 'caption', $view['path']);
            $this->UpdateFormField('DeviceObservedRouteTime', 'caption', $view['time']);
            $this->UpdateFormField('DeviceObservedRouteTime', 'visible', true);
        } finally {
            IPS_SemaphoreLeave($lock);
        }
    }

    private function FormatObservedRouteView(array $route): array
    {
        return [
            'path' => implode(' → ', [$this->ReadPropertyString(self::MQTT_TOPIC), ...$route['hops'], $this->Translate('Coordinator')]),
            'time' => \sprintf($this->Translate('Observed on %s'), date('d.m.Y H:i:s', intdiv($route['observed_at'], 1000)))
        ];
    }

    private function GetDeviceRoutingTableTarget(): ?array
    {
        $ieee = strtolower(trim($this->ReadPropertyString('IEEE')));
        if ($ieee === '' || $this->ReadPropertyString(self::MQTT_TOPIC) === '') {
            return null;
        }
        foreach ($this->ReadBridgeCachedNetworkDevices() as $device) {
            if (\is_array($device) && strtolower((string) ($device['ieee_address'] ?? $device['ieeeAddr'] ?? '')) === $ieee) {
                return self::RoutingTableTarget($device);
            }
        }
        return null;
    }

    private function RefreshDeviceRoutingTable(): void
    {
        if ($this->HasRoutingTableRefresh()) {
            return;
        }
        $target = $this->GetDeviceRoutingTableTarget();
        if ($target === null) {
            $this->RoutingTableRefreshFailed('The matching Bridge has no routing-capable device with a known network address.');
            return;
        }
        $this->StartRoutingTableRefresh($target, $target['ieee']);
    }

    private function BuildDeviceRoutingTableView(array $snapshot): array
    {
        $rows = array_map(fn (array $route): array => [
            'destination' => self::FormatNetworkAddress($route['destinationAddress']),
            'next_hop'    => self::FormatNetworkAddress($route['nextHopAddress']),
            'status'      => $this->FormatRouteStatus($route['status'])
        ], $snapshot['routes']);
        return [
            $rows,
            \sprintf($this->Translate('Routing table updated %s. Entries: %d.'), date('d.m.Y H:i:s', $snapshot['refreshed_at']), \count($rows))
        ];
    }

    /**
     * Zeigt eine lesbare Meldung, wenn die Symcon-Extension nicht antwortet.
     */
    private function ShowDeviceInfoRequestError(): void
    {
        $this->UpdateFormField('DeviceInfoRequestError', 'visible', true);
    }

    /**
     * Zeigt eine Erfolgsmeldung nach geladenen Geraeteinformationen.
     */
    private function ShowDeviceInfoRequestSuccess(): void
    {
        $this->UpdateFormField('DeviceInfoRequestSuccess', 'visible', true);
    }

    /**
     * Traegt eine erkannte IEEE-Adresse nur als noch nicht gespeicherten Formularwert ein.
     */
    private function PopulateDetectedIEEEFormField(): void
    {
        if ($this->detectedIEEEFormValue === '') {
            return;
        }

        $this->UpdateFormField('IEEE', 'value', $this->detectedIEEEFormValue);
        $this->detectedIEEEFormValue = '';
    }

    /**
     * Fuehrt eine bestaetigte Zigbee2MQTT-Geraetewartung aus und zeigt das Ergebnis lesbar an.
     */
    private function RunDeviceMaintenanceRequest(
        string $bridgeFunction,
        string $successTitle,
        string $successMessage,
        string $failureTitle
    ): bool {
        $deviceID = trim($this->ReadPropertyString(parent::MQTT_TOPIC));
        if ($deviceID === '') {
            $this->ShowDeviceMaintenanceMessage(
                $failureTitle,
                'The device cannot be maintained because no MQTT topic is configured.'
            );
            return false;
        }

        set_error_handler(static function (): bool
        {
            return true;
        }, E_USER_NOTICE);
        try {
            $success = $this->CallMatchingBridgeFunction($bridgeFunction, [$deviceID]) === true;
        } finally {
            restore_error_handler();
        }
        if (!$success) {
            $this->ShowDeviceMaintenanceMessage(
                $failureTitle,
                'Zigbee2MQTT did not complete the request. Make sure the device is online and wake battery devices before trying again.'
            );
            return false;
        }

        $this->ShowDeviceMaintenanceMessage($successTitle, $successMessage);
        return true;
    }

    /**
     * Fuehrt eine bestaetigte Zigbee2MQTT-Geraeteentfernung aus.
     */
    private function RunDeviceRemovalRequest(bool $force, bool $block, string $successTitle, string $successMessage): bool
    {
        $deviceID = trim($this->ReadPropertyString(parent::MQTT_TOPIC));
        if ($deviceID === '') {
            $this->ShowDeviceMaintenanceMessage(
                'Device removal failed',
                'The device cannot be removed because no MQTT topic is configured.'
            );
            return false;
        }

        set_error_handler(static function (): bool
        {
            return true;
        }, E_USER_NOTICE);
        try {
            $success = $this->CallMatchingBridgeFunction('RemoveDevice', [$deviceID, $force, $block]) === true;
        } finally {
            restore_error_handler();
        }
        if (!$success) {
            $this->ShowDeviceMaintenanceMessage(
                'Device removal failed',
                'Zigbee2MQTT did not remove the device. Make sure Zigbee2MQTT is online and wake battery devices before a normal removal.'
            );
            return false;
        }

        $this->ShowDeviceMaintenanceMessage($successTitle, $successMessage);
        return true;
    }

    /**
     * Zeigt das Ergebnis einer Geraetewartungsaktion.
     */
    private function ShowDeviceMaintenanceMessage(string $title, string $message): void
    {
        $this->UpdateFormField('DeviceMaintenanceMessageTitle', 'caption', $this->Translate($title));
        $this->UpdateFormField('DeviceMaintenanceMessageText', 'caption', $this->Translate($message));
        $this->UpdateFormField('DeviceMaintenanceMessage', 'visible', true);
    }

    /**
     * Liefert die erste passende Kachel nach der gewuenschten Prioritaet.
     */
    private function GetVisualizationTileDefinition(): ?array
    {
        if ($this->ShouldForceSensorTile()) {
            return [
                'template' => 'sensor_tile.html',
                'data'     => fn (): array => $this->BuildSensorTileData()
            ];
        }
        if ($this->ShouldUseHeatingTile()) {
            return [
                'template' => 'heating_tile.html',
                'data'     => fn (): array => $this->BuildHeatingTileData()
            ];
        }
        if ($this->ShouldUseMeteredSwitchTile()) {
            return [
                'template' => 'metered_switch_tile.html',
                'data'     => fn (): array => $this->BuildMeteredSwitchTileData()
            ];
        }
        if ($this->ShouldUseWindowHandleTile()) {
            return [
                'template' => 'window_handle_tile.html',
                'data'     => fn (): array => $this->BuildWindowHandleTileData()
            ];
        }
        if ($this->ShouldUseSecurityTile()) {
            return [
                'template' => 'security_tile.html',
                'data'     => fn (): array => $this->BuildSecurityTileData()
            ];
        }
        if ($this->ShouldUseActionTile()) {
            return [
                'template' => 'action_tile.html',
                'data'     => fn (): array => $this->BuildActionTileData()
            ];
        }
        if ($this->ShouldUseSensorTile()) {
            return [
                'template' => 'sensor_tile.html',
                'data'     => fn (): array => $this->BuildSensorTileData()
            ];
        }

        return null;
    }

    /**
     * Laedt und speichert das Geraete-Icon aus der Zigbee2MQTT-Bildsammlung.
     *
     * @param string $Model Zigbee2MQTT-Modellkennung des Geraets.
     */
    private function UpdateDeviceIcon(string $Model): void
    {
        if ($this->HasCachedDeviceIcon($Model)) {
            $this->WriteAttributeString('Icon', '');
            $this->WriteAttributeString('Model', $Model);
            return;
        }

        // Leerzeichen durch Bindestriche für URL ersetzen
        $ModelUrl = str_replace([' ', '/'], '-', $Model);

        $Url = 'https://raw.githubusercontent.com/Koenkk/zigbee2mqtt.io/master/public/images/devices/' . $ModelUrl . '.png';
        $this->SendDebug('loadImage', $Url, 0);
        $ImageRaw = $this->DownloadDeviceIcon($Url);
        if ($ImageRaw !== null && $this->WriteDeviceIconCache($Model, $ImageRaw)) {
            $this->WriteAttributeString('Icon', '');
            $this->WriteAttributeString('Model', $Model);
        } else {
            $this->LogMessage($this->Translate('Error downloading icon from URL: ') . $Url, KL_WARNING);
        }
    }

    /**
     * Migriert das bis Version 6.0 verwendete Base64-Attribut in den Dateicache.
     */
    private function MigratePersistedDeviceIconToCache(): void
    {
        $model = $this->ReadAttributeString('Model');
        $imageRaw = $this->ReadPersistedDeviceIcon();
        if ($model === '' || $imageRaw === null) {
            return;
        }

        if ($this->WriteDeviceIconCache($model, $imageRaw)) {
            $this->WriteAttributeString('Icon', '');
        }
    }

    /**
     * Prueft, ob fuer das Modell bereits ein gemeinsam nutzbares Bild vorliegt.
     */
    private function HasCachedDeviceIcon(string $model): bool
    {
        return $this->ReadDeviceIconCache($model) !== null;
    }

    /**
     * Laedt ein Geraetebild mit begrenzter Laufzeit und Groesse.
     */
    private function DownloadDeviceIcon(string $url): ?string
    {
        $context = stream_context_create([
            'http' => [
                'follow_location' => 0,
                'timeout'         => self::ICON_DOWNLOAD_TIMEOUT_SECONDS,
                'user_agent'      => 'IP-Symcon Zigbee2MQTT'
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true
            ]
        ]);
        $imageRaw = @file_get_contents(
            $url,
            false,
            $context,
            0,
            self::ICON_DOWNLOAD_MAX_BYTES + 1
        );
        if (!\is_string($imageRaw) || !$this->IsValidDeviceIcon($imageRaw)) {
            return null;
        }

        return $imageRaw;
    }

    /**
     * Liest nur valide PNG-Dateien aus dem gemeinsam genutzten Cache.
     */
    private function ReadDeviceIconCache(string $model): ?string
    {
        $cacheFile = $this->GetDeviceIconCacheFile($model);
        if (!is_file($cacheFile)) {
            return null;
        }

        $imageRaw = @file_get_contents($cacheFile, false, null, 0, self::ICON_DOWNLOAD_MAX_BYTES + 1);
        if (!\is_string($imageRaw) || !$this->IsValidDeviceIcon($imageRaw)) {
            return null;
        }

        return $imageRaw;
    }

    /**
     * Liest ein historisch im Attribut gespeichertes Geraetebild nur als valides PNG.
     */
    private function ReadPersistedDeviceIcon(): ?string
    {
        $icon = $this->ReadAttributeString('Icon');
        if (preg_match('#^data:image/png;base64,(.+)$#s', $icon, $matches) !== 1) {
            return null;
        }

        $imageRaw = base64_decode($matches[1], true);
        if (!\is_string($imageRaw) || !$this->IsValidDeviceIcon($imageRaw)) {
            return null;
        }

        return $imageRaw;
    }

    /**
     * Speichert ein Geraetebild modellbezogen im Symcon-Benutzerverzeichnis.
     */
    private function WriteDeviceIconCache(string $model, string $imageRaw): bool
    {
        if (!$this->IsValidDeviceIcon($imageRaw)) {
            return false;
        }

        $directory = $this->GetDeviceIconCacheDirectory();
        if (!is_dir($directory) && !@mkdir($directory, 0777, true)) {
            return false;
        }

        return @file_put_contents($this->GetDeviceIconCacheFile($model), $imageRaw, LOCK_EX) !== false;
    }

    /**
     * Akzeptiert ausschliesslich kleine, technisch lesbare PNG-Geraetebilder.
     */
    private function IsValidDeviceIcon(string $imageRaw): bool
    {
        $length = \strlen($imageRaw);
        if ($length === 0 || $length > self::ICON_DOWNLOAD_MAX_BYTES
            || !str_starts_with($imageRaw, "\x89PNG\r\n\x1a\n")) {
            return false;
        }

        $imageInfo = @getimagesizefromstring($imageRaw);
        if (!\is_array($imageInfo)
            || ($imageInfo[2] ?? null) !== IMAGETYPE_PNG
            || ($imageInfo['mime'] ?? '') !== 'image/png') {
            return false;
        }

        $width = (int) ($imageInfo[0] ?? 0);
        $height = (int) ($imageInfo[1] ?? 0);
        return $width > 0 && $height > 0
            && $width <= self::ICON_MAX_DIMENSION
            && $height <= self::ICON_MAX_DIMENSION;
    }

    /**
     * Liefert das Verzeichnis fuer gemeinsam genutzte Geraetebilder.
     */
    private function GetDeviceIconCacheDirectory(): string
    {
        return rtrim(IPS_GetKernelDir(), '\\/')
            . DIRECTORY_SEPARATOR . 'user'
            . DIRECTORY_SEPARATOR . 'IPSZigbee2MQTT'
            . DIRECTORY_SEPARATOR . self::ICON_CACHE_DIRECTORY;
    }

    /**
     * Liefert den kollisionsfreien Cachepfad eines Geraetemodells.
     */
    private function GetDeviceIconCacheFile(string $model): string
    {
        return $this->GetDeviceIconCacheDirectory()
            . DIRECTORY_SEPARATOR . hash('sha256', $model) . '.png';
    }
}
