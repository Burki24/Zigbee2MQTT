<?php

declare(strict_types=1);

/**
 * Bindet die Verwaltung veralteter Variablen in das Bridge-Formular ein.
 */
trait BridgeStaleVariableHelper
{
    /**
     * Sucht benutzerdefinierte Profile und Darstellungen in allen zugeordneten Instanzen.
     */
    private function ScanCustomProfilesFromForm(): void
    {
        $rows = [];
        foreach ($this->GetStaleVariableMaintenanceInstanceIDs() as $instanceID) {
            try {
                $instanceName = (string) IPS_GetLocation($instanceID);
                foreach (IPS_GetChildrenIDs($instanceID) as $variableID) {
                    $object = IPS_GetObject($variableID);
                    if (($object['ObjectType'] ?? -1) !== OBJECTTYPE_VARIABLE) {
                        continue;
                    }

                    $variable = IPS_GetVariable($variableID);
                    $customProfile = (string) ($variable['VariableCustomProfile'] ?? '');
                    $customPresentation = \is_array($variable['VariableCustomPresentation'] ?? null)
                        ? $variable['VariableCustomPresentation']
                        : [];
                    if ($customProfile === '' && $customPresentation === []) {
                        continue;
                    }

                    $rows[] = [
                        'instance'            => $instanceName,
                        'instance_id'         => $instanceID,
                        'variable'            => (string) ($object['ObjectName'] ?? ''),
                        'variable_id'         => (int) $variableID,
                        'ident'               => (string) ($object['ObjectIdent'] ?? ''),
                        'standard_profile'    => (string) ($variable['VariableProfile'] ?? ''),
                        'custom_profile'      => $customProfile,
                        'custom_presentation' => $this->FormatCustomPresentation($customPresentation),
                        'action'              => $this->Translate('Select'),
                    ];
                }
            } catch (\Throwable $exception) {
                $this->SendDebug(__FUNCTION__, sprintf('Instance #%d: %s', $instanceID, $exception->getMessage()), 0);
            }
        }

        usort($rows, static function (array $left, array $right): int
        {
            return strnatcasecmp(
                $left['instance'] . "\0" . $left['variable'],
                $right['instance'] . "\0" . $right['variable']
            );
        });

        $scan = ['scanned' => true, 'rows' => $rows];
        $this->WriteAttributeArray(self::ATTRIBUTE_CUSTOM_PROFILE_SCAN, $scan);
        $this->UpdateCustomProfileForm($scan);
    }

    /**
     * Liefert das zuletzt gespeicherte Ergebnis der Custom-Profil-Suche.
     */
    private function ReadCustomProfileScan(): array
    {
        $scan = $this->ReadAttributeArray(self::ATTRIBUTE_CUSTOM_PROFILE_SCAN);

        $rows = \is_array($scan['rows'] ?? null) ? $scan['rows'] : [];
        foreach ($rows as &$row) {
            if (\is_array($row)) {
                $row['action'] = $this->Translate('Select');
            }
        }
        unset($row);

        return [
            'scanned' => (bool) ($scan['scanned'] ?? false),
            'rows'    => $rows,
        ];
    }

    /**
     * Erstellt den Statustext der Custom-Profil-Suche.
     */
    private function BuildCustomProfileStatusCaption(?array $scan = null): string
    {
        $scan ??= $this->ReadCustomProfileScan();
        if (!($scan['scanned'] ?? false)) {
            return $this->Translate('No custom profile scan has been run yet.');
        }

        return sprintf($this->Translate('Variables with custom configuration: %d'), \count($scan['rows'] ?? []));
    }

    /**
     * Aktualisiert die Custom-Profil-Liste im geöffneten Bridge-Formular.
     */
    private function UpdateCustomProfileForm(array $scan): void
    {
        $rows = \is_array($scan['rows'] ?? null) ? $scan['rows'] : [];
        $this->TryUpdateFormField('CustomProfileStatus', 'caption', $this->BuildCustomProfileStatusCaption($scan));
        $this->TryUpdateFormField('CustomProfileList', 'values', json_encode($rows));
        $this->TryUpdateFormField('CustomProfileList', 'rowCount', min(15, max(3, \count($rows) + 1)));
        $this->TryUpdateFormField('CustomProfileOpenInstance', 'visible', false);
    }

