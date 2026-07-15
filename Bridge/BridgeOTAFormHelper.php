<?php

declare(strict_types=1);

/**
 * Baut die OTA-Formularansicht auf und verarbeitet deren Interaktionen.
 */
trait BridgeOTAFormHelper
{
    /**
     * Baut die OTA-Statuszeilen aus den vorhandenen Device-Instanzen auf.
     */
    private function BuildOTADeviceRows(): array
    {
        $baseTopic = $this->ReadPropertyString(self::MQTT_BASE_TOPIC);
        if ($baseTopic === '') {
            return [];
        }

        $networkDevices = $this->BuildOTANetworkDeviceMap();
        $checkResults = $this->ReadAttributeArray(self::ATTRIBUTE_OTA_CHECK_RESULTS);
        $pendingRequests = $this->ReadActiveOTAPendingRequests();
        $rows = [];
        foreach (IPS_GetInstanceListByModuleID(self::GUID_MODULE_DEVICE) as $instanceID) {
            if (@IPS_GetProperty($instanceID, self::MQTT_BASE_TOPIC) !== $baseTopic
                || !$this->IsInstanceConnectedToSameSplitter($instanceID)
            ) {
                continue;
            }

            $deviceName = trim((string) @IPS_GetProperty($instanceID, self::MQTT_TOPIC), '/');
            if ($deviceName === '') {
                continue;
            }

            $ieeeAddress = strtolower((string) @IPS_GetProperty($instanceID, 'IEEE'));
            $networkDevice = $networkDevices[$deviceName] ?? $networkDevices[$ieeeAddress] ?? [];
            if (!(bool) ($networkDevice['supports_ota'] ?? false)) {
                continue;
            }

            $state = strtolower((string) $this->ReadOTADeviceVariable($instanceID, 'update__state', ''));
            $checkResult = \is_array($checkResults[$deviceName] ?? null) ? $checkResults[$deviceName] : [];
            $hasRecentCheckResult = (int) ($checkResult['checked_at'] ?? 0) >= time() - self::OTA_CHECK_RESULT_LIFETIME;
            if ($hasRecentCheckResult && ($checkResult['state'] ?? '') === 'idle') {
                $state = 'idle';
            } elseif (($pendingRequests[$deviceName] ?? null) !== null && $state !== 'updating') {
                $state = 'requested';
            } elseif ($state !== 'updating' && $hasRecentCheckResult) {
                $state = (string) ($checkResult['state'] ?? ((bool) ($checkResult['update_available'] ?? false) ? 'available' : 'idle'));
            } elseif ($state === '') {
                $state = 'unknown';
            }

            $rows[] = [
                'instance_id'       => $instanceID,
                'instance'          => $this->GetOTAInstanceCaption($instanceID),
                'device_name'       => $deviceName,
                'model'             => (string) ($networkDevice['model'] ?? ''),
                'power_source'      => (string) ($networkDevice['powerSource'] ?? ''),
                'state'             => $state,
                'installed_version' => $this->FormatOTAValue($this->ReadOTADeviceVariable($instanceID, 'update__installed_version', '')),
                'latest_version'    => $this->FormatOTAValue($this->ReadOTADeviceVariable($instanceID, 'update__latest_version', '')),
                'progress'          => $this->FormatOTAProgress($this->ReadOTADeviceVariable($instanceID, 'update__progress', '')),
                'remaining'         => $this->FormatOTARemaining($this->ReadOTADeviceVariable($instanceID, 'update__remaining', '')),
            ];
        }

        usort($rows, static fn (array $left, array $right): int => strnatcasecmp($left['instance'], $right['instance']));
        return $rows;
    }

    /**
     * Baut eine nach Friendly Name und IEEE-Adresse indizierte OTA-Geraeteliste.
     */
    private function BuildOTANetworkDeviceMap(): array
    {
        $devices = [];
        foreach ($this->ReadAttributeArray(self::ATTRIBUTE_NETWORK_DEVICES) as $device) {
            if (!\is_array($device)) {
                continue;
            }

            $friendlyName = trim((string) ($device['friendly_name'] ?? ''), '/');
            $ieeeAddress = strtolower((string) ($device['ieee_address'] ?? $device['ieeeAddr'] ?? ''));
            if ($friendlyName !== '') {
                $devices[$friendlyName] = $device;
            }
            if ($ieeeAddress !== '') {
                $devices[$ieeeAddress] = $device;
            }
        }

        return $devices;
    }

