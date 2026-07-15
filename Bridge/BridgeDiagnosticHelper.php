<?php

declare(strict_types=1);

/**
 * Sammelt, speichert und visualisiert Diagnoseinformationen der Bridge.
 */
trait BridgeDiagnosticHelper
{
    /**
     * HealthCheck
     *
     * @return bool true, wenn Zigbee2MQTT eine gesunde Bridge meldet.
     */
    public function HealthCheck(): bool
    {
        $data = $this->SendCheckedBridgeRequest('/bridge/request/health_check', [], 10000);
        if ($data === false) {
            return false;
        }

        $this->StoreHealthCheckResult($data);
        return (bool) ($data['healthy'] ?? false);
    }

    /**
     * CoordinatorCheck
     *
     * @return bool true, wenn keine fehlenden Router gemeldet wurden.
     */
    public function CoordinatorCheck(): bool
    {
        $data = $this->SendCheckedBridgeRequest('/bridge/request/coordinator_check', [], 10000);
        if ($data === false) {
            return false;
        }

        $this->StoreCoordinatorCheckResult($data);
        return \count($this->ReadDiagnosticMissingRouters()) === 0;
    }

    /**
     * ClearBridgeDiagnostics
     *
     * @return bool
     */
    public function ClearBridgeDiagnostics(): bool
    {
        $this->WriteAttributeArray(self::ATTRIBUTE_DIAGNOSTIC_EVENTS, []);
        $this->WriteAttributeArray(self::ATTRIBUTE_DIAGNOSTIC_LOGS, []);
        $this->WriteAttributeArray(self::ATTRIBUTE_DIAGNOSTIC_UNSUPPORTED_DEVICES, []);
        $this->WriteAttributeArray(self::ATTRIBUTE_DIAGNOSTIC_INTERVIEW_DEVICES, []);
        return true;
    }

    /**
     * Fuehrt den Health-Check aus der Bridge-Konfiguration ohne technische Notice aus.
     */
    private function RunHealthCheckFromForm(): bool
    {
        $data = $this->SendQuietCheckedBridgeRequest('/bridge/request/health_check', [], 10000);
        if ($data === false) {
            $this->ShowDiagnosticMessage(
                'Zigbee2MQTT is not reachable',
                'Zigbee2MQTT did not respond to the health check. Please check whether Zigbee2MQTT is running and connected to MQTT.'
            );
            return false;
        }

        $this->StoreHealthCheckResult($data);
        $this->UpdateDiagnosticFormFields();
        return (bool) ($data['healthy'] ?? false);
    }

    /**
     * Fuehrt den Coordinator-Check aus der Bridge-Konfiguration ohne technische Notice aus.
     */
    private function RunCoordinatorCheckFromForm(): bool
    {
        $data = $this->SendQuietCheckedBridgeRequest('/bridge/request/coordinator_check', [], 10000);
        if ($data === false) {
            $this->ShowDiagnosticMessage(
                'Zigbee2MQTT is not reachable',
                'Zigbee2MQTT did not respond to the coordinator check. Please check whether Zigbee2MQTT is running and connected to MQTT.'
            );
            return false;
        }

        $this->StoreCoordinatorCheckResult($data);
        $this->UpdateDiagnosticFormFields();
        return \count($this->ReadDiagnosticMissingRouters()) === 0;
    }

    /**
     * Speichert das Ergebnis eines Health-Checks oder des bridge/health Topics.
     */
    private function StoreHealthCheckResult(array $data): void
    {
        $previous = $this->ReadAttributeArray(self::ATTRIBUTE_DIAGNOSTIC_HEALTH);
        if (!\array_key_exists('healthy', $data) && \array_key_exists('healthy', $previous)) {
            $data['healthy'] = $previous['healthy'];
        }
        $data['checked_at'] = time();
        $this->WriteAttributeArray(self::ATTRIBUTE_DIAGNOSTIC_HEALTH, $data);
    }

    /**
     * Speichert das Ergebnis des Coordinator-Checks.
     */
    private function StoreCoordinatorCheckResult(array $data): void
    {
        $data['checked_at'] = time();
        $this->WriteAttributeArray(self::ATTRIBUTE_DIAGNOSTIC_COORDINATOR, $data);
    }

