<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/libs/ModulBase.php';

/**
 * Group instance for a Zigbee2MQTT group including members, options and scenes.
 */
class Zigbee2MQTTGroup extends \Zigbee2MQTT\ModulBase
{
    private const GROUP_OPTION_DEFINITIONS = [
        'retain'              => [
            'type'        => 'boolean',
            'default'     => false,
            'description' => 'Retain MQTT messages for this group.'
        ],
        'transition'          => [
            'type'        => 'numeric',
            'default'     => 0,
            'description' => 'Default transition time for group commands in seconds.'
        ],
        'optimistic'          => [
            'type'        => 'boolean',
            'default'     => true,
            'description' => 'Update the group state when one member changes state.'
        ],
        'qos'                 => [
            'type'        => 'enum',
            'default'     => null,
            'description' => 'MQTT quality of service for messages of this group.',
            'values'      => [
                ['caption' => '-', 'value' => 'null', 'actual' => null],
                ['caption' => '0', 'value' => '0', 'actual' => 0],
                ['caption' => '1', 'value' => '1', 'actual' => 1],
                ['caption' => '2', 'value' => '2', 'actual' => 2]
            ]
        ],
        'off_state'           => [
            'type'        => 'enum',
            'default'     => 'all_members_off',
            'description' => 'Controls when OFF or CLOSE is published for a group.',
            'values'      => [
                ['caption' => 'all_members_off', 'value' => 'all_members_off'],
                ['caption' => 'last_member_state', 'value' => 'last_member_state']
            ]
        ],
        'filtered_attributes' => [
            'type'        => 'array',
            'default'     => '[]',
            'description' => 'Attributes not published by Zigbee2MQTT for this group.',
            'editor'      => 'filtered_attributes'
        ],
        'homeassistant'       => [
            'type'        => 'object',
            'default'     => '{}',
            'description' => 'Override Home Assistant discovery properties for this group.'
        ]
    ];

    /** @var mixed $ExtensionTopic Topic für den ReceiveFilter */
    protected static $ExtensionTopic = 'getGroupInfo/';

    /**
     * Create
     *
     * @return void
     */
    public function Create(): void
    {
        // Never delete this line!
        parent::Create();
        $this->RegisterPropertyInteger('GroupId', 0);
        $this->RegisterAttributeInteger('GroupId', 0);
        $this->RegisterAttributeArray(self::ATTRIBUTE_GROUP_OPTIONS, []);
        $this->RegisterAttributeArray(self::ATTRIBUTE_GROUP_MEMBERS, []);
        $this->RegisterAttributeArray(self::ATTRIBUTE_GROUP_SCENES, []);
        $this->RegisterMessage($this->InstanceID, IM_CHANGEATTRIBUTE);
    }