    /**
     * Liest eine optionale OTA-Variable einer Device-Instanz.
     */
    private function ReadOTADeviceVariable(int $instanceID, string $ident, mixed $default): mixed
    {
        $variableID = @IPS_GetObjectIDByIdent($ident, $instanceID);
        if ($variableID === false) {
            return $default;
        }

        return GetValue($variableID);
    }

    /**
     * Baut den Anzeigenamen einer Device-Instanz auf.
     */
    private function GetOTAInstanceCaption(int $instanceID): string
    {
        $location = \function_exists('IPS_GetLocation') ? (string) @IPS_GetLocation($instanceID) : '';
        return $location !== '' ? $location : (string) @IPS_GetName($instanceID);
    }

    /**
     * Formatiert einen einfachen OTA-Wert.
     */
    private function FormatOTAValue(mixed $value): string
    {
        return $value === '' || $value === null ? '-' : (string) $value;
    }

    /**
     * Formatiert den OTA-Fortschritt.
     */
    private function FormatOTAProgress(mixed $value): string
    {
        if ($value === '' || $value === null || !is_numeric($value)) {
            return '-';
        }

        return rtrim(rtrim(number_format((float) $value, 1, ',', ''), '0'), ',') . ' %';
    }

    /**
     * Formatiert die OTA-Restzeit.
     */
    private function FormatOTARemaining(mixed $value): string
    {
        if ($value === '' || $value === null || !is_numeric($value)) {
            return '-';
        }

        $seconds = max(0, (int) $value);
        if ($seconds < 60) {
            return $seconds . ' s';
        }

        return intdiv($seconds, 60) . ' min';
    }

    /**
     * Filtert OTA-Zeilen nach Status.
     */
    private function FilterOTADeviceRowsByState(array $rows, array $states): array
    {
        return array_values(array_filter(
            $rows,
            static fn (array $row): bool => \in_array((string) ($row['state'] ?? ''), $states, true)
        ));
    }

    /**
     * Baut die OTA-Uebersichtszeilen fuer alle bekannten OTA-Geraete auf.
     */
    private function BuildOTAKnownDeviceFormValues(array $rows): array
    {
        $values = [];
        foreach ($rows as $row) {
            $values[] = array_merge($row, [
                'state_caption' => $this->TranslateOTAState((string) ($row['state'] ?? 'unknown')),
                'action'        => $this->TranslateOTAFormText('Check update')
            ]);
        }

        return $values;
    }

    /**
     * Baut die OTA-Uebersichtszeilen fuer verfuegbare Updates auf.
     */
    private function BuildOTAAvailableUpdateFormValues(array $rows): array
    {
        $values = [];
        foreach ($rows as $row) {
            $values[] = array_merge($row, [
                'state_caption'   => $this->TranslateOTAState((string) ($row['state'] ?? 'unknown')),
                'update_action'   => $this->TranslateOTAFormText('Start update'),
                'schedule_action' => $this->TranslateOTAFormText('Schedule')
            ]);
        }

        return $values;
    }

    /**
     * Baut die OTA-Uebersichtszeilen fuer laufende und geplante Updates auf.
     */
    private function BuildOTAActiveUpdateFormValues(array $rows): array
    {
        $values = [];
        foreach ($rows as $row) {
            $state = (string) ($row['state'] ?? '');
            $values[] = array_merge($row, [
                'state_caption'  => $this->TranslateOTAState((string) ($row['state'] ?? 'unknown')),
                'action'         => $this->BuildOTAActiveUpdateActionCaption($state),
                'action_request' => $this->BuildOTAActiveUpdateActionRequest($state)
            ]);
        }

        return $values;
    }

    /**
     * Baut die Beschriftung der Zeilenaktion fuer aktive OTA-Updates.
     */
    private function BuildOTAActiveUpdateActionCaption(string $state): string
    {
        return match ($state) {
            'scheduled'            => $this->TranslateOTAFormText('Unschedule'),
            'requested', 'updating' => $this->TranslateOTAFormText('Abort'),
            default                => ''
        };
    }

