<?php

declare(strict_types=1);

/**
 * Verwaltet die Zigbee2MQTT Blocklist und Passlist samt Bridge-Formularlogik.
 */
trait BridgeNetworkSecurityHelper
{
    /**
     * Setzt eine globale Z2M-Netzwerksicherheitsliste.
     */
    private function SetNetworkSecurityList(string $listName, string $DevicesJSON): bool
    {
        $devices = $this->ParseNetworkSecurityDeviceList($DevicesJSON);
        if ($devices === null) {
            return false;
        }

        $attribute = $this->GetNetworkSecurityAttribute($listName);
        if ($attribute === '') {
            return false;
        }

        $data = $this->SendCheckedBridgeRequest('/bridge/request/options', [
            'options' => [
                $listName => $devices
            ]
        ]);
        if ($data === false) {
            return false;
        }

        $this->WriteAttributeArray($attribute, $devices);
        $this->UpdateNetworkSecurityFormLists();
        return true;
    }

    /**
     * Fuegt ein Geraet sofort zur Blocklist hinzu.
     */
    private function AddNetworkSecurityDeviceFromForm(string $listName, mixed $value): bool
    {
        $selection = $this->DecodeBridgeFormPayload($value);
        if ($selection === null) {
            return false;
        }

        $ieeeAddress = $this->ResolveNetworkSecurityFormIeeeAddress($selection);
        if ($ieeeAddress === '') {
            $this->ShowNetworkSecurityFormError('Please select a known device or enter a valid IEEE address.');
            return false;
        }

        return $this->ApplyNetworkSecurityListChange($listName, 'add', $ieeeAddress);
    }

    /**
     * Entfernt ein Geraet sofort aus der Blocklist.
     */
    private function RemoveNetworkSecurityDeviceFromForm(string $listName, mixed $value): bool
    {
        $selection = $this->DecodeBridgeFormPayload($value);
        if ($selection === null) {
            return false;
        }

        $ieeeAddress = $this->NormalizeNetworkSecurityIeeeAddress((string) ($selection['ieee_address'] ?? ''));
        if ($ieeeAddress === '') {
            $this->ShowNetworkSecurityFormError('The selected list entry does not contain a valid IEEE address.');
            return false;
        }

        return $this->ApplyNetworkSecurityListChange($listName, 'remove', $ieeeAddress);
    }

    /**
     * Speichert eine Passlist-Aenderung erst nach einer Bestaetigung.
     */
    private function RequestPasslistChangeFromForm(mixed $value): bool
    {
        $selection = $this->DecodeBridgeFormPayload($value);
        if ($selection === null) {
            return false;
        }

        $operation = (string) ($selection['operation'] ?? '');
        $ieeeAddress = $operation === 'add'
            ? $this->ResolveNetworkSecurityFormIeeeAddress($selection)
            : $this->NormalizeNetworkSecurityIeeeAddress((string) ($selection['ieee_address'] ?? ''));
        if ($ieeeAddress === '') {
            $this->ShowNetworkSecurityFormError('Please select a known device or enter a valid IEEE address.');
            return false;
        }
        if (!\in_array($operation, ['add', 'remove'], true)) {
            return false;
        }

        $this->WriteAttributeArray(self::ATTRIBUTE_PENDING_PASSLIST_CHANGE, [
            'operation'    => $operation,
            'ieee_address' => $ieeeAddress
        ]);
        $this->TryUpdateFormField(
            'PasslistWarningText',
            'caption',
            \sprintf(
                $this->Translate('You are about to change the passlist for %s. Devices not contained in the passlist are removed from the network by Zigbee2MQTT.'),
                $ieeeAddress
            )
        );
        $this->TryUpdateFormField('PasslistWarning', 'visible', true);
        return true;
    }

    /**
     * Fuehrt eine zuvor bestaetigte Passlist-Aenderung aus.
     */
    private function ApplyPendingPasslistChange(): bool
    {
        $pending = $this->ReadAttributeArray(self::ATTRIBUTE_PENDING_PASSLIST_CHANGE);
        $operation = (string) ($pending['operation'] ?? '');
        $ieeeAddress = (string) ($pending['ieee_address'] ?? '');
        if (!\in_array($operation, ['add', 'remove'], true) || $ieeeAddress === '') {
            return false;
        }

        if (!$this->ApplyNetworkSecurityListChange('passlist', $operation, $ieeeAddress)) {
            return false;
        }

        $this->WriteAttributeArray(self::ATTRIBUTE_PENDING_PASSLIST_CHANGE, []);
        $this->TryUpdateFormField('PasslistWarning', 'visible', false);
        return true;
    }