    /**
     * ApplyChanges
     *
     * @return void
     */
    public function ApplyChanges(): void
    {
        $GroupId = $this->ReadPropertyInteger('GroupId');
        $this->SetSummary($GroupId ? 'Group Id: ' . $GroupId : '');
        if ($GroupId == 0) {
            if ($this->GetStatus() == IS_ACTIVE) {
                $this->LogMessage($this->Translate('No group ID configured'), KL_WARNING);
            }
        } else {
            if ($this->ReadAttributeInteger('GroupId') != $GroupId) {
                $this->WriteAttributeInteger('GroupId', $GroupId);
            }
        }
        // Führe parent::ApplyChanges zuerst aus
        parent::ApplyChanges();
        $this->UpdateGroupReceiveDataFilter();
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
                if (($Data[0] == 'GroupId') && ($Data[1] != 0) && ($this->GetStatus() == IS_CREATING)) {
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
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        return json_encode($this->PrepareLocalVariableMaintenanceForm($this->BuildGroupConfigurationForm($Form)));
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
        if ($ident == 'ShowGroupIdEditWarning') {
            $this->UpdateFormField('GroupIdWarning', 'visible', true);
            return;
        }
        if ($ident == 'EnableGroupIdEdit') {
            $this->UpdateFormField('GroupId', 'enabled', true);
            return;
        }
        if ($ident == 'RefreshGroupInfo') {
            $this->RefreshGroupInfoFromForm();
            return;
        }
        if ($ident == 'SelectGroupMember') {
            $this->SelectGroupMemberFromForm($value);
            return;
        }
        if ($ident == 'SelectGroupMemberDevice') {
            $this->SelectGroupMemberFromForm($value);
            return;
        }
        if ($ident == 'AddGroupMember') {
            $this->AddGroupMemberFromForm($value);
            return;
        }
        if ($ident == 'RemoveGroupMember') {
            $this->RemoveGroupMemberFromForm($value);
            return;
        }
        if ($ident == 'RemoveGroupMemberFromAllGroups') {
            $this->RemoveGroupMemberFromAllGroupsFromForm($value);
            return;
        }
        if ($ident == 'SelectGroupOption') {
            $this->SelectGroupOptionFromForm($value);
            return;
        }
        if ($ident == 'ApplyGroupOption') {
            $this->ApplyGroupOptionFromForm($value);
            return;
        }
        if ($ident == 'AddGroupFilteredAttribute') {
            $this->AddGroupFilteredAttributeFromForm($value);
            return;
        }
        if ($ident == 'RemoveGroupFilteredAttribute') {
            $this->RemoveGroupFilteredAttributeFromForm($value);
            return;
        }
        if ($ident == 'SelectGroupScene') {
            $this->SelectGroupSceneFromForm($value);
            return;
        }
        if ($ident == 'StoreGroupScene') {
            $this->StoreGroupSceneFromForm($value);
            return;
        }
        if ($ident == 'AddGroupScene') {
            $this->AddGroupSceneFromForm($value);
            return;
        }
        if ($ident == 'RecallGroupScene') {
            $this->RecallGroupSceneFromForm($value);
            return;
        }
        if ($ident == 'RenameGroupScene') {
            $this->RenameGroupSceneFromForm($value);
            return;
        }
        if ($ident == 'RemoveGroupScene') {
            $this->RemoveGroupSceneFromForm($value);
            return;
        }
        if ($ident == 'RemoveAllGroupScenes') {
            $this->RemoveAllGroupScenesFromForm();
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
        // Aufruf der Methode aus der ModulBase-Klasse
        $Result = $this->LoadDeviceInfo();
        if (!$Result) {
            return false;
        }
        if (($Result['foundGroup'] ?? false) !== true) {
            trigger_error($this->Translate('Group not found. Check topic.'), E_USER_NOTICE);
            return false;
        }
        $this->WriteAttributeArray(self::ATTRIBUTE_GROUP_OPTIONS, \is_array($Result['options'] ?? null) ? $Result['options'] : []);
        $this->WriteAttributeArray(self::ATTRIBUTE_GROUP_MEMBERS, \is_array($Result['members'] ?? null) ? $Result['members'] : []);
        $this->WriteAttributeArray(self::ATTRIBUTE_GROUP_SCENES, \is_array($Result['scenes'] ?? null) ? $Result['scenes'] : []);
        unset(
            $Result['foundGroup'],
            $Result['id'],
            $Result['ID'],
            $Result['friendly_name'],
            $Result['devices'],
            $Result['options'],
            $Result['members'],
            $Result['scenes']
        );
        // Aufruf der Methode aus der ModulBase-Klasse
        $this->WriteAttributeArray(self::ATTRIBUTE_EXPOSES, $Result);
        $this->mapExposesToVariables($Result);
        return true;
    }

    /**
     * Bereitet die statische Gruppen-Konfigurationsform auf.
     */
    private function BuildGroupConfigurationForm(array $form): array
    {
        $configured = $this->ReadPropertyString(self::MQTT_TOPIC) !== '';

        $this->SetGroupFormField($form, 'ShowMissingTranslationsButton', 'visible', count($this->missingTranslations) > 0);

        $memberValues = $this->BuildGroupMemberFormValues();
        $this->SetGroupFormField($form, 'GroupMembersSettings', 'visible', $configured || $memberValues !== []);
        $this->SetGroupFormField($form, 'GroupMemberList', 'values', $memberValues);
        $this->SetGroupFormField($form, 'GroupMemberList', 'rowCount', min(10, max(4, \count($memberValues) + 1)));

        $availableDeviceValues = $this->BuildGroupAvailableDeviceFormValues();
        $this->SetGroupFormField($form, 'GroupAvailableDeviceList', 'values', $availableDeviceValues);
        $this->SetGroupFormField($form, 'GroupAvailableDeviceList', 'rowCount', min(10, max(4, \count($availableDeviceValues) + 1)));

        $optionValues = $this->BuildGroupOptionFormValues();
        $hasGroupOptions = $configured || $optionValues !== [];
        $this->SetGroupFormField($form, 'GroupOptionsSettings', 'visible', $hasGroupOptions);
        $this->SetGroupFormField($form, 'GroupOptionList', 'values', $optionValues);
        $this->SetGroupFormField($form, 'GroupOptionList', 'rowCount', min(8, max(4, \count($optionValues) + 1)));

        $sceneValues = $this->BuildGroupSceneFormValues();
        $this->SetGroupFormField($form, 'GroupScenesSettings', 'visible', $configured || $sceneValues !== []);
        $this->SetGroupFormField($form, 'GroupSceneList', 'values', $sceneValues);
        $this->SetGroupFormField($form, 'GroupSceneList', 'rowCount', min(8, max(4, \count($sceneValues) + 1)));

        return $form;
    }

    /**
     * Baut die Mitgliederliste fuer die Gruppenform.
     */
    private function BuildGroupMemberFormValues(): array
    {
        $values = [];
        foreach ($this->ReadAttributeArray(self::ATTRIBUTE_GROUP_MEMBERS) as $member) {
            if (!\is_array($member)) {
                continue;
            }

            $values[] = [
                'device'   => $this->FormatGroupMemberDevice($member),
                'endpoint' => (string) ($member['endpoint'] ?? ''),
                'action'   => $this->Translate('Edit')
            ];
        }

        return $values;
    }

    /**
     * Baut die Liste verfuegbarer Geraete fuer die Gruppenform.
     */
    private function BuildGroupAvailableDeviceFormValues(): array
    {
        $values = [];
        foreach ($this->BuildGroupMemberDeviceOptions() as $device) {
            $values[] = [
                'device'    => $device['caption'],
                'topic'     => $device['value'],
                'endpoints' => $this->FormatGroupMemberEndpointList($device['endpoints'] ?? []),
                'action'    => $this->Translate('Select')
            ];
        }

        return $values;
    }

    /**
     * Baut die Auswahl verfuegbarer Zigbee2MQTT-Geraete fuer die Gruppenverwaltung.
     */
    private function BuildGroupMemberDeviceOptions(): array
    {
        $devices = [];
        foreach ($this->LoadGroupMemberDevicesFromInstances() as $device) {
            $devices[$device['value']] = $device;
        }

        foreach ($this->LoadGroupMemberDevicesFromExtension() as $device) {
            $devices[$device['value']] = $device;
        }

        foreach ($this->ReadAttributeArray(self::ATTRIBUTE_GROUP_MEMBERS) as $member) {
            if (!\is_array($member)) {
                continue;
            }

            $device = $this->FormatGroupMemberDevice($member);
            if ($device === '' || isset($devices[$device])) {
                continue;
            }

            $devices[$device] = [
                'caption'   => $device,
                'value'     => $device,
                'endpoints' => $this->BuildGroupMemberEndpointValues([(string) ($member['endpoint'] ?? '')])
            ];
        }

        $options = array_values($devices);
        usort($options, static fn (array $left, array $right): int => strnatcasecmp($left['caption'], $right['caption']));
        return $options;
    }

    /**
     * Liest bekannte Device-Instanzen mit gleichem BaseTopic und MQTT-Splitter als lokale Fallback-Liste.
     */
    private function LoadGroupMemberDevicesFromInstances(): array
    {
        $baseTopic = $this->ReadPropertyString(self::MQTT_BASE_TOPIC);
        if ($baseTopic === '') {
            return [];
        }

        $connectionID = IPS_InstanceExists($this->InstanceID)
            ? (int) IPS_GetInstance($this->InstanceID)['ConnectionID']
            : 0;
        $devices = [];
        foreach (IPS_GetInstanceListByModuleID(self::GUID_MODULE_DEVICE) as $instanceID) {
            if (@IPS_GetProperty($instanceID, self::MQTT_BASE_TOPIC) !== $baseTopic) {
                continue;
            }
            if ((int) IPS_GetInstance($instanceID)['ConnectionID'] !== $connectionID) {
                continue;
            }

            $topic = trim((string) @IPS_GetProperty($instanceID, self::MQTT_TOPIC));
            if ($topic === '') {
                continue;
            }

            $devices[] = [
                'caption'   => $this->BuildGroupMemberDeviceCaption($topic, @IPS_GetName($instanceID)),
                'value'     => $topic,
                'endpoints' => []
            ];
        }

        return $devices;
    }

    /**
     * Fragt Zigbee2MQTT nach bekannten Devices, damit auch noch nicht angelegte Instanzen auswählbar sind.
     */
    private function LoadGroupMemberDevicesFromExtension(): array
    {
        if (!$this->HasActiveParent()) {
            return [];
        }

        $result = @$this->SendData(self::SYMCON_EXTENSION_LIST_REQUEST . 'getDevices', [], 2500);
        if (!\is_array($result) || !\is_array($result['list'] ?? null)) {
            return [];
        }

        $devices = [];
        foreach ($result['list'] as $device) {
            if (!\is_array($device)) {
                continue;
            }
            if (($device['type'] ?? '') === 'Coordinator') {
                continue;
            }

            $topic = trim((string) ($device['friendly_name'] ?? ''));
            if ($topic === '') {
                continue;
            }

            $devices[] = [
                'caption'   => $this->BuildGroupMemberDeviceCaption($topic, (string) ($device['model'] ?? '')),
                'value'     => $topic,
                'endpoints' => $this->BuildGroupMemberEndpointValuesFromDefinition($device['endpoints'] ?? [])
            ];
        }

        return $devices;
    }

    /**
     * Ermittelt die auswählbaren Endpoint-Werte aus der Zigbee2MQTT-Endpoint-Struktur.
     */
    private function BuildGroupMemberEndpointValuesFromDefinition(mixed $endpoints): array
    {
        if (!\is_array($endpoints)) {
            return [];
        }

        $values = [];
        foreach ($endpoints as $endpointID => $endpoint) {
            if (\is_array($endpoint)) {
                $values[] = (string) ($endpoint['id'] ?? $endpoint['ID'] ?? $endpointID);
                continue;
            }

            $values[] = \is_int($endpointID) ? (string) $endpoint : (string) $endpointID;
        }

        return $this->BuildGroupMemberEndpointValues($values);
    }

    /**
     * Normalisiert Endpoint-Werte fuer Auswahlfelder und Listen.
     */
    private function BuildGroupMemberEndpointValues(array $endpoints): array
    {
        $values = [];
        foreach ($endpoints as $endpoint) {
            $endpoint = trim((string) $endpoint);
            if ($endpoint === '') {
                continue;
            }

            $values[$endpoint] = $endpoint;
        }

        $values = array_values($values);
        usort($values, static fn (string $left, string $right): int => strnatcasecmp($left, $right));
        return $values;
    }

    /**
     * Formatiert Endpoint-Werte fuer die Geraeteliste.
     */
    private function FormatGroupMemberEndpointList(array $endpoints): string
    {
        return implode(', ', $this->BuildGroupMemberEndpointValues($endpoints));
    }

    /**
     * Baut die Endpoint-Auswahl fuer ein ausgewaehltes Gruppenmitglied.
     */
    private function BuildGroupMemberEndpointOptions(string $device, string $selectedEndpoint = ''): array
    {
        $endpoints = [];
        foreach ($this->BuildGroupMemberDeviceOptions() as $candidate) {
            if (($candidate['value'] ?? '') !== $device) {
                continue;
            }

            $endpoints = \is_array($candidate['endpoints'] ?? null) ? $candidate['endpoints'] : [];
            break;
        }

        if ($selectedEndpoint !== '') {
            $endpoints[] = $selectedEndpoint;
        }

        $options = [];
        foreach ($this->BuildGroupMemberEndpointValues($endpoints) as $endpoint) {
            $options[] = [
                'caption' => $endpoint,
                'value'   => $endpoint
            ];
        }

        return $options;
    }

    /**
     * Erzeugt eine lesbare Beschriftung fuer die Geraeteauswahl.
     */
    private function BuildGroupMemberDeviceCaption(string $topic, string $suffix = ''): string
    {
        $nameParts = explode('/', $topic);
        $name = (string) end($nameParts);
        $suffix = trim($suffix);

        if ($suffix === '' || $suffix === $name || $suffix === $topic) {
            return $topic;
        }

        return $topic . ' (' . $suffix . ')';
    }

    /**
     * Baut die Optionsliste fuer die Gruppenform.
     */
    private function BuildGroupOptionFormValues(): array
    {
        $values = [];
        $currentOptions = $this->ReadAttributeArray(self::ATTRIBUTE_GROUP_OPTIONS);

        foreach (self::GROUP_OPTION_DEFINITIONS as $name => $definition) {
            $hasCurrentValue = \array_key_exists($name, $currentOptions);
            $values[] = [
                'name'          => $name,
                'type'          => $this->Translate($this->FormatGroupOptionType((string) $definition['type'])),
                'current'       => $hasCurrentValue ? $this->FormatGroupFormValue($currentOptions[$name]) : '',
                'default_value' => $this->FormatGroupFormValue($definition['default'] ?? ''),
                'description'   => $this->Translate($definition['description']),
                'action'        => $this->Translate('Edit')
            ];

            unset($currentOptions[$name]);
        }

        foreach ($currentOptions as $name => $currentValue) {
            $values[] = [
                'name'          => (string) $name,
                'type'          => $this->Translate('Mixed'),
                'current'       => $this->FormatGroupFormValue($currentValue),
                'default_value' => '',
                'description'   => $this->Translate('Option returned by Zigbee2MQTT.'),
                'action'        => $this->Translate('Edit')
            ];
        }

        usort($values, static fn (array $left, array $right): int => strcmp($left['name'], $right['name']));
        return $values;
    }

    /**
     * Baut die Szenenliste fuer die Gruppenform.
     */
    private function BuildGroupSceneFormValues(): array
    {
        $values = [];
        foreach ($this->ReadAttributeArray(self::ATTRIBUTE_GROUP_SCENES) as $scene) {
            if (!\is_array($scene)) {
                continue;
            }

            $values[] = [
                'id'     => (string) ($scene['id'] ?? $scene['ID'] ?? ''),
                'name'   => (string) ($scene['name'] ?? ''),
                'action' => $this->Translate('Edit')
            ];
        }

        return $values;
    }

    /**
     * Laedt extern geaenderte Gruppendaten neu und aktualisiert die geoeffnete Form.
     */
    private function RefreshGroupInfoFromForm(): bool
    {
        if (!$this->UpdateDeviceInfo()) {
            return false;
        }

        $memberValues = $this->BuildGroupMemberFormValues();
        $this->UpdateFormField('GroupMemberList', 'values', json_encode($memberValues));
        $this->UpdateFormField('GroupMemberList', 'rowCount', min(10, max(4, \count($memberValues) + 1)));

        $availableDeviceValues = $this->BuildGroupAvailableDeviceFormValues();
        $this->UpdateFormField('GroupAvailableDeviceList', 'values', json_encode($availableDeviceValues));
        $this->UpdateFormField('GroupAvailableDeviceList', 'rowCount', min(10, max(4, \count($availableDeviceValues) + 1)));

        $optionValues = $this->BuildGroupOptionFormValues();
        $this->UpdateFormField('GroupOptionList', 'values', json_encode($optionValues));
        $this->UpdateFormField('GroupOptionList', 'rowCount', min(8, max(4, \count($optionValues) + 1)));

        $sceneValues = $this->BuildGroupSceneFormValues();
        $this->UpdateFormField('GroupSceneList', 'values', json_encode($sceneValues));
        $this->UpdateFormField('GroupSceneList', 'rowCount', min(8, max(4, \count($sceneValues) + 1)));

        return true;
    }

    /**
     * Uebernimmt ein Gruppenmitglied in die Eingabefelder.
     */
    private function SelectGroupMemberFromForm(mixed $value): bool
    {
        $selection = $this->DecodeGroupFormPayload($value);
        if ($selection === null) {
            return false;
        }

        $device = (string) ($selection['device'] ?? '');
        $endpoint = (string) ($selection['endpoint'] ?? '');
        $endpointOptions = $this->BuildGroupMemberEndpointOptions($device, $endpoint);
        if ($endpoint === '' && \count($endpointOptions) > 0) {
            $endpoint = (string) $endpointOptions[0]['value'];
        }

        $this->UpdateFormField('GroupMemberDevice', 'value', $device);
        $this->UpdateFormField('GroupMemberEndpoint', 'options', json_encode($endpointOptions));
        $this->UpdateFormField('GroupMemberEndpoint', 'value', $endpoint);

        return true;
    }

    /**
     * Fuegt ein Gruppenmitglied hinzu.
     */
    private function AddGroupMemberFromForm(mixed $value): bool
    {
        $selection = $this->DecodeGroupFormPayload($value);
        if ($selection === null) {
            return false;
        }

        $device = trim((string) ($selection['device'] ?? ''));
        $endpoint = trim((string) ($selection['endpoint'] ?? ''));
        if ($device === '') {
            trigger_error($this->Translate('Device is required.'), E_USER_NOTICE);
            return false;
        }

        if (!$this->CallGroupBridgeFunctionWithPopup('AddDeviceToGroup', [$this->ReadPropertyString(self::MQTT_TOPIC), $device, $endpoint])) {
            return false;
        }

        $this->UpsertGroupMemberAttribute($device, $endpoint);
        $this->UpdateFormField('GroupMemberList', 'values', json_encode($this->BuildGroupMemberFormValues()));

        return true;
    }

    /**
     * Entfernt ein Gruppenmitglied.
     */
    private function RemoveGroupMemberFromForm(mixed $value): bool
    {
        $selection = $this->DecodeGroupFormPayload($value);
        if ($selection === null) {
            return false;
        }

        $device = trim((string) ($selection['device'] ?? ''));
        $endpoint = trim((string) ($selection['endpoint'] ?? ''));
        if ($device === '') {
            trigger_error($this->Translate('Device is required.'), E_USER_NOTICE);
            return false;
        }

        $skipDisableReporting = (bool) ($selection['skip_disable_reporting'] ?? true);
        if (!$this->CallGroupBridgeFunctionWithPopup('RemoveDeviceFromGroup', [$this->ReadPropertyString(self::MQTT_TOPIC), $device, $endpoint, $skipDisableReporting])) {
            return false;
        }

        $this->RemoveGroupMemberAttribute($device, $endpoint);
        $this->UpdateFormField('GroupMemberList', 'values', json_encode($this->BuildGroupMemberFormValues()));

        return true;
    }

    /**
     * Entfernt ein Geraet aus allen Gruppen.
     */
    private function RemoveGroupMemberFromAllGroupsFromForm(mixed $value): bool
    {
        $selection = $this->DecodeGroupFormPayload($value);
        if ($selection === null) {
            return false;
        }

        $device = trim((string) ($selection['device'] ?? ''));
        if ($device === '') {
            trigger_error($this->Translate('Device is required.'), E_USER_NOTICE);
            return false;
        }

        if (!$this->CallGroupBridgeFunctionWithPopup('RemoveDeviceFromAllGroups', [$device, (bool) ($selection['skip_disable_reporting'] ?? true)])) {
            return false;
        }

        $this->RemoveGroupMemberAttribute($device, '');
        $this->UpdateFormField('GroupMemberList', 'values', json_encode($this->BuildGroupMemberFormValues()));

        return true;
    }

    /**
     * Uebernimmt eine Gruppenoption in die Eingabefelder.
     */
    private function SelectGroupOptionFromForm(mixed $value): bool
    {
        $selection = $this->DecodeGroupFormPayload($value);
        if ($selection === null) {
            return false;
        }

        $this->UpdateFormField('GroupOptionName', 'value', (string) ($selection['name'] ?? ''));
        $this->ConfigureGroupOptionEditor((string) ($selection['name'] ?? ''), (string) ($selection['value'] ?? ''));

        return true;
    }

    /**
     * Ruft eine Bridge-Funktion für Gruppenmitglieder auf und zeigt Formularfehler als Popup.
     */
    private function CallGroupBridgeFunctionWithPopup(string $function, array $arguments): bool
    {
        $errorMessage = '';
        set_error_handler(static function (int $severity, string $message) use (&$errorMessage): bool
        {
            if (!\in_array($severity, [E_USER_NOTICE, E_USER_WARNING], true)) {
                return false;
            }

            $errorMessage = $message;
            return true;
        });

        try {
            $result = $this->CallMatchingBridgeFunction($function, $arguments);
        } finally {
            restore_error_handler();
        }

        if ($result === true) {
            return true;
        }

        if ($errorMessage !== '') {
            $this->SendDebug('Group request failed', $errorMessage, 0);
        }

        $this->ShowGroupMemberRequestError($errorMessage);
        return false;
    }

    /**
     * Zeigt einen lesbaren Fehler im Gruppenformular.
     */
    private function ShowGroupMemberRequestError(string $errorMessage): void
    {
        $isOffline = $this->IsGroupMemberOfflineError($errorMessage);
        $title = $this->Translate($isOffline ? 'Device offline' : 'Group request failed');
        $text = $this->Translate($isOffline
            ? 'The device did not respond to the group command. Please check whether it is online and try again.'
            : 'The Zigbee2MQTT group request failed. Please check the device and try again.');

        $this->UpdateFormField('GroupMemberRequestErrorTitle', 'caption', $title);
        $this->UpdateFormField('GroupMemberRequestErrorText', 'caption', $text);
        $this->UpdateFormField('GroupMemberRequestError', 'visible', true);
    }

    /**
     * Erkennt typische Zigbee2MQTT-Fehler fuer nicht erreichbare Geraete.
     */
    private function IsGroupMemberOfflineError(string $errorMessage): bool
    {
        $normalized = strtolower($errorMessage);
        return str_contains($normalized, 'delivery failed')
            || str_contains($normalized, 'timed out')
            || str_contains($normalized, 'timeout')
            || str_contains($normalized, 'did not respond')
            || str_contains($normalized, 'not available')
            || str_contains($normalized, 'offline');
    }

    /**
     * Speichert eine Gruppenoption.
     */
    private function ApplyGroupOptionFromForm(mixed $value): bool
    {
        $selection = $this->DecodeGroupFormPayload($value);
        if ($selection === null) {
            return false;
        }

        $name = trim((string) ($selection['name'] ?? ''));
        if ($name === '') {
            trigger_error($this->Translate('No group option selected.'), E_USER_NOTICE);
            return false;
        }

        try {
            $parsedValue = $this->ParseGroupOptionValue($name, $this->ResolveGroupOptionRawValue($name, $selection));
        } catch (\InvalidArgumentException $e) {
            trigger_error($this->Translate($e->getMessage()), E_USER_NOTICE);
            return false;
        }

        if ($this->CallMatchingBridgeFunction('SetGroupOptions', [
            $this->ReadPropertyString(self::MQTT_TOPIC),
            json_encode([$name => $parsedValue], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        ]) !== true) {
            return false;
        }

        $options = $this->ReadAttributeArray(self::ATTRIBUTE_GROUP_OPTIONS);
        $options[$name] = $parsedValue;
        $this->WriteAttributeArray(self::ATTRIBUTE_GROUP_OPTIONS, $options);
        $this->UpdateFormField('GroupOptionList', 'values', json_encode($this->BuildGroupOptionFormValues()));

        return true;
    }

    /**
     * Schaltet den passenden Editor fuer eine Gruppenoption sichtbar.
     */
    private function ConfigureGroupOptionEditor(string $name, string $rawValue): void
    {
        $definition = $this->GetGroupOptionDefinition($name);
        $editor = $this->GetGroupOptionEditor($definition);

        $this->UpdateFormField('GroupOptionEditor', 'value', $editor);
        $this->UpdateFormField('GroupOptionValue', 'visible', $editor === 'text');
        $this->UpdateFormField('GroupOptionBoolean', 'visible', $editor === 'boolean');
        $this->UpdateFormField('GroupOptionSelect', 'visible', $editor === 'select');
        $this->UpdateFormField('GroupFilteredAttributeList', 'visible', $editor === 'filtered_attributes');
        $this->UpdateFormField('GroupFilteredAttributeEditor', 'visible', $editor === 'filtered_attributes');

        if ($editor === 'boolean') {
            $this->UpdateFormField('GroupOptionBoolean', 'value', $this->ParseGroupOptionBooleanValue($rawValue));
            return;
        }

        if ($editor === 'select') {
            $options = $this->BuildGroupOptionSelectOptions($definition);
            $this->UpdateFormField('GroupOptionSelect', 'options', json_encode($options));
            $this->UpdateFormField('GroupOptionSelect', 'value', $this->NormalizeGroupOptionSelectValue($rawValue, $definition));
            return;
        }

        if ($editor === 'filtered_attributes') {
            try {
                $selected = $this->ParseGroupOptionArray($rawValue);
            } catch (\InvalidArgumentException) {
                $selected = [];
            }

            $this->UpdateGroupFilteredAttributeEditor($selected);
            return;
        }

        $this->UpdateFormField('GroupOptionValue', 'value', $rawValue);
    }

    /**
     * Ermittelt aus den sichtbaren Formularfeldern den Rohwert.
     */
    private function ResolveGroupOptionRawValue(string $name, array $selection): mixed
    {
        $definition = $this->GetGroupOptionDefinition($name);
        $editor = (string) ($selection['editor'] ?? $this->GetGroupOptionEditor($definition));

        return match ($editor) {
            'boolean'             => $selection['boolean'] ?? $selection['value'] ?? false,
            'select'              => (string) ($selection['selection'] ?? $selection['value'] ?? ''),
            'filtered_attributes' => (string) ($selection['value'] ?? '[]'),
            default               => (string) ($selection['value'] ?? '')
        };
    }

    /**
     * Liefert die bekannte Definition einer Gruppenoption.
     */
    private function GetGroupOptionDefinition(string $name): array
    {
        $definition = self::GROUP_OPTION_DEFINITIONS[$name] ?? [
            'type'        => 'mixed',
            'default'     => '',
            'description' => 'Option returned by Zigbee2MQTT.'
        ];

        return $definition;
    }

    /**
     * Liefert den Formulareditor passend zum Optionstyp.
     */
    private function GetGroupOptionEditor(array $definition): string
    {
        if (($definition['editor'] ?? '') === 'filtered_attributes') {
            return 'filtered_attributes';
        }

        $type = $this->NormalizeGroupOptionType((string) ($definition['type'] ?? 'mixed'));
        if ($type === 'boolean') {
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
    private function BuildGroupOptionSelectOptions(array $definition): array
    {
        $options = [];
        foreach (($definition['values'] ?? []) as $value) {
            if (!\is_array($value)) {
                continue;
            }

            $options[] = [
                'caption' => (string) ($value['caption'] ?? $value['value'] ?? ''),
                'value'   => (string) ($value['value'] ?? '')
            ];
        }

        return $options;
    }

    /**
     * Fuegt ein Filterattribut in den Editor ein.
     */
    private function AddGroupFilteredAttributeFromForm(mixed $value): bool
    {
        $selection = $this->DecodeGroupFormPayload($value);
        if ($selection === null) {
            return false;
        }

        $attribute = trim((string) ($selection['attribute'] ?? ''));
        $selected = $this->DecodeGroupFilteredAttributeSelection((string) ($selection['value'] ?? '[]'));
        if ($attribute !== '' && !\in_array($attribute, $selected, true)) {
            $selected[] = $attribute;
        }

        $this->UpdateGroupFilteredAttributeEditor($selected);
        return true;
    }

    /**
     * Entfernt ein Filterattribut aus dem Editor.
     */
    private function RemoveGroupFilteredAttributeFromForm(mixed $value): bool
    {
        $selection = $this->DecodeGroupFormPayload($value);
        if ($selection === null) {
            return false;
        }

        $attribute = trim((string) ($selection['attribute'] ?? ''));
        $selected = array_values(array_filter(
            $this->DecodeGroupFilteredAttributeSelection((string) ($selection['value'] ?? '[]')),
            static fn (string $entry): bool => $entry !== $attribute
        ));

        $this->UpdateGroupFilteredAttributeEditor($selected);
        return true;
    }

    /**
     * Aktualisiert Listen, Auswahl und Hidden-JSON fuer filtered_attributes.
     */
    private function UpdateGroupFilteredAttributeEditor(array $selected): void
    {
        $selected = $this->NormalizeGroupFilteredAttributes($selected);
        $listValues = $this->BuildGroupFilteredAttributeFormValues($selected);
        $candidateOptions = $this->BuildGroupFilteredAttributeCandidateOptions($selected);

        $this->UpdateFormField('GroupOptionValue', 'value', $this->FormatGroupFormValue($selected));
        $this->UpdateFormField('GroupFilteredAttributeList', 'values', json_encode($listValues));
        $this->UpdateFormField('GroupFilteredAttributeList', 'rowCount', min(8, max(3, \count($listValues) + 1)));
        $this->UpdateFormField('GroupFilteredAttributeCandidate', 'options', json_encode($candidateOptions));
        $this->UpdateFormField('GroupFilteredAttributeCandidate', 'value', (string) ($candidateOptions[0]['value'] ?? ''));
    }

    /**
     * Baut die Liste der aktuell gefilterten Attribute.
     */
    private function BuildGroupFilteredAttributeFormValues(array $selected): array
    {
        $values = [];
        foreach ($this->NormalizeGroupFilteredAttributes($selected) as $attribute) {
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
    private function BuildGroupFilteredAttributeCandidateOptions(array $selected): array
    {
        $selected = $this->NormalizeGroupFilteredAttributes($selected);
        $candidates = array_values(array_diff($this->BuildGroupFilteredAttributeCandidates($selected), $selected));

        return array_map(
            static fn (string $attribute): array => [
                'caption' => $attribute,
                'value'   => $attribute
            ],
            $candidates
        );
    }

    /**
     * Ermittelt die Attribute, die diese Gruppe potentiell im Payload anbietet.
     */
    private function BuildGroupFilteredAttributeCandidates(array $selected = []): array
    {
        $candidates = [];
        $this->CollectGroupPayloadAttributes($this->ReadAttributeArray(self::ATTRIBUTE_EXPOSES), $candidates);

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
        $candidates = array_values(array_unique(array_filter(array_map('strval', $candidates), [$this, 'IsGroupFilteredAttributeCandidate'])));
        usort($candidates, static fn (string $left, string $right): int => strnatcasecmp($left, $right));
        return $candidates;
    }

    /**
     * Sammelt Payload-Properties aus Expose-Strukturen.
     */
    private function CollectGroupPayloadAttributes(mixed $node, array &$candidates): void
    {
        if (!\is_array($node)) {
            return;
        }

        if (array_is_list($node)) {
            foreach ($node as $child) {
                $this->CollectGroupPayloadAttributes($child, $candidates);
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
                $this->CollectGroupPayloadAttributes($value, $candidates);
            }
        }
    }

    /**
     * Normalisiert den aktuellen filtered_attributes-Wert.
     */
    private function DecodeGroupFilteredAttributeSelection(string $rawValue): array
    {
        try {
            return $this->ParseGroupOptionArray($rawValue);
        } catch (\InvalidArgumentException) {
            return [];
        }
    }

    /**
     * Entfernt leere und doppelte Eintraege.
     */
    private function NormalizeGroupFilteredAttributes(array $attributes): array
    {
        $normalized = array_values(array_unique(array_filter(array_map(
            static fn (mixed $attribute): string => trim((string) $attribute),
            $attributes
        ), [$this, 'IsGroupFilteredAttributeCandidate'])));

        usort($normalized, static fn (string $left, string $right): int => strnatcasecmp($left, $right));
        return $normalized;
    }

    /**
     * Filtert technische oder leere Eintraege aus der Attributauswahl.
     */
    private function IsGroupFilteredAttributeCandidate(string $attribute): bool
    {
        return $attribute !== ''
            && !\in_array($attribute, ['filtered_attributes', 'friendly_name'], true);
    }

    /**
     * Normalisiert den aktuellen Wert fuer das Select-Feld.
     */
    private function NormalizeGroupOptionSelectValue(string $rawValue, array $definition): string
    {
        if ($rawValue === '') {
            return $this->FormatGroupFormValue($definition['default'] ?? '');
        }

        if ($rawValue === '-') {
            return 'null';
        }

        return $rawValue;
    }

    /**
     * Konvertiert Gruppenoptionen typisiert.
     */
    private function ParseGroupOptionValue(string $name, mixed $rawValue): mixed
    {
        $definition = $this->GetGroupOptionDefinition($name);
        $type = $this->NormalizeGroupOptionType((string) ($definition['type'] ?? 'mixed'));

        return match ($type) {
            'boolean' => $this->ParseGroupOptionBooleanValue($rawValue),
            'numeric' => $this->ParseGroupOptionNumber((string) $rawValue),
            'enum'    => $this->ParseGroupOptionEnum((string) $rawValue, $definition),
            'array'   => $this->ParseGroupOptionArray((string) $rawValue),
            'object'  => $this->ParseGroupOptionObject((string) $rawValue),
            'text'    => (string) $rawValue,
            default   => $this->ParseGroupFormValue((string) $rawValue)
        };
    }

    /**
     * Normalisiert interne Typnamen.
     */
    private function NormalizeGroupOptionType(string $type): string
    {
        return match (strtolower($type)) {
            'bool',
            'boolean',
            'binary' => 'boolean',
            'float',
            'integer',
            'number',
            'numeric' => 'numeric',
            'enum',
            'select' => 'enum',
            'list',
            'array' => 'array',
            'composite',
            'object' => 'object',
            'string',
            'text'  => 'text',
            default => 'mixed'
        };
    }

    /**
     * Liefert eine lesbare Typbezeichnung fuer die Optionsliste.
     */
    private function FormatGroupOptionType(string $type): string
    {
        return match ($this->NormalizeGroupOptionType($type)) {
            'boolean' => 'Boolean',
            'numeric' => 'Number',
            'enum'    => 'Selection',
            'array'   => 'Array',
            'object'  => 'Object',
            'text'    => 'Text',
            default   => 'Mixed'
        };
    }

    /**
     * Wandelt Formularwerte in boolesche Gruppenoptionen.
     */
    private function ParseGroupOptionBooleanValue(mixed $rawValue): bool
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

        throw new \InvalidArgumentException('Group option value must be true or false.');
    }

    /**
     * Wandelt Formularwerte in numerische Gruppenoptionen.
     */
    private function ParseGroupOptionNumber(string $rawValue): int|float
    {
        $normalized = str_replace(',', '.', trim($rawValue));
        if (!is_numeric($normalized)) {
            throw new \InvalidArgumentException('Group option value must be numeric.');
        }

        return str_contains($normalized, '.') ? (float) $normalized : (int) $normalized;
    }

    /**
     * Wandelt Formularwerte in Enum-Gruppenoptionen.
     */
    private function ParseGroupOptionEnum(string $rawValue, array $definition): mixed
    {
        foreach (($definition['values'] ?? []) as $option) {
            if (!\is_array($option) || (string) ($option['value'] ?? '') !== $rawValue) {
                continue;
            }

            return \array_key_exists('actual', $option) ? $option['actual'] : (string) $option['value'];
        }

        throw new \InvalidArgumentException('Group option value is not allowed.');
    }

    /**
     * Wandelt Formularwerte in Array-Gruppenoptionen.
     */
    private function ParseGroupOptionArray(string $rawValue): array
    {
        $rawValue = trim($rawValue);
        if ($rawValue === '') {
            return [];
        }

        $decoded = json_decode($rawValue, true);
        if (\is_array($decoded) && array_is_list($decoded)) {
            return $decoded;
        }

        if (str_starts_with($rawValue, '[')) {
            throw new \InvalidArgumentException('Group option value must be a JSON array.');
        }

        return array_values(array_filter(array_map('trim', explode(',', $rawValue)), static fn (string $entry): bool => $entry !== ''));
    }

    /**
     * Wandelt Formularwerte in Objekt-Gruppenoptionen.
     */
    private function ParseGroupOptionObject(string $rawValue): array
    {
        $decoded = json_decode(trim($rawValue), true);
        if (\is_array($decoded) && !array_is_list($decoded)) {
            return $decoded;
        }

        throw new \InvalidArgumentException('Group option value must be a JSON object.');
    }

    /**
     * Uebernimmt eine Szene in die Eingabefelder.
     */
    private function SelectGroupSceneFromForm(mixed $value): bool
    {
        $selection = $this->DecodeGroupFormPayload($value);
        if ($selection === null) {
            return false;
        }

        $sceneID = (int) ($selection['id'] ?? 0);
        $sceneName = (string) ($selection['name'] ?? '');
        $this->UpdateFormField('GroupSceneID', 'value', $sceneID);
        $this->UpdateFormField('GroupSceneName', 'value', $sceneName);
        $this->UpdateFormField('GroupSceneJson', 'value', json_encode(['ID' => $sceneID, 'name' => $sceneName], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return true;
    }

    /**
     * Speichert den aktuellen Gruppenzustand als Szene.
     */
    private function StoreGroupSceneFromForm(mixed $value): bool
    {
        $selection = $this->DecodeGroupFormPayload($value);
        if ($selection === null) {
            return false;
        }

        $sceneID = (int) ($selection['id'] ?? 0);
        $sceneName = trim((string) ($selection['name'] ?? ''));
        if ($this->CallMatchingBridgeFunction('StoreScene', [$this->ReadPropertyString(self::MQTT_TOPIC), $sceneID, $sceneName, 0]) !== true) {
            return false;
        }

        $this->UpsertGroupSceneAttribute($sceneID, $sceneName);
        $this->UpdateFormField('GroupSceneList', 'values', json_encode($this->BuildGroupSceneFormValues()));

        return true;
    }

    /**
     * Fuegt eine erweiterte Szene hinzu.
     */
    private function AddGroupSceneFromForm(mixed $value): bool
    {
        $sceneJSON = trim((string) $value);
        if ($sceneJSON === '') {
            trigger_error($this->Translate('Scene JSON is required.'), E_USER_NOTICE);
            return false;
        }

        if ($this->CallMatchingBridgeFunction('AddScene', [$this->ReadPropertyString(self::MQTT_TOPIC), $sceneJSON]) !== true) {
            return false;
        }

        $scene = json_decode($sceneJSON, true);
        if (\is_array($scene) && isset($scene['ID'])) {
            $this->UpsertGroupSceneAttribute((int) $scene['ID'], (string) ($scene['name'] ?? ''));
            $this->UpdateFormField('GroupSceneList', 'values', json_encode($this->BuildGroupSceneFormValues()));
        }

        return true;
    }

    /**
     * Ruft eine Szene ab.
     */
    private function RecallGroupSceneFromForm(mixed $value): bool
    {
        return $this->CallMatchingBridgeFunction('RecallScene', [$this->ReadPropertyString(self::MQTT_TOPIC), (int) $value]) === true;
    }

    /**
     * Benennt eine Szene um.
     */
    private function RenameGroupSceneFromForm(mixed $value): bool
    {
        $selection = $this->DecodeGroupFormPayload($value);
        if ($selection === null) {
            return false;
        }

        $sceneID = (int) ($selection['id'] ?? 0);
        $sceneName = trim((string) ($selection['name'] ?? ''));
        if ($sceneName === '') {
            trigger_error($this->Translate('Scene name is required.'), E_USER_NOTICE);
            return false;
        }

        if ($this->CallMatchingBridgeFunction('RenameScene', [$this->ReadPropertyString(self::MQTT_TOPIC), $sceneID, $sceneName]) !== true) {
            return false;
        }

        $this->UpsertGroupSceneAttribute($sceneID, $sceneName);
        $this->UpdateFormField('GroupSceneList', 'values', json_encode($this->BuildGroupSceneFormValues()));

        return true;
    }

    /**
     * Entfernt eine Szene.
     */
    private function RemoveGroupSceneFromForm(mixed $value): bool
    {
        $sceneID = (int) $value;
        if ($this->CallMatchingBridgeFunction('RemoveScene', [$this->ReadPropertyString(self::MQTT_TOPIC), $sceneID]) !== true) {
            return false;
        }

        $this->RemoveGroupSceneAttribute($sceneID);
        $this->UpdateFormField('GroupSceneList', 'values', json_encode($this->BuildGroupSceneFormValues()));

        return true;
    }

    /**
     * Entfernt alle Szenen.
     */
    private function RemoveAllGroupScenesFromForm(): bool
    {
        if ($this->CallMatchingBridgeFunction('RemoveAllScenes', [$this->ReadPropertyString(self::MQTT_TOPIC)]) !== true) {
            return false;
        }

        $this->WriteAttributeArray(self::ATTRIBUTE_GROUP_SCENES, []);
        $this->UpdateFormField('GroupSceneList', 'values', json_encode([]));

        return true;
    }

    /**
     * Dekodiert JSON-Payloads aus Formularaktionen.
     */
    private function DecodeGroupFormPayload(mixed $value): ?array
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
     * Konvertiert einfache Formularwerte in JSON-, Boolean-, Zahlen- oder Textwerte.
     */
    private function ParseGroupFormValue(string $rawValue): mixed
    {
        $rawValue = trim($rawValue);
        if ($rawValue === '') {
            return '';
        }

        $decoded = json_decode($rawValue, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        $normalized = str_replace(',', '.', $rawValue);
        if (is_numeric($normalized)) {
            return str_contains($normalized, '.') ? (float) $normalized : (int) $normalized;
        }

        return $rawValue;
    }

    /**
     * Formatiert Werte fuer Formularlisten.
     */
    private function FormatGroupFormValue(mixed $value): string
    {
        if (\is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if ($value === null) {
            return 'null';
        }

        return (string) $value;
    }

    /**
     * Liefert den Anzeigenamen eines Gruppenmitglieds.
     */
    private function FormatGroupMemberDevice(array $member): string
    {
        return (string) ($member['friendly_name'] ?? $member['device'] ?? $member['ieee_address'] ?? '');
    }

    /**
     * Fuegt ein Gruppenmitglied lokal in den Cache ein oder aktualisiert es.
     */
    private function UpsertGroupMemberAttribute(string $device, string $endpoint): void
    {
        $members = $this->ReadAttributeArray(self::ATTRIBUTE_GROUP_MEMBERS);
        $entry = [
            'device'   => $device,
            'endpoint' => $endpoint
        ];

        foreach ($members as $index => $member) {
            if (!\is_array($member)) {
                continue;
            }
            if ($this->FormatGroupMemberDevice($member) === $device && (string) ($member['endpoint'] ?? '') === $endpoint) {
                $members[$index] = array_merge($member, $entry);
                $this->WriteAttributeArray(self::ATTRIBUTE_GROUP_MEMBERS, $members);
                return;
            }
        }

        $members[] = $entry;
        $this->WriteAttributeArray(self::ATTRIBUTE_GROUP_MEMBERS, $members);
    }

    /**
     * Entfernt ein Gruppenmitglied lokal aus dem Cache.
     */
    private function RemoveGroupMemberAttribute(string $device, string $endpoint): void
    {
        $members = [];
        foreach ($this->ReadAttributeArray(self::ATTRIBUTE_GROUP_MEMBERS) as $member) {
            if (!\is_array($member)) {
                continue;
            }
            $sameDevice = $this->FormatGroupMemberDevice($member) === $device;
            $sameEndpoint = $endpoint === '' || (string) ($member['endpoint'] ?? '') === $endpoint;
            if ($sameDevice && $sameEndpoint) {
                continue;
            }
            $members[] = $member;
        }

        $this->WriteAttributeArray(self::ATTRIBUTE_GROUP_MEMBERS, $members);
    }

    /**
     * Fuegt eine Szene lokal in den Cache ein oder aktualisiert sie.
     */
    private function UpsertGroupSceneAttribute(int $sceneID, string $sceneName): void
    {
        $scenes = $this->ReadAttributeArray(self::ATTRIBUTE_GROUP_SCENES);
        foreach ($scenes as $index => $scene) {
            if (!\is_array($scene)) {
                continue;
            }
            if ((int) ($scene['id'] ?? $scene['ID'] ?? -1) === $sceneID) {
                $scenes[$index] = array_merge($scene, ['id' => $sceneID, 'name' => $sceneName]);
                $this->WriteAttributeArray(self::ATTRIBUTE_GROUP_SCENES, $scenes);
                return;
            }
        }

        $scenes[] = ['id' => $sceneID, 'name' => $sceneName];
        $this->WriteAttributeArray(self::ATTRIBUTE_GROUP_SCENES, $scenes);
    }

    /**
     * Entfernt eine Szene lokal aus dem Cache.
     */
    private function RemoveGroupSceneAttribute(int $sceneID): void
    {
        $scenes = [];
        foreach ($this->ReadAttributeArray(self::ATTRIBUTE_GROUP_SCENES) as $scene) {
            if (!\is_array($scene)) {
                continue;
            }
            if ((int) ($scene['id'] ?? $scene['ID'] ?? -1) === $sceneID) {
                continue;
            }
            $scenes[] = $scene;
        }

        $this->WriteAttributeArray(self::ATTRIBUTE_GROUP_SCENES, $scenes);
    }

    /**
     * Setzt ein Feld in der verschachtelten Form.
     */
    private function SetGroupFormField(array &$node, string $name, string $field, mixed $value): bool
    {
        if (($node['name'] ?? null) === $name) {
            $node[$field] = $value;
            return true;
        }

        foreach ($node as &$child) {
            if (!\is_array($child)) {
                continue;
            }
            if ($this->SetGroupFormField($child, $name, $field, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Erweitert den ReceiveFilter um Listen-Antworten fuer die Geraeteauswahl.
     */
    private function UpdateGroupReceiveDataFilter(): void
    {
        if ($this->GetStatus() !== IS_ACTIVE) {
            return;
        }

        $baseTopic = $this->ReadPropertyString(self::MQTT_BASE_TOPIC);
        $mqttTopic = $this->ReadPropertyString(self::MQTT_TOPIC);
        if ($baseTopic === '' || $mqttTopic === '') {
            return;
        }

        $filterAvailability = preg_quote('"Topic":"' . $baseTopic . '/' . $mqttTopic . '/' . self::AVAILABILITY_TOPIC . '"');
        $filterPayload = preg_quote('"Topic":"' . $baseTopic . '/' . $mqttTopic . '"');
        $filterInfo = preg_quote('"Topic":"' . $baseTopic . self::SYMCON_EXTENSION_RESPONSE . static::$ExtensionTopic . $mqttTopic . '"');
        $filterDeviceList = preg_quote('"Topic":"' . $baseTopic . self::SYMCON_EXTENSION_LIST_RESPONSE . 'getDevices"');

        $this->SetReceiveDataFilter('.*(' . $filterAvailability . '|' . $filterPayload . '|' . $filterInfo . '|' . $filterDeviceList . ').*');
    }
}