    /**
     * Aktualisiert die sichtbaren Diagnosefelder in einer geoeffneten Bridge-Konfiguration.
     */
    private function UpdateDiagnosticFormFields(): void
    {
        $this->TryUpdateFormField('DiagnosticHealthStatus', 'caption', $this->BuildHealthStatusCaption());
        $this->TryUpdateFormField('DiagnosticCoordinatorStatus', 'caption', $this->BuildCoordinatorStatusCaption());
        $missingRouters = $this->BuildMissingRouterFormValues();
        $this->TryUpdateFormField('DiagnosticMissingRoutersList', 'values', json_encode($missingRouters));
        $this->TryUpdateFormField('DiagnosticMissingRoutersList', 'rowCount', min(8, max(4, \count($missingRouters) + 1)));
    }

    /**
     * Zeigt eine lesbare Diagnosemeldung im Bridge-Formular.
     */
    private function ShowDiagnosticMessage(string $title, string $message): void
    {
        $this->TryUpdateFormField('DiagnosticMessageTitle', 'caption', $this->Translate($title));
        $this->TryUpdateFormField('DiagnosticMessageText', 'caption', $this->Translate($message));
        $this->TryUpdateFormField('DiagnosticMessage', 'visible', true);
    }

    /**
     * Sammelt warnende und fehlerhafte Bridge-Logmeldungen.
     */
    private function AppendBridgeLog(array $payload): void
    {
        $level = strtolower((string) ($payload['level'] ?? ''));
        if (!\in_array($level, ['warning', 'warn', 'error'], true)) {
            return;
        }

        $this->AppendDiagnosticEntry(self::ATTRIBUTE_DIAGNOSTIC_LOGS, [
            'time'      => time(),
            'level'     => $level === 'warn' ? 'warning' : $level,
            'namespace' => (string) ($payload['namespace'] ?? ''),
            'message'   => $this->FormatDiagnosticValue($payload['message'] ?? $payload)
        ]);
    }

    /**
     * Sammelt Bridge-Events als Ringpuffer.
     */
    private function AppendBridgeEvent(array $payload): void
    {
        $data = \is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $this->AppendDiagnosticEntry(self::ATTRIBUTE_DIAGNOSTIC_EVENTS, [
            'time'    => time(),
            'type'    => (string) ($payload['type'] ?? ''),
            'device'  => (string) ($data['friendly_name'] ?? $data['device'] ?? $data['id'] ?? ''),
            'message' => $this->FormatDiagnosticValue($data === [] ? $payload : $data)
        ]);
    }

    /**
     * Aktualisiert Diagnose-Listen aus bridge/devices.
     */
    private function UpdateDeviceDiagnostics(array $devices): void
    {
        $networkDevices = [];
        $unsupported = [];
        $interviewIssues = [];
        foreach ($devices as $device) {
            if (!\is_array($device)) {
                continue;
            }

            $row = $this->BuildDeviceDiagnosticRow($device);
            if (($row['status'] ?? '') !== 'Coordinator') {
                $networkDevices[] = $this->BuildCachedNetworkDeviceRow($device, $row);
            }
            if (($device['supported'] ?? true) === false || (\array_key_exists('definition', $device) && $device['definition'] === null)) {
                $unsupported[] = $row;
            }

            $interviewCompleted = $device['interview_completed'] ?? $device['interviewCompleted'] ?? null;
            $interviewing = (bool) ($device['interviewing'] ?? false);
            if ($interviewCompleted === false || $interviewing) {
                $row['status'] = $interviewing ? $this->Translate('Interview running') : $this->Translate('Interview incomplete');
                $interviewIssues[] = $row;
            }
        }

        usort($networkDevices, static fn (array $left, array $right): int => strnatcasecmp($left['friendly_name'], $right['friendly_name']));
        $this->WriteAttributeArray(self::ATTRIBUTE_NETWORK_DEVICES, $networkDevices);
        $this->WriteAttributeArray(self::ATTRIBUTE_DIAGNOSTIC_UNSUPPORTED_DEVICES, $unsupported);
        $this->WriteAttributeArray(self::ATTRIBUTE_DIAGNOSTIC_INTERVIEW_DEVICES, $interviewIssues);
        $this->SynchronizeOTAMessageSubscriptions();
    }