    /**
     * Baut die RequestAction der Zeilenaktion fuer aktive OTA-Updates.
     */
    private function BuildOTAActiveUpdateActionRequest(string $state): string
    {
        return match ($state) {
            'scheduled'            => 'UnscheduleOTAUpdate',
            'requested', 'updating' => 'AbortOTAUpdate',
            default                => ''
        };
    }

    /**
     * Baut den Statushinweis fuer die OTA-Zentrale.
     */
    private function BuildOTAStatusCaption(array $rows): string
    {
        return sprintf(
            $this->TranslateOTAFormText('Known OTA devices: %d, available updates: %d, active or scheduled: %d'),
            \count($rows),
            \count($this->FilterOTADeviceRowsByState($rows, ['available'])),
            \count($this->FilterOTADeviceRowsByState($rows, ['requested', 'scheduled', 'updating']))
        );
    }

    /**
     * Uebersetzt einen OTA-Status fuer die Bridge-Oberflaeche.
     */
    private function TranslateOTAState(string $state): string
    {
        return $this->TranslateOTAFormText(match ($state) {
            'available' => 'Available',
            'requested' => 'Requested',
            'scheduled' => 'Scheduled',
            'updating'  => 'Updating',
            'idle'      => 'Idle',
            default     => 'Unknown'
        });
    }

    /**
     * Uebersetzt OTA-Formulartexte mit Originaltext als Fallback waehrend Modul-Updates.
     */
    private function TranslateOTAFormText(string $text): string
    {
        try {
            $translation = @$this->Translate($text);
            return \is_string($translation) ? $translation : $text;
        } catch (\Throwable) {
            return $text;
        }
    }

    /**
     * Aktualisiert alle OTA-Listen der Bridge-Konfiguration.
     */
    private function UpdateOTAFormLists(): void
    {
        $this->SynchronizeOTAMessageSubscriptions();
        $rows = $this->BuildOTADeviceRows();
        $availableRows = $this->FilterOTADeviceRowsByState($rows, ['available']);
        $activeRows = $this->FilterOTADeviceRowsByState($rows, ['requested', 'scheduled', 'updating']);
        if (!$this->TryUpdateFormField('OTAStatus', 'caption', $this->BuildOTAStatusCaption($rows))) {
            return;
        }
        $this->TryUpdateFormField('OTAKnownDevices', 'values', json_encode($this->BuildOTAKnownDeviceFormValues($rows)));
        $this->TryUpdateFormField('OTAKnownDevices', 'rowCount', min(10, max(3, \count($rows) + 1)));
        $this->TryUpdateFormField('OTAAvailableUpdates', 'values', json_encode($this->BuildOTAAvailableUpdateFormValues($availableRows)));
        $this->TryUpdateFormField('OTAAvailableUpdates', 'rowCount', min(8, max(3, \count($availableRows) + 1)));
        $this->TryUpdateFormField('OTAActiveUpdates', 'values', json_encode($this->BuildOTAActiveUpdateFormValues($activeRows)));
        $this->TryUpdateFormField('OTAActiveUpdates', 'rowCount', min(8, max(3, \count($activeRows) + 1)));
        $this->TryUpdateFormField('OTAUpdateResults', 'values', json_encode($this->BuildOTAUpdateResultFormValues()));
    }

    /**
     * Aktualisiert die OTA-Formulardaten, ohne MessageSink bei temporaer
     * nicht verfuegbaren Instanz- oder Formulardaten zu unterbrechen.
     */
    private function TryUpdateOTAFormLists(): void
    {
        try {
            $this->UpdateOTAFormLists();
        } catch (\Throwable $exception) {
            $this->SendDebug(__FUNCTION__, 'OTA-Formularaktualisierung uebersprungen: ' . $exception->getMessage(), 0);
        }
    }

