<?php

declare(strict_types=1);

namespace Zigbee2MQTT\Maintenance;

/**
 * Scans Zigbee2MQTT instances for variables that are no longer backed by exposes or payload data.
 */
final class StaleVariableCleanupHelper
{
    public const DEFAULT_OPTIONS = [
        'includeGroups'              => true,
        'showPayloadOnlyReview'      => true,
        'protectArchivedVariables'   => true,
        'protectReferencedVariables' => true,
    ];

    private const DEVICE_MODULE_ID = '{E5BB36C6-A70B-EB23-3716-9151A09AC8A2}';
    private const GROUP_MODULE_ID = '{11BF3773-E940-469B-9DD7-FB9ACD7199A2}';
    private const ARCHIVE_MODULE_ID = '{43192F0B-135B-4CE7-A0A7-1475603F3060}';
    private const SKIP_COMPOSITES = ['device', 'endpoints', 'options'];
    private const ALWAYS_KEEP_IDENTS = [
        'device_status',
        'last_seen',
        'update',
        'Z2M_ActionTransaction',
        'Z2M_ActionTransTime',
        'Z2M_XAxis',
        'Z2M_YAxis',
        'Z2M_ZAxis',
    ];
    private const ALWAYS_KEEP_IDENT_PREFIXES = [
        'update__',
    ];

    private static ?array $referenceIndex = null;

    /**
     * Scans all Zigbee2MQTT device and optionally group instances.
     *
     * @param array $options Scan options, merged with DEFAULT_OPTIONS.
     *
     * @return array Scan result with clearCandidates, reviewCandidates, errors and counters.
     */
    public static function Scan(array $options = []): array
    {
        $options = array_replace(self::DEFAULT_OPTIONS, $options);
        self::$referenceIndex = null;

        if (!\function_exists('IPS_GetInstanceListByModuleID') || !\function_exists('Z2M_UIExportDebugData')) {
            return [
                'instanceCount'    => 0,
                'keptCount'        => 0,
                'clearCandidates'  => [],
                'reviewCandidates' => [],
                'errors'           => [[
                    'instanceID' => 0,
                    'path'       => '-',
                    'error'      => 'Dieses Script muss in IP-Symcon mit installiertem Zigbee2MQTT-Modul ausgefuehrt werden.',
                ]],
            ];
        }

        $moduleIDs = [
            'Device' => self::DEVICE_MODULE_ID,
            'Group'  => self::GROUP_MODULE_ID,
        ];
        if (!(bool) $options['includeGroups']) {
            unset($moduleIDs['Group']);
        }

        $clearCandidates = [];
        $reviewCandidates = [];
        $keptCount = 0;
        $instanceCount = 0;
        $errors = [];
        $variableObjectType = \defined('OBJECTTYPE_VARIABLE') ? OBJECTTYPE_VARIABLE : 2;
        $archiveID = self::GetArchiveID();

        foreach ($moduleIDs as $moduleType => $moduleID) {
            foreach (IPS_GetInstanceListByModuleID($moduleID) as $instanceID) {
                ++$instanceCount;
                $debugData = self::DecodeExportData((int) $instanceID);
                if (isset($debugData['error'])) {
                    $errors[] = [
                        'instanceID' => (int) $instanceID,
                        'path'       => self::GetObjectPath((int) $instanceID),
                        'error'      => (string) $debugData['error'],
                    ];
                    continue;
                }

                [$expected, $capabilities] = self::BuildExpected($debugData);
                $hasExposeData = \is_array($debugData['Exposes'] ?? null) && $debugData['Exposes'] !== [];
                $hasPayloadData = \is_array($debugData['LastPayload'] ?? null) && $debugData['LastPayload'] !== [];

                if (!$hasExposeData && !$hasPayloadData) {
                    $errors[] = [
                        'instanceID' => (int) $instanceID,
                        'path'       => self::GetObjectPath((int) $instanceID),
                        'error'      => 'Keine Expose- oder Payload-Daten vorhanden, Instanz wurde uebersprungen.',
                    ];
                    continue;
                }

                foreach (IPS_GetChildrenIDs((int) $instanceID) as $childID) {
                    $object = IPS_GetObject((int) $childID);
                    if (($object['ObjectType'] ?? -1) !== $variableObjectType) {
                        continue;
                    }

                    $ident = (string) ($object['ObjectIdent'] ?? '');
                    if ($ident === '' || self::IsAlwaysKeepIdent($ident)) {
                        ++$keptCount;
                        continue;
                    }

                    $sources = array_keys($expected[$ident] ?? []);
                    $inExpose = \in_array('expose', $sources, true)
                        || \in_array('derived', $sources, true)
                        || \in_array('system', $sources, true);
                    $inPayload = \in_array('payload', $sources, true);
                    $isUnsupportedColorTemperatureDerived = \in_array($ident, ['color_temp_kelvin', 'color_temp_presets'], true)
                        && !($capabilities['color_temp'] ?? false);
                    $isUnsupportedDerivedWhiteColor = $ident === 'color'
                        && !($capabilities['native_color'] ?? false)
                        && !($capabilities['color_temp'] ?? false)
                        && !$inPayload;

                    $row = self::BuildCandidateRow((int) $instanceID, (int) $childID, $moduleType, $ident, $sources, $archiveID);

                    if ($isUnsupportedColorTemperatureDerived) {
                        $row['reason'] = 'Abgeleitete Farbtemperaturvariable ohne aktuelles color_temp-Expose';
                        $clearCandidates[] = $row;
                        continue;
                    }

                    if ($isUnsupportedDerivedWhiteColor) {
                        $row['reason'] = 'Abgeleitete Weiss-Farbvariable ohne aktuelles Farb- oder Farbtemperatur-Expose';
                        $clearCandidates[] = $row;
                        continue;
                    }

                    if (!$inExpose && !$inPayload) {
                        $row['reason'] = 'Nicht in aktuellen Exposes und nicht im letzten Payload';
                        $clearCandidates[] = $row;
                        continue;
                    }

                    if ((bool) $options['showPayloadOnlyReview'] && !$inExpose && $inPayload) {
                        $row['reason'] = 'Nur im letzten Payload vorhanden, nicht in aktuellen Exposes';
                        $reviewCandidates[] = $row;
                        continue;
                    }

                    ++$keptCount;
                }
            }
        }

        return [
            'instanceCount'    => $instanceCount,
            'keptCount'        => $keptCount,
            'clearCandidates'  => $clearCandidates,
            'reviewCandidates' => $reviewCandidates,
            'errors'           => $errors,
        ];
    }