    /**
     * Fuegt einen Eintrag an den Anfang eines Diagnose-Ringpuffers.
     */
    private function AppendDiagnosticEntry(string $attribute, array $entry): void
    {
        $entries = $this->ReadAttributeArray($attribute);
        array_unshift($entries, $entry);
        $this->WriteAttributeArray($attribute, \array_slice($entries, 0, self::MAX_DIAGNOSTIC_ENTRIES));
    }

    /**
     * Erstellt eine Diagnose-Zeile fuer ein Geraet.
     */
    private function BuildDeviceDiagnosticRow(array $device): array
    {
        $definition = \is_array($device['definition'] ?? null) ? $device['definition'] : [];
        return [
            'friendly_name' => (string) ($device['friendly_name'] ?? $device['friendlyName'] ?? ''),
            'ieee_address'  => (string) ($device['ieee_address'] ?? $device['ieeeAddr'] ?? ''),
            'model'         => (string) ($definition['model'] ?? $device['model'] ?? ''),
            'vendor'        => (string) ($definition['vendor'] ?? $device['vendor'] ?? ''),
            'status'        => (string) ($device['type'] ?? '')
        ];
    }

    /**
     * Erstellt eine Cache-Zeile fuer den Konfigurator und lokale Auswahlfelder.
     */
    private function BuildCachedNetworkDeviceRow(array $device, array $diagnosticRow): array
    {
        $definition = \is_array($device['definition'] ?? null) ? $device['definition'] : [];
        $friendlyName = (string) ($device['friendly_name'] ?? $device['friendlyName'] ?? $diagnosticRow['friendly_name'] ?? '');
        $ieeeAddress = (string) ($device['ieee_address'] ?? $device['ieeeAddr'] ?? $diagnosticRow['ieee_address'] ?? '');

        return [
            'friendly_name'    => $friendlyName,
            'ieee_address'     => $ieeeAddress,
            'ieeeAddr'         => $ieeeAddress,
            'networkAddress'   => $device['networkAddress'] ?? $device['network_address'] ?? '',
            'type'             => (string) ($device['type'] ?? $diagnosticRow['status'] ?? ''),
            'model'            => (string) ($definition['model'] ?? $device['model'] ?? $diagnosticRow['model'] ?? ''),
            'vendor'           => (string) ($definition['vendor'] ?? $device['vendor'] ?? $diagnosticRow['vendor'] ?? ''),
            'description'      => (string) ($definition['description'] ?? $device['description'] ?? ''),
            'supports_ota'     => (bool) ($definition['supports_ota'] ?? $device['supports_ota'] ?? false),
            'manufacturerName' => (string) ($device['manufacturerName'] ?? $device['manufacturer_name'] ?? ''),
            'powerSource'      => (string) ($device['powerSource'] ?? $device['power_source'] ?? ''),
            'modelID'          => (string) ($device['modelID'] ?? $device['model_id'] ?? $definition['model'] ?? $device['model'] ?? ''),
            'endpoints'        => \is_array($device['endpoints'] ?? null) ? $device['endpoints'] : []
        ];
    }

    /**
     * Formatiert den Health-Status fuer die Form.
     */
    private function BuildHealthStatusCaption(): string
    {
        $health = $this->ReadAttributeArray(self::ATTRIBUTE_DIAGNOSTIC_HEALTH);
        if ($health === []) {
            return $this->Translate('Health status: not checked');
        }

        $status = \array_key_exists('healthy', $health)
            ? ((bool) $health['healthy'] ? $this->Translate('healthy') : $this->Translate('unhealthy'))
            : $this->Translate('health data received');

        return $this->Translate('Health status') . ': ' . $status . $this->FormatDiagnosticSuffix($health);
    }

    /**
     * Formatiert den Coordinator-Status fuer die Form.
     */
    private function BuildCoordinatorStatusCaption(): string
    {
        $coordinator = $this->ReadAttributeArray(self::ATTRIBUTE_DIAGNOSTIC_COORDINATOR);
        if ($coordinator === []) {
            return $this->Translate('Coordinator status: not checked');
        }

        $missingRouters = $this->ReadDiagnosticMissingRouters();
        $status = \count($missingRouters) === 0
            ? $this->Translate('no missing routers')
            : \sprintf($this->Translate('%d missing routers'), \count($missingRouters));

        return $this->Translate('Coordinator status') . ': ' . $status . $this->FormatDiagnosticSuffix($coordinator);
    }