    /**
     * Beobachtet OTA-Variablen und neue Kinder der zugehörigen Device-Instanzen.
     */
    private function SynchronizeOTAMessageSubscriptions(): void
    {
        $baseTopic = $this->ReadPropertyString(self::MQTT_BASE_TOPIC);
        $deviceIDs = [];
        $variableIDs = [];
        if ($baseTopic !== '') {
            $networkDevices = $this->BuildOTANetworkDeviceMap();
            foreach (IPS_GetInstanceListByModuleID(self::GUID_MODULE_DEVICE) as $instanceID) {
                if (@IPS_GetProperty($instanceID, self::MQTT_BASE_TOPIC) !== $baseTopic
                    || !$this->IsInstanceConnectedToSameSplitter($instanceID)
                ) {
                    continue;
                }

                $otaVariableIDs = [];
                foreach (IPS_GetChildrenIDs($instanceID) as $childID) {
                    if (!IPS_ObjectExists($childID)) {
                        continue;
                    }

                    $object = IPS_GetObject($childID);
                    if ($object['ObjectType'] === OBJECTTYPE_VARIABLE && str_starts_with((string) $object['ObjectIdent'], 'update__')) {
                        $otaVariableIDs[] = $childID;
                    }
                }

                $deviceName = trim((string) @IPS_GetProperty($instanceID, self::MQTT_TOPIC), '/');
                $ieeeAddress = strtolower((string) @IPS_GetProperty($instanceID, 'IEEE'));
                $networkDevice = $networkDevices[$deviceName] ?? $networkDevices[$ieeeAddress] ?? [];
                if (!(bool) ($networkDevice['supports_ota'] ?? false)) {
                    continue;
                }

                $deviceIDs[] = $instanceID;
                array_push($variableIDs, ...$otaVariableIDs);
            }
        }

        sort($deviceIDs);
        sort($variableIDs);
        $previousDeviceIDs = $this->ReadAttributeArray(self::ATTRIBUTE_OTA_MONITORED_DEVICES);
        $previousVariableIDs = $this->ReadAttributeArray(self::ATTRIBUTE_OTA_MONITORED_VARIABLES);
        foreach (array_diff($previousDeviceIDs, $deviceIDs) as $instanceID) {
            $this->UnregisterMessage((int) $instanceID, OM_CHILDADDED);
            $this->UnregisterMessage((int) $instanceID, OM_CHILDREMOVED);
        }
        foreach (array_diff($previousVariableIDs, $variableIDs) as $variableID) {
            $this->UnregisterMessage((int) $variableID, VM_UPDATE);
        }
        foreach (array_diff($deviceIDs, $previousDeviceIDs) as $instanceID) {
            $this->RegisterMessage((int) $instanceID, OM_CHILDADDED);
            $this->RegisterMessage((int) $instanceID, OM_CHILDREMOVED);
        }
        foreach (array_diff($variableIDs, $previousVariableIDs) as $variableID) {
            $this->RegisterMessage((int) $variableID, VM_UPDATE);
        }

        if ($previousDeviceIDs !== $deviceIDs) {
            $this->WriteAttributeArray(self::ATTRIBUTE_OTA_MONITORED_DEVICES, $deviceIDs);
        }
        if ($previousVariableIDs !== $variableIDs) {
            $this->WriteAttributeArray(self::ATTRIBUTE_OTA_MONITORED_VARIABLES, $variableIDs);
        }
    }

    /**
     * Prueft ein einzelnes Geraet auf ein OTA-Update und aktualisiert die Liste.
     */
    private function CheckOTAUpdateFromForm(mixed $value): bool
    {
        $row = $this->ResolveOTADeviceRow($value);
        if ($row === null) {
            return false;
        }

        $result = $this->SendDataQuiet('/bridge/request/device/ota_update/check', ['id' => $row['device_name']], 10000);
        if (!\is_array($result) || isset($result['error']) || (($result['status'] ?? 'ok') !== 'ok')) {
            $this->ShowOTAMessage('OTA check failed', 'Zigbee2MQTT did not return a successful OTA check result. Battery devices may need to be woken up first.');
            return false;
        }

        $data = \is_array($result['data'] ?? null) ? $result['data'] : [];
        $available = (bool) ($data['update_available'] ?? $data['updateAvailable'] ?? false);
        $this->StoreOTACheckResult($row['device_name'], $available, $available ? 'available' : 'idle');
        $this->UpdateOTAFormLists();
        $this->ShowOTAMessage(
            $available ? 'OTA update available' : 'No OTA update available',
            $available ? 'Zigbee2MQTT reports an available OTA update for the selected device.' : 'Zigbee2MQTT reports no available OTA update for the selected device.'
        );

        return true;
    }