    /**
     * Deletes selected variables if they are candidates in the provided scan result.
     *
     * @param array $scanResult Result from Scan().
     * @param array $variableIDs Variable object IDs to delete.
     * @param array $options Protection options, merged with DEFAULT_OPTIONS.
     *
     * @return array Result with deleted and skipped entries.
     */
    public static function DeleteSelected(array $scanResult, array $variableIDs, array $options = []): array
    {
        $options = array_replace(self::DEFAULT_OPTIONS, $options);
        $candidateByID = [];
        foreach (array_merge($scanResult['clearCandidates'] ?? [], $scanResult['reviewCandidates'] ?? []) as $row) {
            if (isset($row['variableID'])) {
                $candidateByID[(int) $row['variableID']] = $row;
            }
        }

        $deleted = [];
        $skipped = [];
        foreach (array_values(array_unique(array_filter(array_map('intval', $variableIDs)))) as $variableID) {
            if (!isset($candidateByID[$variableID])) {
                $skipped[] = ['variableID' => $variableID, 'reason' => 'ID ist kein Kandidat aus diesem Lauf.'];
                continue;
            }

            $row = $candidateByID[$variableID];
            if ((bool) $options['protectArchivedVariables'] && ($row['archived'] ?? false)) {
                $skipped[] = ['variableID' => $variableID, 'reason' => 'Variable ist archiviert.'];
                continue;
            }

            if ((bool) $options['protectReferencedVariables'] && \count($row['references'] ?? []) > 0) {
                $skipped[] = ['variableID' => $variableID, 'reason' => 'Variable hat Referenzen: ' . implode(', ', $row['references'])];
                continue;
            }

            if (!\function_exists('IPS_DeleteVariable')) {
                $skipped[] = ['variableID' => $variableID, 'reason' => 'IPS_DeleteVariable ist nicht verfuegbar.'];
                continue;
            }

            try {
                IPS_DeleteVariable($variableID);
                $deleted[] = $row;
            } catch (\Throwable $exception) {
                $skipped[] = ['variableID' => $variableID, 'reason' => $exception->getMessage()];
            }
        }

        return [
            'deleted' => $deleted,
            'skipped' => $skipped,
        ];
    }

    /**
     * Formats candidate protection details for UI or text output.
     */
    public static function FormatProtection(array $row): string
    {
        $protection = [];
        if ($row['archived'] ?? false) {
            $protection[] = 'archiviert';
        }
        if (!empty($row['references'])) {
            $protection[] = 'Referenzen: ' . implode(', ', $row['references']);
        }

        return $protection === [] ? 'nicht geschuetzt' : implode('; ', $protection);
    }

    /**
     * Returns true when the candidate is protected by archive or references.
     */
    public static function IsProtected(array $row): bool
    {
        return (bool) ($row['archived'] ?? false) || !empty($row['references']);
    }

