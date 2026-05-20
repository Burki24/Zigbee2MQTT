<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/libs/ModulBase.php';

class Zigbee2MQTTGroup extends \Zigbee2MQTT\ModulBase
{
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
        return json_encode($this->BuildGroupConfigurationForm($Form));
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
        if ($ident == 'SelectGroupMember') {
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
        if (!isset($Result['foundGroup'])) {
            trigger_error($this->Translate('Group not found. Check topic.'), E_USER_NOTICE);
            return false;

        }
        $this->WriteAttributeArray(self::ATTRIBUTE_GROUP_OPTIONS, \is_array($Result['options'] ?? null) ? $Result['options'] : []);
        $this->WriteAttributeArray(self::ATTRIBUTE_GROUP_MEMBERS, \is_array($Result['members'] ?? null) ? $Result['members'] : []);
        $this->WriteAttributeArray(self::ATTRIBUTE_GROUP_SCENES, \is_array($Result['scenes'] ?? null) ? $Result['scenes'] : []);
        unset($Result['foundGroup'], $Result['id'], $Result['options'], $Result['members'], $Result['scenes']);
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
        $this->SetGroupFormField($form, 'GroupMembersSettings', 'visible', $configured);
        $this->SetGroupFormField($form, 'GroupMemberList', 'values', $memberValues);
        $this->SetGroupFormField($form, 'GroupMemberList', 'rowCount', min(10, max(4, \count($memberValues) + 1)));

        $optionValues = $this->BuildGroupOptionFormValues();
        $this->SetGroupFormField($form, 'GroupOptionsSettings', 'visible', $configured);
        $this->SetGroupFormField($form, 'GroupOptionList', 'values', $optionValues);
        $this->SetGroupFormField($form, 'GroupOptionList', 'rowCount', min(8, max(4, \count($optionValues) + 1)));

        $sceneValues = $this->BuildGroupSceneFormValues();
        $this->SetGroupFormField($form, 'GroupScenesSettings', 'visible', $configured);
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
     * Baut die Optionsliste fuer die Gruppenform.
     */
    private function BuildGroupOptionFormValues(): array
    {
        $values = [];
        foreach ($this->ReadAttributeArray(self::ATTRIBUTE_GROUP_OPTIONS) as $name => $currentValue) {
            $values[] = [
                'name'    => (string) $name,
                'current' => $this->FormatGroupFormValue($currentValue),
                'action'  => $this->Translate('Edit')
            ];
        }

        ksort($values);
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
     * Uebernimmt ein Gruppenmitglied in die Eingabefelder.
     */
    private function SelectGroupMemberFromForm(mixed $value): bool
    {
        $selection = $this->DecodeGroupFormPayload($value);
        if ($selection === null) {
            return false;
        }

        $this->UpdateFormField('GroupMemberDevice', 'value', (string) ($selection['device'] ?? ''));
        $this->UpdateFormField('GroupMemberEndpoint', 'value', (string) ($selection['endpoint'] ?? ''));

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

        if ($this->CallMatchingBridgeFunction('AddDeviceToGroup', [$this->ReadPropertyString(self::MQTT_TOPIC), $device, $endpoint]) !== true) {
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
        if ($this->CallMatchingBridgeFunction('RemoveDeviceFromGroup', [$this->ReadPropertyString(self::MQTT_TOPIC), $device, $endpoint, $skipDisableReporting]) !== true) {
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

        if ($this->CallMatchingBridgeFunction('RemoveDeviceFromAllGroups', [$device, (bool) ($selection['skip_disable_reporting'] ?? true)]) !== true) {
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
        $this->UpdateFormField('GroupOptionValue', 'value', (string) ($selection['value'] ?? ''));

        return true;
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

        $parsedValue = $this->ParseGroupFormValue((string) ($selection['value'] ?? ''));
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
            return '';
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
}