    /**
     * Oeffnet die Sicherheitsabfrage fuer ein OTA-Update.
     */
    private function RequestOTAUpdateFromForm(mixed $value): bool
    {
        $row = $this->ResolveOTADeviceRow($value);
        if ($row === null) {
            return false;
        }

        if (($row['state'] ?? '') !== 'available') {
            $this->ShowOTAMessage('OTA update cannot be started', 'The selected device does not currently report an available OTA update.');
            return false;
        }
        if ($this->HasRunningOTAUpdate()) {
            $this->ShowOTAMessage('OTA update cannot be started', 'Another OTA update is already running. Please wait until it has finished.');
            return false;
        }

        $this->WriteAttributeArray(self::ATTRIBUTE_PENDING_OTA_UPDATE, $row);
        $this->TryUpdateFormField(
            'OTAUpdateWarningText',
            'caption',
            sprintf(
                $this->Translate('Start OTA update for %s? The device can be unavailable for a longer period and may restart.'),
                $row['instance']
            )
        );
        $this->TryUpdateFormField('OTAUpdateWarning', 'visible', true);
        return true;
    }

    /**
     * Startet das zuvor bestaetigte OTA-Update.
     */
    private function ConfirmPendingOTAUpdate(): bool
    {
        $pending = $this->ReadAttributeArray(self::ATTRIBUTE_PENDING_OTA_UPDATE);
        $this->WriteAttributeArray(self::ATTRIBUTE_PENDING_OTA_UPDATE, []);
        $this->TryUpdateFormField('OTAUpdateWarning', 'visible', false);
        $row = $this->ResolveOTADeviceRow($pending);
        if ($row === null) {
            return false;
        }

        if (($row['state'] ?? '') !== 'available') {
            $this->ShowOTAMessage('OTA update cannot be started', 'The selected device does not currently report an available OTA update.');
            return false;
        }
        if ($this->HasRunningOTAUpdate()) {
            $this->ShowOTAMessage('OTA update cannot be started', 'Another OTA update is already running. Please wait until it has finished.');
            return false;
        }
        if (!$this->PerformOTAUpdate($row['device_name'])) {
            $this->ShowOTAMessage('OTA update could not be submitted', 'Zigbee2MQTT did not accept the OTA update request.');
            return false;
        }

        $requests = $this->ReadActiveOTAPendingRequests();
        $requests[$row['device_name']] = [
            'instance_id'  => $row['instance_id'],
            'requested_at' => time()
        ];
        $this->WriteAttributeArray(self::ATTRIBUTE_OTA_PENDING_REQUESTS, $requests);
        $this->UpdateOTAFormLists();
        $this->ShowOTAMessage('OTA update submitted', 'The OTA update was submitted to Zigbee2MQTT. Use Refresh status to read the current progress.');
        return true;
    }

    /**
     * Plant ein OTA-Update fuer das ausgewaehlte Geraet.
     */
    private function ScheduleOTAUpdateFromForm(mixed $value): bool
    {
        $row = $this->ResolveOTADeviceRow($value);
        if ($row === null) {
            return false;
        }
        if (($row['state'] ?? '') !== 'available') {
            $this->ShowOTAMessage('OTA update could not be scheduled', 'The selected device does not currently report an available OTA update.');
            return false;
        }
        if (!$this->ScheduleOTAUpdate($row['device_name'])) {
            $this->ShowOTAMessage('OTA update could not be scheduled', 'Zigbee2MQTT did not accept the OTA schedule request. Battery devices may need to be woken up first.');
            return false;
        }

        $this->StoreOTACheckResult($row['device_name'], false, 'scheduled');
        $this->UpdateOTAFormLists();
        $this->ShowOTAMessage('OTA update scheduled', 'The OTA update was scheduled for the selected device.');
        return true;
    }