    private static function DecodeExportData(int $instanceID): array
    {
        try {
            $raw = Z2M_UIExportDebugData($instanceID);
        } catch (\Throwable $exception) {
            return ['error' => $exception->getMessage()];
        }

        $prefix = 'data:application/json;base64,';
        if (str_starts_with($raw, $prefix)) {
            $raw = substr($raw, \strlen($prefix));
        }

        $json = base64_decode($raw, true);
        if ($json === false) {
            return ['error' => 'Debugdaten konnten nicht decodiert werden.'];
        }

        $data = json_decode($json, true);
        if (!\is_array($data)) {
            return ['error' => 'Debugdaten enthalten kein gueltiges JSON.'];
        }

        return $data;
    }

    private static function BuildCandidateRow(int $instanceID, int $variableID, string $moduleType, string $ident, array $sources, int $archiveID): array
    {
        $object = IPS_GetObject($variableID);
        $references = self::GetReferences($variableID);
        $row = [
            'moduleType'   => $moduleType,
            'instanceID'   => $instanceID,
            'instance'     => self::GetObjectPath($instanceID),
            'category'     => self::GetVariableCategoryPath($variableID),
            'variableID'   => $variableID,
            'ident'        => $ident,
            'name'         => (string) ($object['ObjectName'] ?? ''),
            'sources'      => $sources === [] ? '-' : implode(', ', $sources),
            'archived'     => self::IsArchived($variableID, $archiveID),
            'lastUpdated'  => self::GetVariableUpdatedTimestamp($variableID),
            'references'   => $references,
            'reason'       => '',
        ];
        $row['protected'] = self::IsProtected($row);
        $row['protection'] = self::FormatProtection($row);

        return $row;
    }

    private static function GetVariableUpdatedTimestamp(int $variableID): int
    {
        if (!\function_exists('IPS_GetVariable')) {
            return 0;
        }

        try {
            $variable = IPS_GetVariable($variableID);
        } catch (\Throwable) {
            return 0;
        }

        if (!\is_array($variable)) {
            return 0;
        }

        return (int) ($variable['VariableUpdated'] ?? ($variable['VariableChanged'] ?? 0));
    }

    private static function BuildExpected(array $debugData): array
    {
        $expected = [];
        $capabilities = [
            'color_temp'   => false,
            'native_color' => false,
        ];

        foreach (($debugData['Exposes'] ?? []) as $expose) {
            if (\is_array($expose)) {
                self::CollectExposeFeature($expose, $expected, $capabilities);
            }
        }

        foreach (self::FlattenPayload((array) ($debugData['LastPayload'] ?? [])) as $ident => $_value) {
            self::AddExpected($expected, (string) $ident, 'payload');
        }

        if (($capabilities['color_temp'] ?? false) && !($capabilities['native_color'] ?? false)) {
            self::AddExpected($expected, 'color', 'derived');
        }

        self::AddExpected($expected, 'device_status', 'system');

        return [$expected, $capabilities];
    }

    private static function CollectExposeFeature(array $feature, array &$expected, array &$capabilities, string $parentIdent = ''): void
    {
        if (self::IsColorComposite($feature)) {
            $name = strtolower((string) ($feature['name'] ?? ''));
            $capabilities['native_color'] = true;

            if ($name === 'color_hs') {
                self::AddExpected($expected, 'color_hs', 'expose');
            } elseif ($name === 'color_rgb') {
                self::AddExpected($expected, 'color_rgb', 'expose');
            } else {
                self::AddExpected($expected, 'color', 'expose');
            }

            return;
        }

        if (self::IsCompositeContainer($feature)) {
            $parent = self::NormalizeIdent((string) ($feature['property'] ?? $parentIdent));
            foreach ($feature['features'] as $subFeature) {
                if (!\is_array($subFeature)) {
                    continue;
                }

                $subProperty = self::NormalizeIdent((string) ($subFeature['property'] ?? ''));
                if ($parent !== '' && $subProperty !== '') {
                    $subFeature['property'] = $parent . '__' . $subProperty;
                }

                self::CollectExposeFeature($subFeature, $expected, $capabilities, $parent);
            }

            return;
        }

        $property = self::NormalizeIdent((string) ($feature['property'] ?? ''));
        if ($property !== '') {
            self::AddExpected($expected, $property, 'expose');

            if ($property === 'occupancy') {
                self::AddExpected($expected, 'no_occupancy_since', 'derived');
            }

            if ($property === 'color') {
                $capabilities['native_color'] = true;
            }

            if ($property === 'color_temp') {
                $capabilities['color_temp'] = true;
                self::AddExpected($expected, 'color_temp_kelvin', 'derived');
            }

            if (isset($feature['presets']) && \is_array($feature['presets']) && $feature['presets'] !== []) {
                self::AddExpected($expected, $property . '_presets', 'derived');
            }
        }

        if (!isset($feature['features']) || !\is_array($feature['features'])) {
            return;
        }

        foreach ($feature['features'] as $subFeature) {
            if (\is_array($subFeature)) {
                self::CollectExposeFeature($subFeature, $expected, $capabilities, $parentIdent);
            }
        }
    }

