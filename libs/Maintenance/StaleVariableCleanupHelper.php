<?php

declare(strict_types=1);

namespace Zigbee2MQTT\Maintenance;

/**
 * Ermittelt Zigbee2MQTT-Variablen, die nicht mehr durch Exposes oder Payload-Daten belegt sind.
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
        'Z2M_ActionTransaction',
        'Z2M_ActionTransTime',
        'Z2M_XAxis',
        'Z2M_YAxis',
        'Z2M_ZAxis',
    ];

    private static ?array $referenceIndex = null;

    /**
     * Prüft alle Zigbee2MQTT-Geräte- und optional auch Gruppeninstanzen.
     *
     * @param array $options Prüfoptionen, die mit `DEFAULT_OPTIONS` zusammengeführt werden.
     *
     * @return array Prüfergebnis mit Löschkandidaten, Review-Kandidaten, Fehlern und Zählern.
     */
    public static function Scan(array $options = []): array
    {
        $hasInstanceFilter = array_key_exists('instanceIDs', $options);
        $options = array_replace(self::DEFAULT_OPTIONS, $options);
        self::$referenceIndex = null;
        $instanceFilter = array_values(array_unique(array_filter(array_map(
            'intval',
            \is_array($options['instanceIDs'] ?? null) ? $options['instanceIDs'] : []
        ))));

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
                if ($hasInstanceFilter && !\in_array((int) $instanceID, $instanceFilter, true)) {
                    continue;
                }

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
     * Prüft eine einzelne Zigbee2MQTT-Geräte- oder Gruppeninstanz.
     *
     * @param int   $instanceID Zu prüfende Instanz-ID.
     * @param array $options    Prüfoptionen, die mit `DEFAULT_OPTIONS` zusammengeführt werden.
     */
    public static function ScanInstance(int $instanceID, array $options = []): array
    {
        $options['includeGroups'] = true;
        $options['instanceIDs'] = [$instanceID];

        return self::Scan($options);
    }

    /**
     * Löscht ausgewählte Variablen, wenn sie im übergebenen Prüfergebnis als Kandidaten enthalten sind.
     *
     * @param array $scanResult  Ergebnis von `Scan()`.
     * @param array $variableIDs IDs der zu löschenden Variablenobjekte.
     * @param array $options     Schutzoptionen, die mit `DEFAULT_OPTIONS` zusammengeführt werden.
     *
     * @return array Ergebnis mit gelöschten und übersprungenen Einträgen.
     */
    public static function DeleteSelected(array $scanResult, array $variableIDs, array $options = []): array
    {
        $options = array_replace(self::DEFAULT_OPTIONS, $options);
        $ownerInstanceID = (int) ($options['ownerInstanceID'] ?? 0);
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
            if ($ownerInstanceID > 0) {
                if ((int) ($row['instanceID'] ?? 0) !== $ownerInstanceID) {
                    $skipped[] = ['variableID' => $variableID, 'reason' => 'Variable gehoert nicht zu dieser Instanz.'];
                    continue;
                }

                try {
                    $variable = IPS_GetObject($variableID);
                } catch (\Throwable) {
                    $variable = [];
                }

                if ((int) ($variable['ParentID'] ?? 0) !== $ownerInstanceID) {
                    $skipped[] = ['variableID' => $variableID, 'reason' => 'Variable ist kein direktes Kind dieser Instanz.'];
                    continue;
                }
            }

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
     * Löscht ausgewählte Kandidaten nur als direkte Kinder ihrer Besitzerinstanz.
     *
     * @param int   $ownerInstanceID Besitzerinstanz vom Typ Gerät oder Gruppe.
     * @param array $scanResult      Ergebnis von `ScanInstance()`.
     * @param array $variableIDs     IDs der zu löschenden Variablenobjekte.
     * @param array $options         Schutzoptionen, die mit `DEFAULT_OPTIONS` zusammengeführt werden.
     */
    public static function DeleteSelectedForInstance(
        int $ownerInstanceID,
        array $scanResult,
        array $variableIDs,
        array $options = []
    ): array {
        $options['ownerInstanceID'] = $ownerInstanceID;

        return self::DeleteSelected($scanResult, $variableIDs, $options);
    }

    /**
     * Formatiert die Schutzdetails eines Kandidaten für Formular- oder Textausgaben.
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
     * Prüft, ob ein Kandidat durch Archivierung oder Referenzen geschützt ist.
     */
    public static function IsProtected(array $row): bool
    {
        return (bool) ($row['archived'] ?? false) || !empty($row['references']);
    }

    /**
     * Liest und dekodiert den Debugexport einer Geräte- oder Gruppeninstanz.
     *
     * @return array Dekodierte Exportdaten oder ein Array mit dem Schlüssel `error`.
     */
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

    /**
     * Erstellt eine Wartungszeile mit Objekt-, Schutz- und Herkunftsinformationen.
     */
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

    /**
     * Ermittelt den letzten Änderungs- oder Aktualisierungszeitpunkt einer Variable.
     */
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

    /**
     * Leitet erwartete Variablen-Idents und Gerätefähigkeiten aus einem Debugexport ab.
     *
     * @return array{expected:array,capabilities:array{color_temp:bool,native_color:bool,supports_ota:bool}}
     */
    private static function BuildExpected(array $debugData): array
    {
        $expected = [];
        $capabilities = [
            'color_temp'   => false,
            'native_color' => false,
            'supports_ota' => (bool) ($debugData['SupportsOTA'] ?? false),
        ];

        foreach (($debugData['Exposes'] ?? []) as $expose) {
            if (\is_array($expose)) {
                self::CollectExposeFeature($expose, $expected, $capabilities);
            }
        }

        foreach (self::FlattenPayload((array) ($debugData['LastPayload'] ?? [])) as $ident => $_value) {
            if (self::IsOTAIdent((string) $ident)
                && (!($capabilities['supports_ota'] ?? false) || self::IsTransientOTAIdent((string) $ident))
            ) {
                continue;
            }

            self::AddExpected($expected, (string) $ident, 'payload');
        }

        foreach (self::FlattenPayload((array) ($debugData['LatestPayload'] ?? [])) as $ident => $_value) {
            if (self::IsOTAIdent((string) $ident)) {
                self::AddExpected($expected, (string) $ident, 'payload');
            }
        }

        if (($capabilities['color_temp'] ?? false) && !($capabilities['native_color'] ?? false)) {
            self::AddExpected($expected, 'color', 'derived');
        }

        self::AddExpected($expected, 'device_status', 'system');

        return [$expected, $capabilities];
    }

    /**
     * Sammelt rekursiv die aus einem Expose-Feature erwarteten Variablen.
     *
     * @param array  $feature      Expose-Feature von Zigbee2MQTT.
     * @param array  $expected     Referenz auf die gesammelten Idents und ihre Quellen.
     * @param array  $capabilities Referenz auf die erkannten Gerätefähigkeiten.
     * @param string $parentIdent  Ident des übergeordneten Composite-Features.
     */
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

    /**
     * Reduziert eine verschachtelte Payload auf die vom Modul verwendeten Variablen-Idents.
     *
     * @param string $prefix Ident-Präfix der aktuellen Verschachtelungsebene.
     */
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

    /**
     * Fügt einen normalisierten Ident mit seiner Herkunft zur Erwartungsliste hinzu.
     */
    private static function AddExpected(array &$expected, string $ident, string $source): void
    {
        $ident = self::NormalizeIdent($ident);
        if ($ident === '') {
            return;
        }

        $expected[$ident] ??= [];
        $expected[$ident][$source] = true;
    }

    /**
     * Prüft, ob ein Ident unabhängig von Exposes und Payload immer erhalten bleiben muss.
     */
    private static function IsAlwaysKeepIdent(string $ident): bool
    {
        return \in_array($ident, self::ALWAYS_KEEP_IDENTS, true);
    }

    /**
     * Prüft, ob ein Ident zum OTA-Variablenbaum gehört.
     */
    private static function IsOTAIdent(string $ident): bool
    {
        return $ident === 'update' || str_starts_with($ident, 'update__');
    }

    /**
     * Prüft, ob eine OTA-Variable nur während eines laufenden Updates benötigt wird.
     */
    private static function IsTransientOTAIdent(string $ident): bool
    {
        return \in_array($ident, ['update__progress', 'update__remaining'], true);
    }

    /**
     * Erkennt ein von Zigbee2MQTT geliefertes Farb-Composite.
     */
    private static function IsColorComposite(array $feature): bool
    {
        $name = strtolower((string) ($feature['name'] ?? ''));
        return (($feature['type'] ?? '') === 'composite'
                && \in_array($name, ['color_xy', 'color_hs', 'color_rgb'], true)
                && isset($feature['features'])
                && \is_array($feature['features']))
            || isset($feature['color_mode']);
    }

    /**
     * Erkennt einen gewöhnlichen Composite-Container, der rekursiv aufgelöst werden soll.
     */
    private static function IsCompositeContainer(array $feature): bool
    {
        return ($feature['type'] ?? '') === 'composite'
            && isset($feature['features'])
            && \is_array($feature['features'])
            && !self::IsColorComposite($feature);
    }

    /**
     * Normalisiert einen Zigbee2MQTT-Propertynamen für die Verwendung als Symcon-Ident.
     */
    private static function NormalizeIdent(string $ident): string
    {
        return str_replace('&', '_and_', $ident);
    }

    /**
     * Liefert den vollständigen Symcon-Pfad oder ersatzweise den Objektnamen.
     */
    private static function GetObjectPath(int $objectID): string
    {
        if (\function_exists('IPS_GetLocation')) {
            return IPS_GetLocation($objectID);
        }

        return IPS_GetName($objectID);
    }

    /**
     * Liefert den relativen Kategoriepfad einer Variable innerhalb ihrer Modulinstanz.
     */
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

    /**
     * Ermittelt die erste verfügbare Archiv-Control-Instanz.
     *
     * @return int Instanz-ID oder `0`, wenn kein Archiv verfügbar ist.
     */
    private static function GetArchiveID(): int
    {
        if (!\function_exists('IPS_GetInstanceListByModuleID')) {
            return 0;
        }

        $archiveIDs = IPS_GetInstanceListByModuleID(self::ARCHIVE_MODULE_ID);
        return (int) ($archiveIDs[0] ?? 0);
    }

    /**
     * Prüft, ob die Protokollierung einer Variable im angegebenen Archiv aktiv ist.
     */
    private static function IsArchived(int $variableID, int $archiveID): bool
    {
        if ($archiveID <= 0 || !\function_exists('AC_GetLoggingStatus')) {
            return false;
        }

        return (bool) @AC_GetLoggingStatus($archiveID, $variableID);
    }

    /**
     * Liefert alle Symcon-Objekte, die das angegebene Objekt referenzieren.
     *
     * Der hierfür benötigte Referenzindex wird einmalig aufgebaut und wiederverwendet.
     *
     * @return array<int,int> IDs der referenzierenden Objekte.
     */
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
