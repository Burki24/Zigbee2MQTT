<?php

declare(strict_types=1);

namespace Zigbee2MQTT\Maintenance;

require_once __DIR__ . '/StaleVariableCleanupHelper.php';

/**
 * Adds owner-scoped stale-variable maintenance to Device and Group instances.
 */
trait VariableMaintenanceHelper
{
    private const ATTRIBUTE_LOCAL_STALE_VARIABLE_SCAN = 'LocalStaleVariableScan';
    private const ATTRIBUTE_PENDING_LOCAL_STALE_VARIABLE_DELETE = 'PendingLocalStaleVariableDelete';

    /**
     * Registers attributes used by the local maintenance workflow.
     */
    protected function InitializeLocalVariableMaintenance(): void
    {
        $this->RegisterAttributeArray(self::ATTRIBUTE_LOCAL_STALE_VARIABLE_SCAN, []);
        $this->RegisterAttributeArray(self::ATTRIBUTE_PENDING_LOCAL_STALE_VARIABLE_DELETE, []);
    }

    /**
     * Adds and populates the local variable-maintenance controls.
     */
    protected function PrepareLocalVariableMaintenanceForm(array $form): array
    {
        $form['actions'] ??= [];
        $scan = $this->ReadLocalStaleVariableScan();
        $panel = $this->BuildLocalVariableMaintenancePanel($scan);

        if (!$this->AppendLocalVariableMaintenancePanel($form['actions'], $panel)) {
            $form['actions'][] = $panel;
        }

        $form['actions'][] = $this->BuildLocalStaleVariableDeleteWarning();
        $form['actions'][] = $this->BuildLocalStaleVariableMessage();

        return $form;
    }

    /**
     * Handles local maintenance actions and returns null for unrelated actions.
     */
    protected function HandleLocalVariableMaintenanceAction(string $ident, mixed $value): ?bool
    {
        return match ($ident) {
            'ScanLocalStaleVariables'         => $this->ScanLocalStaleVariablesFromForm(),
            'RequestDeleteLocalStaleVariable' => $this->RequestDeleteLocalStaleVariableFromForm($value),
            'ConfirmDeleteLocalStaleVariable' => $this->ConfirmPendingLocalStaleVariableDelete(),
            default                           => null,
        };
    }

    /**
     * Scans only variables owned by this instance.
     */
    private function ScanLocalStaleVariablesFromForm(): bool
    {
        $scan = StaleVariableCleanupHelper::ScanInstance($this->InstanceID, $this->GetLocalStaleVariableCleanupOptions());
        $this->WriteAttributeArray(self::ATTRIBUTE_LOCAL_STALE_VARIABLE_SCAN, $scan);
        $this->UpdateLocalStaleVariableFormLists($scan);

        return true;
    }

    /**
     * Opens the confirmation popup for an owner-scoped deletion.
     */
    private function RequestDeleteLocalStaleVariableFromForm(mixed $value): bool
    {
        $selection = \is_string($value) ? json_decode($value, true) : $value;
        $variableID = (int) ((\is_array($selection) ? $selection['variable_id'] ?? 0 : $selection) ?? 0);
        $row = $this->FindLocalStaleVariableCandidate($variableID);

        if ($row === null) {
            $this->ShowLocalStaleVariableMessage(
                'Variable cannot be deleted.',
                'The selected variable is no clear deletion candidate from the last scan.'
            );
            return false;
        }

        if (StaleVariableCleanupHelper::IsProtected($row)) {
            $this->ShowLocalStaleVariableMessage(
                'Variable is protected.',
                'Archived or referenced variables are not deleted.'
            );
            return false;
        }

        $this->WriteAttributeArray(self::ATTRIBUTE_PENDING_LOCAL_STALE_VARIABLE_DELETE, $row);
        $this->UpdateFormField(
            'LocalStaleVariableDeleteWarningText',
            'caption',
            sprintf(
                $this->Translate('Delete variable %s (%s)? This cannot be undone.'),
                '#' . (int) $row['variableID'],
                (string) $row['ident']
            )
        );
        $this->UpdateFormField('LocalStaleVariableDeleteWarning', 'visible', true);

        return true;
    }