    private static function FlattenPayload(array $payload, string $prefix = ''): array
    {
        $result = [];

        foreach ($payload as $key => $value) {
            $key = (string) $key;
            if ($prefix === '' && \in_array($key, self::SKIP_COMPOSITES, true) && \is_array($value)) {
                continue;
            }

            if ($key === 'exposes') {
                continue;
            }

            if ($key === 'color' && \is_array($value)) {
                $result['color'] = $value;
                continue;
            }

            $newKey = $prefix !== '' ? $prefix . '__' . $key : $key;
            if (\is_array($value)) {
                $result += self::FlattenPayload($value, $newKey);
                continue;
            }

            $result[$newKey] = $value;
        }

        return $result;
    }

    private static function AddExpected(array &$expected, string $ident, string $source): void
    {
        $ident = self::NormalizeIdent($ident);
        if ($ident === '') {
            return;
        }

        $expected[$ident] ??= [];
        $expected[$ident][$source] = true;
    }

    private static function IsAlwaysKeepIdent(string $ident): bool
    {
        if (\in_array($ident, self::ALWAYS_KEEP_IDENTS, true)) {
            return true;
        }

        foreach (self::ALWAYS_KEEP_IDENT_PREFIXES as $prefix) {
            if (str_starts_with($ident, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private static function IsColorComposite(array $feature): bool
    {
        $name = strtolower((string) ($feature['name'] ?? ''));
        return (($feature['type'] ?? '') === 'composite'
                && \in_array($name, ['color_xy', 'color_hs', 'color_rgb'], true)
                && isset($feature['features'])
                && \is_array($feature['features']))
            || isset($feature['color_mode']);
    }

    private static function IsCompositeContainer(array $feature): bool
    {
        return ($feature['type'] ?? '') === 'composite'
            && isset($feature['features'])
            && \is_array($feature['features'])
            && !self::IsColorComposite($feature);
    }

    private static function NormalizeIdent(string $ident): string
    {
        return str_replace('&', '_and_', $ident);
    }

    private static function GetObjectPath(int $objectID): string
    {
        if (\function_exists('IPS_GetLocation')) {
            return IPS_GetLocation($objectID);
        }

        return IPS_GetName($objectID);
    }

    private static function GetVariableCategoryPath(int $variableID): string
    {
        $instanceObjectType = \defined('OBJECTTYPE_INSTANCE') ? OBJECTTYPE_INSTANCE : 1;
        $variable = IPS_GetObject($variableID);
        $parentID = (int) ($variable['ParentID'] ?? 0);
        if ($parentID <= 0) {
            return '-';
        }

        $parent = IPS_GetObject($parentID);
        if (($parent['ObjectType'] ?? -1) !== $instanceObjectType) {
            return self::GetObjectPath($parentID);
        }

        $categoryID = (int) ($parent['ParentID'] ?? 0);
        if ($categoryID <= 0) {
            return '-';
        }

        return self::GetObjectPath($categoryID);
    }

    private static function GetArchiveID(): int
    {
        if (!\function_exists('IPS_GetInstanceListByModuleID')) {
            return 0;
        }

        $archiveIDs = IPS_GetInstanceListByModuleID(self::ARCHIVE_MODULE_ID);
        return (int) ($archiveIDs[0] ?? 0);
    }

    private static function IsArchived(int $variableID, int $archiveID): bool
    {
        if ($archiveID <= 0 || !\function_exists('AC_GetLoggingStatus')) {
            return false;
        }

        return (bool) @AC_GetLoggingStatus($archiveID, $variableID);
    }

    private static function GetReferences(int $objectID): array
    {
        if (!\function_exists('IPS_GetObjectList') || !\function_exists('IPS_GetReferenceList')) {
            return [];
        }

        if (self::$referenceIndex === null) {
            self::$referenceIndex = [];
            foreach (IPS_GetObjectList() as $currentObjectID) {
                $references = @IPS_GetReferenceList((int) $currentObjectID);
                if (!\is_array($references)) {
                    continue;
                }

                foreach ($references as $referencedObjectID) {
                    self::$referenceIndex[(int) $referencedObjectID][] = (int) $currentObjectID;
                }
            }
        }

        return self::$referenceIndex[$objectID] ?? [];
    }
}