    /**
     * Hebt eine OTA-Planung fuer das ausgewaehlte Geraet auf.
     */
    private function UnscheduleOTAUpdateFromForm(mixed $value): bool
    {
        $row = $this->ResolveOTADeviceRow($value);
        if ($row === null) {
            return false;
        }
        if (($row['state'] ?? '') !== 'scheduled') {
            $this->ShowOTAMessage('OTA schedule could not be removed', 'The selected device does not currently report a scheduled OTA update.');
            return false;
        }
        if (!$this->UnscheduleOTAUpdate($row['device_name'])) {
            $this->ShowOTAMessage('OTA schedule could not be removed', 'Zigbee2MQTT did not accept the OTA unschedule request.');
            return false;
        }

        $this->StoreOTACheckResult($row['device_name'], false, 'idle');
        $this->UpdateOTAFormLists();
        $this->ShowOTAMessage('OTA schedule removed', 'The OTA schedule was removed for the selected device.');
        return true;
    }

    /**
     * Bricht ein laufendes oder angefordertes OTA-Update fuer das ausgewaehlte Geraet ab.
     */
    private function AbortOTAUpdateFromForm(mixed $value): bool
    {
        $row = $this->ResolveOTADeviceRow($value);
        if ($row === null) {
            return false;
        }
        if (!\in_array((string) ($row['state'] ?? ''), ['requested', 'updating'], true)) {
            $this->ShowOTAMessage('OTA update cannot be aborted', 'The selected device does not currently report an active OTA update.');
            return false;
        }
        if ($this->SendQuietCheckedBridgeRequest('/bridge/request/device/ota_update/update/abort', $this->BuildOTAPayload($row['device_name'], '')) === false) {
            $this->ShowOTAMessage('OTA update could not be aborted', 'Zigbee2MQTT did not accept the OTA abort request.');
            return false;
        }

        $requests = $this->ReadAttributeArray(self::ATTRIBUTE_OTA_PENDING_REQUESTS);
        unset($requests[$row['device_name']]);
        $this->WriteAttributeArray(self::ATTRIBUTE_OTA_PENDING_REQUESTS, $requests);
        $this->StoreOTACheckResult($row['device_name'], false, 'idle');
        $this->UpdateOTAFormLists();
        $this->ShowOTAMessage('OTA update abort requested', 'The OTA abort request was sent to Zigbee2MQTT.');
        return true;
    }

    /**
     * Loest eine Formularauswahl gegen die bekannten OTA-Geraete auf.
     */
    private function ResolveOTADeviceRow(mixed $value): ?array
    {
        $selection = \is_array($value) ? $value : $this->DecodeBridgeFormPayload($value);
        $instanceID = (int) ($selection['instance_id'] ?? 0);
        foreach ($this->BuildOTADeviceRows() as $row) {
            if ((int) ($row['instance_id'] ?? 0) === $instanceID) {
                return $row;
            }
        }

        $this->ShowOTAMessage('OTA device not found', 'The selected OTA device is no longer available in this bridge instance.');
        return null;
    }

    /**
     * Prueft, ob bereits ein OTA-Update aktiv ist.
     */
    private function HasRunningOTAUpdate(): bool
    {
        return $this->FilterOTADeviceRowsByState($this->BuildOTADeviceRows(), ['requested', 'updating']) !== [];
    }

    /**
     * Liest noch relevante unmittelbar gestartete OTA-Requests.
     */
    private function ReadActiveOTAPendingRequests(): array
    {
        $requests = $this->ReadAttributeArray(self::ATTRIBUTE_OTA_PENDING_REQUESTS);
        $minimumTimestamp = time() - self::OTA_PENDING_REQUEST_LIFETIME;
        $activeRequests = array_filter(
            $requests,
            static fn (mixed $request): bool => \is_array($request) && (int) ($request['requested_at'] ?? 0) >= $minimumTimestamp
        );
        if ($activeRequests !== $requests) {
            $this->WriteAttributeArray(self::ATTRIBUTE_OTA_PENDING_REQUESTS, $activeRequests);
        }

        return $activeRequests;
    }

    /**
     * Speichert einen kurzlebigen OTA-Zustand aus einer direkten Bridge-Antwort.
     */
    private function StoreOTACheckResult(string $deviceName, bool $updateAvailable, string $state): void
    {
        $checks = $this->ReadAttributeArray(self::ATTRIBUTE_OTA_CHECK_RESULTS);
        $checks[$deviceName] = [
            'checked_at'       => time(),
            'update_available' => $updateAvailable,
            'state'            => $state
        ];
        $this->WriteAttributeArray(self::ATTRIBUTE_OTA_CHECK_RESULTS, $checks);
    }