    /**
     * Deletes the pending candidate after revalidating ownership and protection.
     */
    private function ConfirmPendingLocalStaleVariableDelete(): bool
    {
        $pending = $this->ReadAttributeArray(self::ATTRIBUTE_PENDING_LOCAL_STALE_VARIABLE_DELETE);
        $variableID = (int) ($pending['variableID'] ?? 0);
        $this->WriteAttributeArray(self::ATTRIBUTE_PENDING_LOCAL_STALE_VARIABLE_DELETE, []);
        $this->UpdateFormField('LocalStaleVariableDeleteWarning', 'visible', false);

        $scan = StaleVariableCleanupHelper::ScanInstance($this->InstanceID, $this->GetLocalStaleVariableCleanupOptions());
        $result = StaleVariableCleanupHelper::DeleteSelectedForInstance(
            $this->InstanceID,
            $scan,
            [$variableID],
            $this->GetLocalStaleVariableCleanupOptions()
        );
        $updatedScan = StaleVariableCleanupHelper::ScanInstance($this->InstanceID, $this->GetLocalStaleVariableCleanupOptions());
        $this->WriteAttributeArray(self::ATTRIBUTE_LOCAL_STALE_VARIABLE_SCAN, $updatedScan);
        $this->UpdateLocalStaleVariableFormLists($updatedScan);

        if (($result['deleted'] ?? []) !== []) {
            $this->ShowLocalStaleVariableMessage('Variable deleted.', 'The selected variable was deleted successfully.');
            return true;
        }

        $reason = (string) ($result['skipped'][0]['reason'] ?? 'The selected variable could not be deleted.');
        $this->ShowLocalStaleVariableMessage('Variable was not deleted.', $reason);

        return false;
    }

    /**
     * Returns the protection options used by local maintenance.
     */
    private function GetLocalStaleVariableCleanupOptions(): array
    {
        return [
            'includeGroups'              => true,
            'showPayloadOnlyReview'      => true,
            'protectArchivedVariables'   => true,
            'protectReferencedVariables' => true,
        ];
    }

    /**
     * Returns the stored owner-scoped scan result.
     */
    private function ReadLocalStaleVariableScan(): array
    {
        $scan = $this->ReadAttributeArray(self::ATTRIBUTE_LOCAL_STALE_VARIABLE_SCAN);

        return [
            'instanceCount'    => (int) ($scan['instanceCount'] ?? 0),
            'keptCount'        => (int) ($scan['keptCount'] ?? 0),
            'clearCandidates'  => \is_array($scan['clearCandidates'] ?? null) ? $scan['clearCandidates'] : [],
            'reviewCandidates' => \is_array($scan['reviewCandidates'] ?? null) ? $scan['reviewCandidates'] : [],
            'errors'           => \is_array($scan['errors'] ?? null) ? $scan['errors'] : [],
        ];
    }

    /**
     * Builds the local maintenance panel.
     */
    private function BuildLocalVariableMaintenancePanel(array $scan): array
    {
        return [
            'type'     => 'ExpansionPanel',
            'name'     => 'LocalVariableMaintenance',
            'caption'  => $this->Translate('Variable maintenance'),
            'expanded' => false,
            'items'    => [
                [
                    'type'    => 'Label',
                    'caption' => $this->Translate('Checks only variables owned by this instance. Archived or referenced variables are protected.'),
                ],
                [
                    'type'  => 'RowLayout',
                    'items' => [
                        [
                            'type'    => 'Button',
                            'caption' => $this->Translate('Scan stale variables'),
                            'onClick' => "IPS_RequestAction(\$id, 'ScanLocalStaleVariables', true);",
                        ],
                        [
                            'type'    => 'Label',
                            'name'    => 'LocalStaleVariableStatus',
                            'caption' => $this->BuildLocalStaleVariableStatusCaption($scan),
                        ],
                    ],
                ],
                $this->BuildPresentationMigrationLogList(),
                $this->BuildLocalStaleVariableCandidateList('LocalStaleVariableClearCandidates', 'Clear deletion candidates', $scan['clearCandidates'], true),
                $this->BuildLocalStaleVariableCandidateList('LocalStaleVariableReviewCandidates', 'Review candidates', $scan['reviewCandidates'], false),
                [
                    'type'                        => 'List',
                    'name'                        => 'LocalStaleVariableErrors',
                    'caption'                     => $this->Translate('Scan hints'),
                    'add'                         => false,
                    'delete'                      => false,
                    'rowCount'                    => min(6, max(2, \count($scan['errors']) + 1)),
                    'loadValuesFromConfiguration' => false,
                    'columns'                     => [
                        ['caption' => $this->Translate('Message'), 'name' => 'message', 'width' => 'auto'],
                    ],
                    'values' => $this->BuildLocalStaleVariableErrorFormValues($scan),
                ],
            ],
        ];
    }