    /**
     * Formatiert eine native Symcon-Darstellung kompakt für die Tabellenansicht.
     */
    private function FormatCustomPresentation(array $presentation): string
    {
        if ($presentation === []) {
            return '';
        }
        if (isset($presentation['PROFILE']) && \is_string($presentation['PROFILE'])) {
            return $presentation['PROFILE'];
        }

        return (string) json_encode($presentation, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Sucht veraltete Zigbee2MQTT-Variablen und aktualisiert die Listen des Bridge-Formulars.
     */
    private function ScanStaleVariablesFromForm(): void
    {
        $scan = \Zigbee2MQTT\Maintenance\StaleVariableCleanupHelper::Scan($this->GetStaleVariableCleanupOptions());
        $this->WriteAttributeArray(self::ATTRIBUTE_STALE_VARIABLE_SCAN, $scan);
        $this->UpdateStaleVariableFormLists($scan);
    }

    /**
     * Wählt eine Besitzerinstanz aus der zentralen Übersicht aus.
     */
    private function SelectStaleVariableMaintenanceInstanceFromForm(mixed $value): bool
    {
        return $this->SelectVariableMaintenanceInstanceFromForm($value, 'StaleVariableOpenInstance');
    }

    /**
     * Wählt die Besitzerinstanz einer Custom-Profil-Zeile aus.
     */
    private function SelectCustomProfileInstanceFromForm(mixed $value): bool
    {
        return $this->SelectVariableMaintenanceInstanceFromForm($value, 'CustomProfileOpenInstance');
    }

    /**
     * Prüft eine Instanzauswahl und blendet den zugehörigen Öffnen-Button ein.
     */
    private function SelectVariableMaintenanceInstanceFromForm(mixed $value, string $formField): bool
    {
        $selection = $this->DecodeBridgeFormPayload($value);
        $instanceID = (int) ($selection['instance_id'] ?? 0);
        if ($instanceID <= 0 || !\in_array($instanceID, $this->GetStaleVariableMaintenanceInstanceIDs(), true)) {
            return false;
        }

        try {
            $object = IPS_GetObject($instanceID);
        } catch (\Throwable) {
            return false;
        }

        if (($object['ObjectType'] ?? -1) !== OBJECTTYPE_INSTANCE) {
            return false;
        }

        $this->TryUpdateFormField($formField, 'objectID', $instanceID);
        $this->TryUpdateFormField($formField, 'visible', true);

        return true;
    }

    /**
     * Liefert die von der Bridge-Oberfläche verwendeten Bereinigungsoptionen.
     */
    private function GetStaleVariableCleanupOptions(): array
    {
        return [
            'includeGroups'              => true,
            'instanceIDs'                => $this->GetStaleVariableMaintenanceInstanceIDs(),
            'showPayloadOnlyReview'      => true,
            'protectArchivedVariables'   => true,
            'protectReferencedVariables' => true,
        ];
    }

    /**
     * Liefert die Geräte- und Gruppeninstanzen, die zum MQTT-System dieser Bridge gehören.
     */
    private function GetStaleVariableMaintenanceInstanceIDs(): array
    {
        $baseTopic = $this->ReadPropertyString(self::MQTT_BASE_TOPIC);
        try {
            $connectionID = (int) IPS_GetInstance($this->InstanceID)['ConnectionID'];
        } catch (\Throwable) {
            return [];
        }

        if ($baseTopic === '' || $connectionID <= 0) {
            return [];
        }

        $instanceIDs = [];
        foreach ([self::GUID_MODULE_DEVICE, self::GUID_MODULE_GROUP] as $moduleID) {
            foreach (IPS_GetInstanceListByModuleID($moduleID) as $instanceID) {
                if ((int) IPS_GetInstance($instanceID)['ConnectionID'] !== $connectionID
                    || @IPS_GetProperty($instanceID, self::MQTT_BASE_TOPIC) !== $baseTopic
                ) {
                    continue;
                }

                $instanceIDs[] = (int) $instanceID;
            }
        }

        sort($instanceIDs);

        return $instanceIDs;
    }

    /**
     * Liefert das zuletzt gespeicherte Ergebnis der Variablenprüfung.
     */
    private function ReadStaleVariableScan(): array
    {
        $scan = $this->ReadAttributeArray(self::ATTRIBUTE_STALE_VARIABLE_SCAN);
        return [
            'instanceCount'    => (int) ($scan['instanceCount'] ?? 0),
            'keptCount'        => (int) ($scan['keptCount'] ?? 0),
            'clearCandidates'  => \is_array($scan['clearCandidates'] ?? null) ? $scan['clearCandidates'] : [],
            'reviewCandidates' => \is_array($scan['reviewCandidates'] ?? null) ? $scan['reviewCandidates'] : [],
            'errors'           => \is_array($scan['errors'] ?? null) ? $scan['errors'] : [],
        ];
    }

    /**
     * Erstellt den Statustext für den Bereich der Variablenwartung.
     */
    private function BuildStaleVariableStatusCaption(): string
    {
        $scan = $this->ReadStaleVariableScan();
        if (($scan['instanceCount'] ?? 0) === 0
            && ($scan['clearCandidates'] ?? []) === []
            && ($scan['reviewCandidates'] ?? []) === []
            && ($scan['errors'] ?? []) === []
        ) {
            return $this->Translate('No scan has been run yet.');
        }

        return sprintf(
            $this->Translate('Checked instances: %d, clear candidates: %d, review candidates: %d'),
            $scan['instanceCount'],
            \count($scan['clearCandidates']),
            \count($scan['reviewCandidates'])
        );
    }

    /**
     * Erstellt die kompakte, nach Besitzerinstanz gruppierte Bridge-Übersicht.
     */
    private function BuildStaleVariableInstanceSummaryFormValues(?array $scan = null): array
    {
        $scan ??= $this->ReadStaleVariableScan();
        $instances = [];

        foreach ([
            'clearCandidates'  => 'clear_count',
            'reviewCandidates' => 'review_count',
        ] as $source => $counter) {
            foreach (($scan[$source] ?? []) as $row) {
                $instanceID = (int) ($row['instanceID'] ?? 0);
                if ($instanceID <= 0) {
                    continue;
                }

                $instances[$instanceID] ??= [
                    'instance_id'  => $instanceID,
                    'instance'     => (string) ($row['instance'] ?? ''),
                    'clear_count'  => 0,
                    'review_count' => 0,
                    'hint_count'   => 0,
                    'action'       => $this->Translate('Select'),
                ];
                ++$instances[$instanceID][$counter];
            }
        }

        foreach (($scan['errors'] ?? []) as $error) {
            $instanceID = (int) ($error['instanceID'] ?? 0);
            if ($instanceID <= 0) {
                continue;
            }

            $instances[$instanceID] ??= [
                'instance_id'  => $instanceID,
                'instance'     => (string) ($error['path'] ?? ''),
                'clear_count'  => 0,
                'review_count' => 0,
                'hint_count'   => 0,
                'action'       => $this->Translate('Select'),
            ];
            ++$instances[$instanceID]['hint_count'];
        }

        uasort(
            $instances,
            static fn (array $left, array $right): int => strnatcasecmp($left['instance'], $right['instance'])
        );

        return array_values($instances);
    }

    /**
     * Aktualisiert alle Formularfelder der Wartung veralteter Variablen.
     */
    private function UpdateStaleVariableFormLists(array $scan): void
    {
        $this->TryUpdateFormField('StaleVariableStatus', 'caption', $this->BuildStaleVariableStatusCaption());
        $summary = $this->BuildStaleVariableInstanceSummaryFormValues($scan);
        $this->TryUpdateFormField('StaleVariableInstanceSummary', 'values', json_encode($summary));
        $this->TryUpdateFormField('StaleVariableInstanceSummary', 'rowCount', min(12, max(3, \count($summary) + 1)));
        $this->TryUpdateFormField('StaleVariableOpenInstance', 'visible', false);
    }

}