    /**
     * Verarbeitet die spaete Abschlussantwort eines asynchronen OTA-Updates.
     */
    private function HandleOTAUpdateResponse(array $payload, array $topics): bool
    {
        $responseTopic = implode('/', $topics);
        if (!\in_array($responseTopic, ['device/ota_update/update', 'device/ota_update/update/downgrade', 'device/ota_update/update/abort'], true)) {
            return false;
        }

        $data = \is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $deviceName = (string) ($data['id'] ?? $payload['id'] ?? '');
        $success = ($payload['status'] ?? '') === 'ok';
        $isAbort = $responseTopic === 'device/ota_update/update/abort';
        $message = $success
            ? $this->Translate($isAbort ? 'The OTA update was aborted.' : 'The OTA update completed successfully.')
            : (string) ($payload['error'] ?? $data['error'] ?? $this->Translate('Zigbee2MQTT reported an OTA update error.'));
        $this->AppendOTAUpdateResult([
            'time'        => time(),
            'device_name' => $deviceName,
            'status'      => $isAbort && $success ? 'aborted' : ($success ? 'successful' : 'failed'),
            'message'     => $message
        ]);

        $requests = $this->ReadAttributeArray(self::ATTRIBUTE_OTA_PENDING_REQUESTS);
        unset($requests[$deviceName]);
        $this->WriteAttributeArray(self::ATTRIBUTE_OTA_PENDING_REQUESTS, $requests);
        $checks = $this->ReadAttributeArray(self::ATTRIBUTE_OTA_CHECK_RESULTS);
        unset($checks[$deviceName]);
        if ($isAbort && $success && $deviceName !== '') {
            $checks[$deviceName] = [
                'checked_at'       => time(),
                'update_available' => false,
                'state'            => 'idle'
            ];
        }
        $this->WriteAttributeArray(self::ATTRIBUTE_OTA_CHECK_RESULTS, $checks);
        $this->ShowOTAMessage($isAbort && $success ? 'OTA update aborted' : ($success ? 'OTA update successful' : 'OTA update failed'), $message);
        return true;
    }

    /**
     * Speichert ein OTA-Abschlussergebnis als Ringpuffer.
     */
    private function AppendOTAUpdateResult(array $result): void
    {
        $results = $this->ReadAttributeArray(self::ATTRIBUTE_OTA_UPDATE_RESULTS);
        array_unshift($results, $result);
        $this->WriteAttributeArray(self::ATTRIBUTE_OTA_UPDATE_RESULTS, \array_slice($results, 0, self::MAX_OTA_RESULT_ENTRIES));
    }

    /**
     * Baut die OTA-Ergebnisliste fuer das Formular auf.
     */
    private function BuildOTAUpdateResultFormValues(): array
    {
        $values = [];
        foreach ($this->ReadAttributeArray(self::ATTRIBUTE_OTA_UPDATE_RESULTS) as $result) {
            if (!\is_array($result)) {
                continue;
            }

            $values[] = [
                'time'        => date('d.m.Y H:i:s', (int) ($result['time'] ?? 0)),
                'device_name' => (string) ($result['device_name'] ?? ''),
                'status'      => $this->TranslateOTAResultStatus((string) ($result['status'] ?? 'failed')),
                'message'     => (string) ($result['message'] ?? '')
            ];
        }

        return $values;
    }

    /**
     * Uebersetzt einen OTA-Ergebnisstatus fuer die Ergebnisliste.
     */
    private function TranslateOTAResultStatus(string $status): string
    {
        return $this->TranslateOTAFormText(match ($status) {
            'successful' => 'Successful',
            'aborted'    => 'Aborted',
            default      => 'Failed'
        });
    }

    /**
     * Zeigt eine OTA-Rueckmeldung in der Bridge-Konfiguration an.
     */
    private function ShowOTAMessage(string $title, string $message): void
    {
        $this->TryUpdateFormField('OTAMessageTitle', 'caption', $this->Translate($title));
        $this->TryUpdateFormField('OTAMessageText', 'caption', $this->Translate($message));
        $this->TryUpdateFormField('OTAMessage', 'visible', true);
    }

}