    /**
     * Builds a read-only list of variables whose legacy Z2M profile was replaced by a native presentation.
     */
    private function BuildPresentationMigrationLogList(): array
    {
        $rows = $this->ReadPresentationMigrationLogRows();

        return [
            'type'                        => 'List',
            'name'                        => 'PresentationMigrationLog',
            'caption'                     => $this->Translate('Presentation changes'),
            'add'                         => false,
            'delete'                      => false,
            'rowCount'                    => min(8, max(2, \count($rows) + 1)),
            'loadValuesFromConfiguration' => false,
            'columns'                     => [
                ['caption' => $this->Translate('Variable'), 'name' => 'variable', 'width' => 'auto', 'quickFilter' => true],
                ['caption' => $this->Translate('Ident'), 'name' => 'ident', 'width' => '180px', 'quickFilter' => true],
                ['caption' => $this->Translate('Previous profile'), 'name' => 'old_profile', 'width' => '220px', 'quickFilter' => true],
                ['caption' => $this->Translate('New presentation'), 'name' => 'new_presentation', 'width' => '160px', 'quickFilter' => true],
                ['caption' => $this->Translate('User override'), 'name' => 'custom_setting', 'width' => '120px', 'align' => 'center'],
                ['caption' => $this->Translate('Changed'), 'name' => 'changed', 'width' => '150px'],
            ],
            'values' => $this->BuildPresentationMigrationLogFormValues($rows),
        ];
    }

    /**
     * Reads and sorts the presentation migration log for this instance.
     */
    private function ReadPresentationMigrationLogRows(): array
    {
        $rows = array_values(array_filter(
            $this->ReadAttributeArray(self::ATTRIBUTE_PRESENTATION_MIGRATION_LOG),
            static fn (mixed $row): bool => \is_array($row)
        ));

        usort(
            $rows,
            static fn (array $left, array $right): int => (int) ($right['time'] ?? 0) <=> (int) ($left['time'] ?? 0)
        );

        return $rows;
    }

    /**
     * Builds form rows for the presentation migration log.
     */
    private function BuildPresentationMigrationLogFormValues(array $rows): array
    {
        $values = [];
        foreach ($rows as $row) {
            $values[] = [
                'variable'         => '#' . (int) ($row['variableID'] ?? 0) . ' ' . (string) ($row['variable'] ?? ''),
                'ident'            => (string) ($row['ident'] ?? ''),
                'old_profile'      => (string) ($row['oldProfile'] ?? ''),
                'new_presentation' => (string) ($row['newPresentation'] ?? ''),
                'custom_setting'   => $this->Translate(($row['customSetting'] ?? false) ? 'Yes' : 'No'),
                'changed'          => $this->FormatLocalStaleVariableTimestamp((int) ($row['time'] ?? 0)),
            ];
        }

        return $values;
    }

