<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/libs/ModulBase.php';

/**
 * Device instance for a single Zigbee2MQTT device.
 */
class Zigbee2MQTTDevice extends \Zigbee2MQTT\ModulBase
{
    private const ICON_CACHE_DIRECTORY = 'icons';

    /** @var mixed $ExtensionTopic Topic für den ReceiveFilter*/
    protected static $ExtensionTopic = 'getDeviceInfo/';

    /** Detected IEEE address prepared only for the currently handled configuration form request. */
    private string $detectedIEEEFormValue = '';

    /**
     * Create
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
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);

        return json_encode($this->PrepareLocalVariableMaintenanceForm($this->BuildDeviceConfigurationForm($form)));
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
        if ($ident == 'UpdateInfo') {
            if (!$this->UpdateDeviceInfo()) {
                $this->ShowDeviceInfoRequestError();
            } else {
                $this->PopulateDetectedIEEEFormField();
                $this->ShowDeviceInfoRequestSuccess();
            }
            return;
        }
        if ($ident == 'RequestDeviceInterview') {
            $this->UpdateFormField('DeviceInterviewWarning', 'visible', true);
            return;
        }
        if ($ident == 'ConfirmDeviceInterview') {
            $this->UpdateFormField('DeviceInterviewWarning', 'visible', false);
            $this->RunDeviceMaintenanceRequest(
                'InterviewDevice',
                'Device interview successful',
                'The device interview completed successfully. Refresh the device information afterwards to import changed exposes into Symcon.',
                'Device interview failed'
            );
            return;
        }
        if ($ident == 'RequestDeviceConfigure') {
            $this->UpdateFormField('DeviceConfigureWarning', 'visible', true);
            return;
        }
        if ($ident == 'ConfirmDeviceConfigure') {
            $this->UpdateFormField('DeviceConfigureWarning', 'visible', false);
            $this->RunDeviceMaintenanceRequest(
                'ConfigureDevice',
                'Device configuration successful',
                'The device configuration completed successfully. Refresh endpoint data afterwards to update binding and reporting information.',
                'Device configuration failed'
            );
            return;
        }
        if ($ident == 'RequestDeviceRemoval') {
            $this->UpdateFormField('DeviceRemovalWarning', 'visible', true);
            return;
        }
        if ($ident == 'ConfirmDeviceRemoval') {
            $this->UpdateFormField('DeviceRemovalWarning', 'visible', false);
            $this->RunDeviceRemovalRequest(
                false,
                false,
                'Device removed',
                'Zigbee2MQTT removed the device from the Zigbee network. The Symcon instance and its variables remain unchanged.'
            );
            return;
        }
        if ($ident == 'RequestForceDeviceRemoval') {
            $this->UpdateFormField('ForceDeviceRemovalWarning', 'visible', true);
            return;
        }
        if ($ident == 'ConfirmForceDeviceRemoval') {
            $this->UpdateFormField('ForceDeviceRemovalWarning', 'visible', false);
            $this->RunDeviceRemovalRequest(
                true,
                false,
                'Device force removed',
                'Zigbee2MQTT removed the device from its database. Factory-reset the device before pairing it again. The Symcon instance and its variables remain unchanged.'
            );
            return;
        }
        if ($ident == 'RequestBlockDeviceRemoval') {
            $this->UpdateFormField('BlockDeviceRemovalWarning', 'visible', true);
            return;
        }
        if ($ident == 'ConfirmBlockDeviceRemoval') {
            $this->UpdateFormField('BlockDeviceRemovalWarning', 'visible', false);
            $this->RunDeviceRemovalRequest(
                false,
                true,
                'Device removed and blocked',
                'Zigbee2MQTT removed the device and added it to the blocklist. The Symcon instance and its variables remain unchanged.'
            );
            return;
        }
        if ($ident == 'RequestApplyRecommendedPresentations') {
            $this->UpdateFormField('RecommendedPresentationsWarning', 'visible', true);
            return;
        }
        if ($ident == 'ConfirmApplyRecommendedPresentations') {
            $this->UpdateFormField('RecommendedPresentationsWarning', 'visible', false);
            $this->ApplyRecommendedVariablePresentations();
            $this->ShowDeviceMaintenanceMessage(
                'Recommended variable presentations applied',
                'Existing custom presentations were replaced with the presentations recommended by this module.'
            );
            return;
        }
        if ($ident == 'ShowIeeeEditWarning') {
            $this->UpdateFormField('IeeeWarning', 'visible', true);
            return;
        }
        if ($ident == 'EnableIeeeEdit') {
            $this->UpdateFormField('IEEE', 'enabled', true);
            return;
        }
        if ($ident == 'SelectDeviceOption') {
            $this->SelectDeviceOptionFromForm($value);
            return;
        }
        if ($ident == 'ApplyDeviceOption') {
            $this->ApplyDeviceOptionFromForm($value);
            return;
        }
        if ($ident == 'AddDeviceOptionAttribute') {
            $this->AddDeviceOptionAttributeFromForm($value);
            return;
        }
        if ($ident == 'RemoveDeviceOptionAttribute') {
            $this->RemoveDeviceOptionAttributeFromForm($value);
            return;
        }
        if ($ident == 'ApplyBinding') {
            $this->ApplyBindingFromForm($value);
            return;
        }
        if ($ident == 'ApplyUnbinding') {
            $this->ApplyUnbindingFromForm($value);
            return;
        }
        if ($ident == 'ClearBindings') {
            $this->ClearBindingsFromForm();
            return;
        }
        if ($ident == 'RefreshBindingReportingInfo') {
            $this->RefreshBindingReportingInfoFromForm();
            return;
        }
        if ($ident == 'UpdateBindingClusters') {
            $this->UpdateBindingClustersFromForm($value);
            return;
        }
        if ($ident == 'UpdateReportingSelection') {
            $this->UpdateReportingSelectionFromForm($value);
            return;
        }
        if ($ident == 'ConfigureReporting') {
            $this->ConfigureReportingFromForm($value);
            return;
        }
        if ($ident == 'ReadReporting') {
            $this->ReadReportingFromForm($value);
            return;
        }
        parent::RequestAction($ident, $value);
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
            $imageRaw = @file_get_contents($this->GetDeviceIconCacheFile($model));
            if ($imageRaw !== false) {
                return 'data:image/png;base64,' . base64_encode($imageRaw);
            }
        }

        // Kompatibilitaetsfallback bis ein bestehendes Base64-Attribut migriert wurde.
        return $this->ReadAttributeString('Icon');
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
        $ImageRaw = @file_get_contents($Url);
        if ($ImageRaw !== false && $this->WriteDeviceIconCache($Model, $ImageRaw)) {
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
        $icon = $this->ReadAttributeString('Icon');
        if ($icon === '') {
            return;
        }

        $model = $this->ReadAttributeString('Model');
        if ($model === ''
            || preg_match('#^data:image/[^;]+;base64,(.+)$#s', $icon, $matches) !== 1
        ) {
            return;
        }

        $imageRaw = base64_decode($matches[1], true);
        if ($imageRaw !== false && $this->WriteDeviceIconCache($model, $imageRaw)) {
            $this->WriteAttributeString('Icon', '');
        }
    }

    /**
     * Prueft, ob fuer das Modell bereits ein gemeinsam nutzbares Bild vorliegt.
     */
    private function HasCachedDeviceIcon(string $model): bool
    {
        return is_file($this->GetDeviceIconCacheFile($model));
    }

    /**
     * Speichert ein Geraetebild modellbezogen im Symcon-Benutzerverzeichnis.
     */
    private function WriteDeviceIconCache(string $model, string $imageRaw): bool
    {
        $directory = $this->GetDeviceIconCacheDirectory();
        if (!is_dir($directory) && !@mkdir($directory, 0777, true)) {
            return false;
        }

        return @file_put_contents($this->GetDeviceIconCacheFile($model), $imageRaw, LOCK_EX) !== false;
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
