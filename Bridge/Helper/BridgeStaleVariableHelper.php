<?php

declare(strict_types=1);

/**
 * Bindet die Verwaltung veralteter Variablen in das Bridge-Formular ein.
 */
trait BridgeStaleVariableHelper
{
    /**
     * Scans for stale Zigbee2MQTT variables and refreshes the bridge form lists.
     */
    private function ScanStaleVariablesFromForm(): void
    {
        $scan = \Zigbee2MQTT\Maintenance\StaleVariableCleanupHelper::Scan($this->GetStaleVariableCleanupOptions());
        $this->WriteAttributeArray(self::ATTRIBUTE_STALE_VARIABLE_SCAN, $scan);
        $this->UpdateStaleVariableFormLists($scan);
    }

    /**
     * Selects an owning instance from the central overview.
     */
    private function SelectStaleVariableMaintenanceInstanceFromForm(mixed $value): bool
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

        $this->TryUpdateFormField('StaleVariableOpenInstance', 'objectID', $instanceID);
        $this->TryUpdateFormField('StaleVariableOpenInstance', 'visible', true);

        return true;
    }

    /**
     * Returns cleanup options used by the bridge UI.
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
     * Returns Device and Group instances owned by this bridge's MQTT system.
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
     * Returns the last stored stale variable scan result.
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
     * Builds the status caption for the stale variable maintenance area.
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
     * Builds the compact read-only bridge overview grouped by owning instance.
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
     * Refreshes all stale variable maintenance form fields.
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