    /**
     * Builds a local candidate list.
     */
    private function BuildLocalStaleVariableCandidateList(string $name, string $caption, array $rows, bool $withAction): array
    {
        $columns = [
            ['caption' => $this->Translate('Variable'), 'name' => 'variable', 'width' => 'auto', 'quickFilter' => true],
            ['caption' => $this->Translate('Ident'), 'name' => 'ident', 'width' => '180px', 'quickFilter' => true],
            ['caption' => $this->Translate('Archived'), 'name' => 'archived', 'width' => '90px', 'align' => 'center'],
            ['caption' => $this->Translate('Last written'), 'name' => 'last_update', 'width' => '150px'],
            ['caption' => $this->Translate('Reason'), 'name' => 'reason', 'width' => '240px'],
            ['caption' => $this->Translate('Protection'), 'name' => 'protection', 'width' => '160px'],
        ];
        if ($withAction) {
            $columns[] = [
                'caption' => $this->Translate('Action'),
                'name'    => 'action',
                'width'   => '100px',
                'align'   => 'center',
                'onClick' => "IPS_RequestAction(\$id, 'RequestDeleteLocalStaleVariable', json_encode(['variable_id' => \${$name}['variable_id']]));",
            ];
        }

        return [
            'type'                        => 'List',
            'name'                        => $name,
            'caption'                     => $this->Translate($caption),
            'add'                         => false,
            'delete'                      => false,
            'rowCount'                    => min(10, max(3, \count($rows) + 1)),
            'loadValuesFromConfiguration' => false,
            'columns'                     => $columns,
            'values'                      => $this->BuildLocalStaleVariableCandidateFormValues($rows, $withAction),
        ];
    }

    /**
     * Builds rows for a local candidate list.
     */
    private function BuildLocalStaleVariableCandidateFormValues(array $rows, bool $withAction): array
    {
        $values = [];
        foreach ($rows as $row) {
            if (!\is_array($row)) {
                continue;
            }

            $protected = StaleVariableCleanupHelper::IsProtected($row);
            $values[] = [
                'variable_id' => (int) ($row['variableID'] ?? 0),
                'variable'    => '#' . (int) ($row['variableID'] ?? 0) . ' ' . (string) ($row['name'] ?? ''),
                'ident'       => (string) ($row['ident'] ?? ''),
                'archived'    => $this->Translate(($row['archived'] ?? false) ? 'Yes' : 'No'),
                'last_update' => $this->FormatLocalStaleVariableTimestamp((int) ($row['lastUpdated'] ?? 0)),
                'reason'      => (string) ($row['reason'] ?? ''),
                'protection'  => StaleVariableCleanupHelper::FormatProtection($row),
                'action'      => $withAction ? ($protected ? $this->Translate('Protected') : $this->Translate('Delete')) : '',
            ];
        }

        return $values;
    }

    /**
     * Builds rows for local scan hints.
     */
    private function BuildLocalStaleVariableErrorFormValues(array $scan): array
    {
        return array_map(
            static fn (array $error): array => ['message' => (string) ($error['error'] ?? '')],
            $scan['errors'] ?? []
        );
    }

    /**
     * Builds the local scan status caption.
     */
    private function BuildLocalStaleVariableStatusCaption(array $scan): string
    {
        if (($scan['instanceCount'] ?? 0) === 0
            && ($scan['clearCandidates'] ?? []) === []
            && ($scan['reviewCandidates'] ?? []) === []
            && ($scan['errors'] ?? []) === []
        ) {
            return $this->Translate('No scan has been run yet.');
        }

        return sprintf(
            $this->Translate('Clear candidates: %d, review candidates: %d, scan hints: %d'),
            \count($scan['clearCandidates'] ?? []),
            \count($scan['reviewCandidates'] ?? []),
            \count($scan['errors'] ?? [])
        );
    }

    /**
     * Refreshes local maintenance form fields.
     */
    private function UpdateLocalStaleVariableFormLists(array $scan): void
    {
        $this->UpdateFormField('LocalStaleVariableStatus', 'caption', $this->BuildLocalStaleVariableStatusCaption($scan));
        $this->UpdateFormField('LocalStaleVariableClearCandidates', 'values', json_encode($this->BuildLocalStaleVariableCandidateFormValues($scan['clearCandidates'] ?? [], true)));
        $this->UpdateFormField('LocalStaleVariableClearCandidates', 'rowCount', min(10, max(3, \count($scan['clearCandidates'] ?? []) + 1)));
        $this->UpdateFormField('LocalStaleVariableReviewCandidates', 'values', json_encode($this->BuildLocalStaleVariableCandidateFormValues($scan['reviewCandidates'] ?? [], false)));
        $this->UpdateFormField('LocalStaleVariableReviewCandidates', 'rowCount', min(10, max(3, \count($scan['reviewCandidates'] ?? []) + 1)));
        $this->UpdateFormField('LocalStaleVariableErrors', 'values', json_encode($this->BuildLocalStaleVariableErrorFormValues($scan)));
        $this->UpdateFormField('LocalStaleVariableErrors', 'rowCount', min(6, max(2, \count($scan['errors'] ?? []) + 1)));
    }