    /**
     * Aendert eine Sicherheitsliste und schreibt sie nach Zigbee2MQTT.
     */
    private function ApplyNetworkSecurityListChange(string $listName, string $operation, string $ieeeAddress): bool
    {
        $attribute = $this->GetNetworkSecurityAttribute($listName);
        if ($attribute === '') {
            return false;
        }

        $ieeeAddress = $this->NormalizeNetworkSecurityIeeeAddress($ieeeAddress);
        if ($ieeeAddress === '') {
            $this->ShowNetworkSecurityFormError('The IEEE address must start with 0x and contain 16 hexadecimal characters.');
            return false;
        }

        $devices = $this->ReadAttributeArray($attribute);
        if ($operation === 'add' && !\in_array($ieeeAddress, $devices, true)) {
            $devices[] = $ieeeAddress;
        }
        if ($operation === 'remove') {
            $devices = array_values(array_filter($devices, static fn (mixed $device): bool => (string) $device !== $ieeeAddress));
        }

        return $listName === 'passlist'
            ? $this->SetPasslist(json_encode($devices, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
            : $this->SetBlocklist(json_encode($devices, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Uebernimmt ein Geraet in die Netzwerksicherheits-Eingabefelder.
     */
    private function SelectNetworkSecurityDeviceFromForm(mixed $value): bool
    {
        $selection = $this->DecodeBridgeFormPayload($value);
        if ($selection === null) {
            return false;
        }

        $ieeeAddress = $this->NormalizeNetworkSecurityIeeeAddress((string) ($selection['ieee_address'] ?? ''));
        if ($ieeeAddress === '') {
            $this->ShowNetworkSecurityFormError('The selected list entry does not contain a valid IEEE address.');
            return false;
        }

        $this->TryUpdateFormField('NetworkSecuritySelectedDevice', 'value', (string) ($selection['device'] ?? ''));
        $this->TryUpdateFormField('NetworkSecuritySelectedIeeeAddress', 'value', $ieeeAddress);
        $this->TryUpdateFormField('NetworkSecurityIeeeAddress', 'value', '');
        return true;
    }

    /**
     * Baut die Device-Liste fuer Blocklist und Passlist.
     */
    private function BuildNetworkSecurityAvailableDeviceFormValues(?array $devices = null): array
    {
        $values = [];
        foreach (($devices ?? $this->BuildNetworkSecurityDevices()) as $device) {
            if (!\is_array($device)) {
                continue;
            }

            $ieeeAddress = (string) ($device['ieee_address'] ?? '');
            if ($ieeeAddress === '') {
                continue;
            }

            $caption = (string) ($device['friendly_name'] ?? $ieeeAddress);
            $model = (string) ($device['model'] ?? '');
            if ($model !== '' && $model !== $caption) {
                $caption .= ' (' . $model . ')';
            }

            $values[] = [
                'device'       => $caption,
                'ieee_address' => $ieeeAddress,
                'action'       => $this->Translate('Select')
            ];
        }

        return $values;
    }

    /**
     * Baut die Formularzeilen fuer blocklist oder passlist.
     */
    private function BuildNetworkSecurityListFormValues(string $listName, ?array $devices = null): array
    {
        $attribute = $this->GetNetworkSecurityAttribute($listName);
        if ($attribute === '') {
            return [];
        }

        $deviceNames = $this->BuildNetworkSecurityDeviceNameMap($devices);
        $values = [];
        foreach ($this->ReadAttributeArray($attribute) as $ieeeAddress) {
            $ieeeAddress = (string) $ieeeAddress;
            $values[] = [
                'device'       => $deviceNames[$ieeeAddress] ?? '',
                'ieee_address' => $ieeeAddress,
                'action'       => $this->Translate('Remove')
            ];
        }

        return $values;
    }

    /**
     * Baut eine IEEE-zu-Name-Map fuer Listenanzeigen.
     */
    private function BuildNetworkSecurityDeviceNameMap(?array $devices = null): array
    {
        $names = [];
        foreach (($devices ?? $this->BuildNetworkSecurityDevices()) as $device) {
            if (!\is_array($device)) {
                continue;
            }

            $ieeeAddress = (string) ($device['ieee_address'] ?? '');
            if ($ieeeAddress === '') {
                continue;
            }

            $names[$ieeeAddress] = (string) ($device['friendly_name'] ?? '');
        }

        return $names;
    }

    /**
     * Aktualisiert die Sicherheitslisten nach Aenderungen.
     */
    private function UpdateNetworkSecurityFormLists(): void
    {
        $devices = $this->BuildNetworkSecurityDevices(true);
        $availableDevices = $this->BuildNetworkSecurityAvailableDeviceFormValues($devices);
        $this->TryUpdateFormField('NetworkSecurityAvailableDeviceList', 'values', json_encode($availableDevices));
        $this->TryUpdateFormField('NetworkSecurityAvailableDeviceList', 'rowCount', min(10, max(4, \count($availableDevices) + 1)));
        $this->TryUpdateFormField('NetworkSecurityBlocklist', 'values', json_encode($this->BuildNetworkSecurityListFormValues('blocklist', $devices)));
        $this->TryUpdateFormField('NetworkSecurityPasslist', 'values', json_encode($this->BuildNetworkSecurityListFormValues('passlist', $devices)));
    }

    /**
     * Zeigt Formularfehler im Netzwerksicherheitsbereich als Popup.
     */
    private function ShowNetworkSecurityFormError(string $message): void
    {
        $this->TryUpdateFormField('NetworkSecurityErrorText', 'caption', $this->Translate($message));
        $this->TryUpdateFormField('NetworkSecurityError', 'visible', true);
    }

    /**
     * Baut die bekannten Geraete aus Cache, Instanzen und optionalem Extension-Fallback.
     */
    private function BuildNetworkSecurityDevices(bool $includeExtensionFallback = false): array
    {
        $devices = [];
        foreach ($this->ReadAttributeArray(self::ATTRIBUTE_NETWORK_DEVICES) as $device) {
            $this->AddNetworkSecurityDevice($devices, $device);
        }
        foreach ($this->LoadNetworkSecurityDevicesFromInstances() as $device) {
            $this->AddNetworkSecurityDevice($devices, $device);
        }
        if ($devices === [] && $includeExtensionFallback) {
            foreach ($this->LoadNetworkSecurityDevicesFromExtension() as $device) {
                $this->AddNetworkSecurityDevice($devices, $device);
            }
        }

        $devices = array_values($devices);
        usort($devices, static fn (array $left, array $right): int => strnatcasecmp($left['friendly_name'], $right['friendly_name']));
        return $devices;
    }

    /**
     * Fuegt ein Geraet in eine IEEE-indizierte Liste ein.
     */
    private function AddNetworkSecurityDevice(array &$devices, mixed $device): void
    {
        if (!\is_array($device)) {
            return;
        }

        $ieeeAddress = $this->NormalizeNetworkSecurityIeeeAddress((string) ($device['ieee_address'] ?? $device['ieeeAddr'] ?? ''));
        if ($ieeeAddress === '') {
            return;
        }

        $devices[$ieeeAddress] = array_merge($devices[$ieeeAddress] ?? [], [
            'friendly_name' => (string) ($device['friendly_name'] ?? $device['friendlyName'] ?? $devices[$ieeeAddress]['friendly_name'] ?? ''),
            'ieee_address'  => $ieeeAddress,
            'model'         => (string) ($device['model'] ?? $devices[$ieeeAddress]['model'] ?? ''),
            'vendor'        => (string) ($device['vendor'] ?? $devices[$ieeeAddress]['vendor'] ?? ''),
            'type'          => (string) ($device['type'] ?? $devices[$ieeeAddress]['type'] ?? '')
        ]);
    }

    /**
     * Liest bekannte Device-Instanzen mit gleichem BaseTopic und MQTT-Splitter.
     */
    private function LoadNetworkSecurityDevicesFromInstances(): array
    {
        $baseTopic = $this->ReadPropertyString(self::MQTT_BASE_TOPIC);
        if ($baseTopic === '') {
            return [];
        }

        $devices = [];
        foreach (IPS_GetInstanceListByModuleID(self::GUID_MODULE_DEVICE) as $instanceID) {
            if (@IPS_GetProperty($instanceID, self::MQTT_BASE_TOPIC) !== $baseTopic
                || !$this->IsInstanceConnectedToSameSplitter($instanceID)
            ) {
                continue;
            }

            $ieeeAddress = $this->NormalizeNetworkSecurityIeeeAddress((string) @IPS_GetProperty($instanceID, 'IEEE'));
            if ($ieeeAddress === '') {
                continue;
            }

            $topic = trim((string) @IPS_GetProperty($instanceID, self::MQTT_TOPIC));
            $devices[] = [
                'friendly_name' => $topic !== '' ? $topic : @IPS_GetName($instanceID),
                'ieee_address'  => $ieeeAddress,
                'model'         => ''
            ];
        }

        return $devices;
    }

    /**
     * Fragt die Symcon-Extension nach bekannten Zigbee2MQTT-Geraeten.
     */
    private function LoadNetworkSecurityDevicesFromExtension(): array
    {
        try {
            if (!$this->HasActiveParent()) {
                return [];
            }
        } catch (\Throwable) {
            return [];
        }

        $result = @$this->SendData(
            self::SYMCON_EXTENSION_LIST_REQUEST . 'getDevices',
            [],
            self::TIMEOUT_SYMCON_EXTENSION_LIST_REQUEST
        );
        if (!\is_array($result) || !\is_array($result['list'] ?? null)) {
            return [];
        }

        return array_values(array_filter(
            $result['list'],
            static fn (mixed $device): bool => \is_array($device) && ($device['type'] ?? '') !== 'Coordinator'
        ));
    }

    /**
     * Liest die manuelle oder selektierte IEEE-Adresse aus der Form.
     */
    private function ResolveNetworkSecurityFormIeeeAddress(array $selection): string
    {
        $manualAddress = trim((string) ($selection['ieee_address'] ?? ''));
        if ($manualAddress !== '') {
            return $this->NormalizeNetworkSecurityIeeeAddress($manualAddress);
        }

        return $this->NormalizeNetworkSecurityIeeeAddress((string) ($selection['selected_ieee_address'] ?? ''));
    }

    /**
     * Validiert und normalisiert eine JSON-Liste aus IEEE-Adressen.
     */
    private function ParseNetworkSecurityDeviceList(string $devicesJSON): ?array
    {
        $decoded = json_decode($devicesJSON, true);
        if (!\is_array($decoded) || !array_is_list($decoded)) {
            trigger_error($this->Translate('Device list must be a JSON array.'), E_USER_NOTICE);
            return null;
        }

        foreach ($decoded as $device) {
            if ((string) $device === '') {
                continue;
            }
            if ($this->NormalizeNetworkSecurityIeeeAddress((string) $device) === '') {
                trigger_error($this->Translate('Invalid IEEE address.'), E_USER_NOTICE);
                return null;
            }
        }

        $devices = $this->NormalizeNetworkSecurityDeviceList($decoded);

        return $devices;
    }

    /**
     * Normalisiert eine Liste von IEEE-Adressen.
     */
    private function NormalizeNetworkSecurityDeviceList(mixed $devices): array
    {
        if (!\is_array($devices)) {
            return [];
        }

        $normalized = [];
        foreach ($devices as $device) {
            $ieeeAddress = $this->NormalizeNetworkSecurityIeeeAddress((string) $device);
            if ($ieeeAddress === '') {
                continue;
            }

            $normalized[$ieeeAddress] = $ieeeAddress;
        }

        return array_values($normalized);
    }

    /**
     * Normalisiert eine einzelne IEEE-Adresse.
     */
    private function NormalizeNetworkSecurityIeeeAddress(string $ieeeAddress): string
    {
        $ieeeAddress = strtolower(trim($ieeeAddress));
        return preg_match('/^0x[0-9a-f]{16}$/', $ieeeAddress) === 1 ? $ieeeAddress : '';
    }

    /**
     * Liefert das Attribut fuer eine Sicherheitsliste.
     */
    private function GetNetworkSecurityAttribute(string $listName): string
    {
        return match ($listName) {
            'blocklist' => self::ATTRIBUTE_CONFIG_BLOCKLIST,
            'passlist'  => self::ATTRIBUTE_CONFIG_PASSLIST,
            default     => ''
        };
    }

}