    /**
     * Gibt die fehlenden Router aus dem letzten Coordinator-Check zurueck.
     */
    private function ReadDiagnosticMissingRouters(): array
    {
        $coordinator = $this->ReadAttributeArray(self::ATTRIBUTE_DIAGNOSTIC_COORDINATOR);
        $missingRouters = $coordinator['missing_routers'] ?? $coordinator['missingRouters'] ?? [];
        return \is_array($missingRouters) ? $missingRouters : [];
    }

    /**
     * Baut die Formwerte fuer fehlende Router.
     */
    private function BuildMissingRouterFormValues(): array
    {
        $values = [];
        foreach ($this->ReadDiagnosticMissingRouters() as $router) {
            if (!\is_array($router)) {
                continue;
            }

            $values[] = [
                'friendly_name' => (string) ($router['friendly_name'] ?? ''),
                'ieee_address'  => (string) ($router['ieee_address'] ?? $router['ieeeAddr'] ?? '')
            ];
        }

        return $values;
    }

    /**
     * Baut die Formwerte fuer Geraete-Diagnoselisten.
     */
    private function BuildDeviceDiagnosticFormValues(string $attribute): array
    {
        $values = [];
        foreach ($this->ReadAttributeArray($attribute) as $entry) {
            if (!\is_array($entry)) {
                continue;
            }

            $values[] = [
                'friendly_name' => (string) ($entry['friendly_name'] ?? ''),
                'ieee_address'  => (string) ($entry['ieee_address'] ?? ''),
                'model'         => (string) ($entry['model'] ?? ''),
                'vendor'        => (string) ($entry['vendor'] ?? ''),
                'status'        => (string) ($entry['status'] ?? '')
            ];
        }

        return $values;
    }

    /**
     * Baut die Formwerte fuer Bridge-Events.
     */
    private function BuildEventFormValues(): array
    {
        $values = [];
        foreach ($this->ReadAttributeArray(self::ATTRIBUTE_DIAGNOSTIC_EVENTS) as $entry) {
            if (!\is_array($entry)) {
                continue;
            }

            $values[] = [
                'time'    => $this->FormatDiagnosticTimestamp($entry['time'] ?? 0),
                'type'    => (string) ($entry['type'] ?? ''),
                'device'  => (string) ($entry['device'] ?? ''),
                'message' => (string) ($entry['message'] ?? '')
            ];
        }

        return $values;
    }

    /**
     * Baut die Formwerte fuer Bridge-Warnungen und Fehler.
     */
    private function BuildLogFormValues(): array
    {
        $values = [];
        foreach ($this->ReadAttributeArray(self::ATTRIBUTE_DIAGNOSTIC_LOGS) as $entry) {
            if (!\is_array($entry)) {
                continue;
            }

            $values[] = [
                'time'      => $this->FormatDiagnosticTimestamp($entry['time'] ?? 0),
                'level'     => (string) ($entry['level'] ?? ''),
                'namespace' => (string) ($entry['namespace'] ?? ''),
                'message'   => (string) ($entry['message'] ?? '')
            ];
        }

        return $values;
    }

    /**
     * Formatiert einen Diagnose-Zeitstempel.
     */
    private function FormatDiagnosticTimestamp(mixed $timestamp): string
    {
        $timestamp = (int) $timestamp;
        return $timestamp > 0 ? date('d.m.Y H:i:s', $timestamp) : '';
    }

    /**
     * Liefert einen optionalen Zeitstempel-Suffix fuer Statuszeilen.
     */
    private function FormatDiagnosticSuffix(array $entry): string
    {
        $timestamp = $this->FormatDiagnosticTimestamp($entry['checked_at'] ?? 0);
        return $timestamp === '' ? '' : ' (' . $timestamp . ')';
    }

    /**
     * Formatiert Diagnosewerte kompakt fuer Formularlisten.
     */
    private function FormatDiagnosticValue(mixed $value): string
    {
        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (\is_array($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $value = \is_string($encoded) ? $encoded : '';
        }

        $text = (string) $value;
        return \strlen($text) > 240 ? \substr($text, 0, 237) . '...' : $text;
    }
}