    /**
     * Finds a clear local candidate.
     */
    private function FindLocalStaleVariableCandidate(int $variableID): ?array
    {
        foreach ($this->ReadLocalStaleVariableScan()['clearCandidates'] as $row) {
            if ((int) ($row['variableID'] ?? 0) === $variableID
                && (int) ($row['instanceID'] ?? 0) === $this->InstanceID
            ) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Shows a local maintenance result message.
     */
    private function ShowLocalStaleVariableMessage(string $title, string $message): void
    {
        $this->UpdateFormField('LocalStaleVariableMessageTitle', 'caption', $this->Translate($title));
        $this->UpdateFormField('LocalStaleVariableMessageText', 'caption', $this->Translate($message));
        $this->UpdateFormField('LocalStaleVariableMessage', 'visible', true);
    }

    /**
     * Builds the local delete confirmation popup.
     */
    private function BuildLocalStaleVariableDeleteWarning(): array
    {
        return [
            'name'    => 'LocalStaleVariableDeleteWarning',
            'type'    => 'PopupAlert',
            'visible' => false,
            'popup'   => [
                'closeCaption' => $this->Translate('Cancel'),
                'buttons'      => [[
                    'caption' => $this->Translate('Delete variable'),
                    'onClick' => "IPS_RequestAction(\$id, 'ConfirmDeleteLocalStaleVariable', true);",
                ]],
                'items' => [
                    ['type' => 'Label', 'bold' => true, 'caption' => $this->Translate('Delete variable?')],
                    [
                        'name'    => 'LocalStaleVariableDeleteWarningText',
                        'type'    => 'Label',
                        'caption' => $this->Translate('The selected variable will be deleted.'),
                    ],
                ],
            ],
        ];
    }

    /**
     * Builds the local result popup.
     */
    private function BuildLocalStaleVariableMessage(): array
    {
        return [
            'name'    => 'LocalStaleVariableMessage',
            'type'    => 'PopupAlert',
            'visible' => false,
            'popup'   => [
                'closeCaption' => $this->Translate('Close'),
                'items'        => [
                    ['name' => 'LocalStaleVariableMessageTitle', 'type' => 'Label', 'bold' => true, 'caption' => ''],
                    ['name' => 'LocalStaleVariableMessageText', 'type' => 'Label', 'caption' => ''],
                ],
            ],
        ];
    }

    /**
     * Inserts the local panel into the existing expert tools section.
     *
     * Device instances place it directly after the advanced removal controls.
     * Other instances append it to the expert tools section.
     */
    private function AppendLocalVariableMaintenancePanel(array &$items, array $panel): bool
    {
        foreach ($items as &$item) {
            if (!\is_array($item)) {
                continue;
            }

            if (($item['type'] ?? '') === 'ExpansionPanel'
                && ($item['caption'] ?? '') === 'Expert tools'
                && isset($item['items'])
                && \is_array($item['items'])
            ) {
                $insertAt = \count($item['items']);
                foreach ($item['items'] as $index => $expertItem) {
                    if (($expertItem['name'] ?? '') === 'AdvancedDeviceRemovalSettings') {
                        $insertAt = $index + 1;
                        break;
                    }
                }

                array_splice($item['items'], $insertAt, 0, [$panel]);
                return true;
            }

            foreach (['items', 'actions', 'popup'] as $childKey) {
                if (isset($item[$childKey]) && \is_array($item[$childKey])
                    && $this->AppendLocalVariableMaintenancePanel($item[$childKey], $panel)
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Formats a variable update timestamp.
     */
    private function FormatLocalStaleVariableTimestamp(int $timestamp): string
    {
        return $timestamp > 0 ? date('d.m.Y H:i:s', $timestamp) : '-';
    }
}
