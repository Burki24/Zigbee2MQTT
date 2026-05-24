<?php

declare(strict_types=1);

/*
 * Zigbee2MQTT stale variable cleanup for IP-Symcon.
 *
 * Usage:
 * 1. Copy this script and SymconCleanupStaleVariables.config.json into the
 *    same IP-Symcon script directory, or adjust $configFile below once.
 * 2. Run the script unchanged and review the reported variable IDs.
 * 3. Enter selected variable object IDs in the JSON config file and set
 *    deleteMode plus confirmDeletion there.
 *
 * The default run is always a dry run. No variable is deleted unless delete
 * mode is explicitly enabled in the external JSON config.
 */

$configFile = __DIR__ . '/SymconCleanupStaleVariables.config.json';
$configFileStatus = 'nicht gefunden, Dry-Run-Defaults werden verwendet';
$config = [
    'deleteMode'                 => false,
    'confirmDeletion'            => '',
    'deleteVariableIDs'          => [],
    'deleteAllClearCandidates'   => false,
    'includeGroups'              => true,
    'showPayloadOnlyReview'      => true,
    'protectArchivedVariables'   => true,
    'protectReferencedVariables' => true,
];

if (is_file($configFile)) {
    $rawConfig = file_get_contents($configFile);
    $decodedConfig = $rawConfig === false ? null : json_decode($rawConfig, true);
    if (!is_array($decodedConfig)) {
        echo 'Konfigurationsdatei ist kein gueltiges JSON: ' . $configFile . "\n";
        return;
    }

    $config = array_replace($config, array_intersect_key($decodedConfig, $config));
    $configFileStatus = $configFile;
}

$deleteMode = (bool) $config['deleteMode'];
$deleteVariableIDs = array_values(array_unique(array_map(
    'intval',
    is_array($config['deleteVariableIDs']) ? $config['deleteVariableIDs'] : []
)));
$deleteAllClearCandidates = (bool) $config['deleteAllClearCandidates'];

$includeGroups = (bool) $config['includeGroups'];
$showPayloadOnlyReview = (bool) $config['showPayloadOnlyReview'];
$protectArchivedVariables = (bool) $config['protectArchivedVariables'];
$protectReferencedVariables = (bool) $config['protectReferencedVariables'];

if ($deleteMode && (string) $config['confirmDeletion'] !== 'DELETE') {
    echo "Loeschmodus wurde angefordert, aber confirmDeletion ist nicht \"DELETE\". Script laeuft sicherheitshalber im Dry-Run.\n";
    $deleteMode = false;
}

if (!function_exists('IPS_GetInstanceListByModuleID') || !function_exists('Z2M_UIExportDebugData')) {
    echo "Dieses Script muss in IP-Symcon mit installiertem Zigbee2MQTT-Modul ausgefuehrt werden.\n";
    return;
}

$moduleIDs = [
    'Device' => '{E5BB36C6-A70B-EB23-3716-9151A09AC8A2}',
    'Group'  => '{11BF3773-E940-469B-9DD7-FB9ACD7199A2}',
];

if (!$includeGroups) {
    unset($moduleIDs['Group']);
}

$variableObjectType = defined('OBJECTTYPE_VARIABLE') ? OBJECTTYPE_VARIABLE : 2;
$archiveModuleID = '{43192F0B-135B-4CE7-A0A7-1475603F3060}';
$skipComposites = ['device', 'endpoints', 'options'];
$alwaysKeepIdents = [
    'device_status',
    'Z2M_ActionTransaction',
    'Z2M_ActionTransTime',
    'Z2M_XAxis',
    'Z2M_YAxis',
    'Z2M_ZAxis',
];

$normalizeIdent = static function (string $ident): string
{
    return str_replace('&', '_and_', $ident);
};

$addExpected = static function (array &$expected, string $ident, string $source) use ($normalizeIdent): void
{
    $ident = $normalizeIdent($ident);
    if ($ident === '') {
        return;
    }

    $expected[$ident] ??= [];
    $expected[$ident][$source] = true;
};

$isColorComposite = static function (array $feature): bool
{
    $name = strtolower((string) ($feature['name'] ?? ''));
    return (($feature['type'] ?? '') === 'composite'
            && in_array($name, ['color_xy', 'color_hs', 'color_rgb'], true)
            && isset($feature['features'])
            && is_array($feature['features']))
        || isset($feature['color_mode']);
};

$isCompositeContainer = static function (array $feature) use ($isColorComposite): bool
{
    return ($feature['type'] ?? '') === 'composite'
        && isset($feature['features'])
        && is_array($feature['features'])
        && !$isColorComposite($feature);
};

$collectExposeFeature = null;
$collectExposeFeature = static function (array $feature, array &$expected, array &$capabilities, string $parentIdent = '') use (&$collectExposeFeature, $addExpected, $isColorComposite, $isCompositeContainer, $normalizeIdent): void
{
    if ($isColorComposite($feature)) {
        $name = strtolower((string) ($feature['name'] ?? ''));
        $capabilities['native_color'] = true;

        if ($name === 'color_hs') {
            $addExpected($expected, 'color_hs', 'expose');
        } elseif ($name === 'color_rgb') {
            $addExpected($expected, 'color_rgb', 'expose');
        } else {
            $addExpected($expected, 'color', 'expose');
        }

        return;
    }

    if ($isCompositeContainer($feature)) {
        $parent = $normalizeIdent((string) ($feature['property'] ?? $parentIdent));
        foreach ($feature['features'] as $subFeature) {
            if (!is_array($subFeature)) {
                continue;
            }

            $subProperty = $normalizeIdent((string) ($subFeature['property'] ?? ''));
            if ($parent !== '' && $subProperty !== '') {
                $subFeature['property'] = $parent . '__' . $subProperty;
            }

            $collectExposeFeature($subFeature, $expected, $capabilities, $parent);
        }

        return;
    }

    $property = $normalizeIdent((string) ($feature['property'] ?? ''));
    if ($property !== '') {
        $addExpected($expected, $property, 'expose');

        if ($property === 'occupancy') {
            $addExpected($expected, 'no_occupancy_since', 'derived');
        }

        if ($property === 'color') {
            $capabilities['native_color'] = true;
        }

        if ($property === 'color_temp') {
            $capabilities['color_temp'] = true;
            $addExpected($expected, 'color_temp_kelvin', 'derived');
        }

        if (isset($feature['presets']) && is_array($feature['presets']) && $feature['presets'] !== []) {
            $addExpected($expected, $property . '_presets', 'derived');
        }
    }

    if (!isset($feature['features']) || !is_array($feature['features'])) {
        return;
    }

    foreach ($feature['features'] as $subFeature) {
        if (is_array($subFeature)) {
            $collectExposeFeature($subFeature, $expected, $capabilities, $parentIdent);
        }
    }
};

$flattenPayload = null;
$flattenPayload = static function (array $payload, string $prefix = '') use (&$flattenPayload, $skipComposites): array
{
    $result = [];

    foreach ($payload as $key => $value) {
        $key = (string) $key;
        if ($prefix === '' && in_array($key, $skipComposites, true) && is_array($value)) {
            continue;
        }

        if ($key === 'exposes') {
            continue;
        }

        if ($key === 'color' && is_array($value)) {
            $result['color'] = $value;
            continue;
        }

        $newKey = $prefix !== '' ? $prefix . '__' . $key : $key;
        if (is_array($value)) {
            $result += $flattenPayload($value, $newKey);
            continue;
        }

        $result[$newKey] = $value;
    }

    return $result;
};

$decodeExportData = static function (int $instanceID): array
{
    try {
        $raw = Z2M_UIExportDebugData($instanceID);
    } catch (Throwable $exception) {
        return ['error' => $exception->getMessage()];
    }

    $prefix = 'data:application/json;base64,';
    if (str_starts_with($raw, $prefix)) {
        $raw = substr($raw, strlen($prefix));
    }

    $json = base64_decode($raw, true);
    if ($json === false) {
        return ['error' => 'Debugdaten konnten nicht decodiert werden.'];
    }

    $data = json_decode($json, true);
    if (!is_array($data)) {
        return ['error' => 'Debugdaten enthalten kein gueltiges JSON.'];
    }

    return $data;
};

$getObjectPath = static function (int $objectID): string
{
    if (function_exists('IPS_GetLocation')) {
        return IPS_GetLocation($objectID);
    }

    return IPS_GetName($objectID);
};

$getArchiveID = static function () use ($archiveModuleID): int
{
    if (!function_exists('IPS_GetInstanceListByModuleID')) {
        return 0;
    }

    $archiveIDs = IPS_GetInstanceListByModuleID($archiveModuleID);
    return (int) ($archiveIDs[0] ?? 0);
};

$isArchived = static function (int $variableID, int $archiveID): bool
{
    if ($archiveID <= 0 || !function_exists('AC_GetLoggingStatus')) {
        return false;
    }

    return (bool) @AC_GetLoggingStatus($archiveID, $variableID);
};

$referenceIndex = null;
$getReferences = static function (int $objectID) use (&$referenceIndex): array
{
    if (!function_exists('IPS_GetObjectList') || !function_exists('IPS_GetReferenceList')) {
        return [];
    }

    if ($referenceIndex === null) {
        $referenceIndex = [];
        foreach (IPS_GetObjectList() as $currentObjectID) {
            $references = @IPS_GetReferenceList((int) $currentObjectID);
            if (!is_array($references)) {
                continue;
            }

            foreach ($references as $referencedObjectID) {
                $referenceIndex[(int) $referencedObjectID][] = (int) $currentObjectID;
            }
        }
    }

    return $referenceIndex[$objectID] ?? [];
};

$buildExpected = static function (array $debugData) use ($collectExposeFeature, $flattenPayload, $addExpected): array
{
    $expected = [];
    $capabilities = [
        'color_temp'   => false,
        'native_color' => false,
    ];

    foreach (($debugData['Exposes'] ?? []) as $expose) {
        if (is_array($expose)) {
            $collectExposeFeature($expose, $expected, $capabilities);
        }
    }

    foreach ($flattenPayload((array) ($debugData['LastPayload'] ?? [])) as $ident => $_value) {
        $addExpected($expected, (string) $ident, 'payload');
    }

    if (($capabilities['color_temp'] ?? false) && !($capabilities['native_color'] ?? false)) {
        $addExpected($expected, 'color', 'derived');
    }

    $addExpected($expected, 'device_status', 'system');

    return [$expected, $capabilities];
};

$archiveID = $getArchiveID();
$clearCandidates = [];
$reviewCandidates = [];
$keptCount = 0;
$instanceCount = 0;
$errors = [];

foreach ($moduleIDs as $moduleType => $moduleID) {
    foreach (IPS_GetInstanceListByModuleID($moduleID) as $instanceID) {
        ++$instanceCount;
        $debugData = $decodeExportData((int) $instanceID);
        if (isset($debugData['error'])) {
            $errors[] = [
                'instanceID' => (int) $instanceID,
                'path'       => $getObjectPath((int) $instanceID),
                'error'      => (string) $debugData['error'],
            ];
            continue;
        }

        [$expected, $capabilities] = $buildExpected($debugData);
        $hasExposeData = is_array($debugData['Exposes'] ?? null) && $debugData['Exposes'] !== [];
        $hasPayloadData = is_array($debugData['LastPayload'] ?? null) && $debugData['LastPayload'] !== [];

        if (!$hasExposeData && !$hasPayloadData) {
            $errors[] = [
                'instanceID' => (int) $instanceID,
                'path'       => $getObjectPath((int) $instanceID),
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
            if ($ident === '') {
                ++$keptCount;
                continue;
            }

            $sources = array_keys($expected[$ident] ?? []);
            $inExpose = in_array('expose', $sources, true) || in_array('derived', $sources, true) || in_array('system', $sources, true);
            $inPayload = in_array('payload', $sources, true);
            $alwaysKeep = in_array($ident, $alwaysKeepIdents, true);

            $isUnsupportedColorTemperatureDerived = in_array($ident, ['color_temp_kelvin', 'color_temp_presets'], true)
                && !($capabilities['color_temp'] ?? false);
            $isUnsupportedDerivedWhiteColor = $ident === 'color'
                && !($capabilities['native_color'] ?? false)
                && !($capabilities['color_temp'] ?? false)
                && !$inPayload;

            if ($alwaysKeep) {
                ++$keptCount;
                continue;
            }

            $row = [
                'moduleType'  => $moduleType,
                'instanceID'  => (int) $instanceID,
                'instance'    => $getObjectPath((int) $instanceID),
                'variableID'  => (int) $childID,
                'ident'       => $ident,
                'name'        => (string) ($object['ObjectName'] ?? ''),
                'sources'     => $sources === [] ? '-' : implode(', ', $sources),
                'archived'    => $isArchived((int) $childID, $archiveID),
                'references'  => $getReferences((int) $childID),
                'reason'      => '',
            ];

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

            if ($showPayloadOnlyReview && !$inExpose && $inPayload) {
                $row['reason'] = 'Nur im letzten Payload vorhanden, nicht in aktuellen Exposes';
                $reviewCandidates[] = $row;
                continue;
            }

            ++$keptCount;
        }
    }
}

$allReportRows = array_merge($clearCandidates, $reviewCandidates);
$candidateByID = [];
foreach ($allReportRows as $row) {
    $candidateByID[$row['variableID']] = $row;
}

$deleted = [];
$skippedDeletes = [];

if ($deleteMode) {
    $idsToDelete = [];
    if ($deleteAllClearCandidates) {
        $idsToDelete = array_column($clearCandidates, 'variableID');
    }
    foreach ($deleteVariableIDs as $variableID) {
        $idsToDelete[] = (int) $variableID;
    }
    $idsToDelete = array_values(array_unique(array_filter($idsToDelete)));

    foreach ($idsToDelete as $variableID) {
        if (!isset($candidateByID[$variableID])) {
            $skippedDeletes[] = ['variableID' => $variableID, 'reason' => 'ID ist kein Kandidat aus diesem Lauf.'];
            continue;
        }

        $row = $candidateByID[$variableID];
        if ($protectArchivedVariables && $row['archived']) {
            $skippedDeletes[] = ['variableID' => $variableID, 'reason' => 'Variable ist archiviert.'];
            continue;
        }

        if ($protectReferencedVariables && count($row['references']) > 0) {
            $skippedDeletes[] = ['variableID' => $variableID, 'reason' => 'Variable hat Referenzen: ' . implode(', ', $row['references'])];
            continue;
        }

        if (!function_exists('IPS_DeleteVariable')) {
            $skippedDeletes[] = ['variableID' => $variableID, 'reason' => 'IPS_DeleteVariable ist nicht verfuegbar.'];
            continue;
        }

        try {
            IPS_DeleteVariable($variableID);
            $deleted[] = $row;
        } catch (Throwable $exception) {
            $skippedDeletes[] = ['variableID' => $variableID, 'reason' => $exception->getMessage()];
        }
    }
}

$printRows = static function (string $title, array $rows): void
{
    echo "\n" . $title . "\n";
    echo str_repeat('=', strlen($title)) . "\n";

    if ($rows === []) {
        echo "Keine Eintraege.\n";
        return;
    }

    foreach ($rows as $row) {
        $protection = [];
        if ($row['archived'] ?? false) {
            $protection[] = 'archiviert';
        }
        if (!empty($row['references'])) {
            $protection[] = 'Referenzen: ' . implode(', ', $row['references']);
        }

        echo sprintf(
            "#%d | %s | %s | %s | %s | %s\n",
            $row['variableID'],
            $row['instance'],
            $row['ident'],
            $row['name'],
            $row['reason'],
            $protection === [] ? 'nicht geschuetzt' : implode('; ', $protection)
        );
    }
};

echo "Zigbee2MQTT Variablen-Cleanup\n";
echo "=============================\n";
echo 'Konfiguration: ' . $configFileStatus . "\n";
echo 'Modus: ' . ($deleteMode ? 'LOESCHMODUS' : 'DRY-RUN') . "\n";
echo 'Gepruefte Instanzen: ' . $instanceCount . "\n";
echo 'Behaltene Variablen: ' . $keptCount . "\n";
echo 'Klare Loeschkandidaten: ' . count($clearCandidates) . "\n";
echo 'Review-Kandidaten: ' . count($reviewCandidates) . "\n";

$printRows('Klare Loeschkandidaten', $clearCandidates);
$printRows('Review-Kandidaten', $reviewCandidates);

if ($errors !== []) {
    echo "\nHinweise/Fehler\n";
    echo "==============\n";
    foreach ($errors as $error) {
        echo sprintf("#%d | %s | %s\n", $error['instanceID'], $error['path'], $error['error']);
    }
}

if (!$deleteMode && $clearCandidates !== []) {
    echo "\nZum Loeschen einzelne IDs in die externe JSON-Datei unter deleteVariableIDs eintragen.\n";
    echo "Danach deleteMode auf true und confirmDeletion auf DELETE setzen.\n";
    echo "Alternativ deleteAllClearCandidates auf true setzen, um alle klaren Kandidaten zu loeschen.\n";
    echo "Archivierte oder referenzierte Variablen werden standardmaessig nicht geloescht.\n";
}

if ($deleteMode) {
    $printRows('Geloeschte Variablen', $deleted);

    if ($skippedDeletes !== []) {
        echo "\nUebersprungene Loeschungen\n";
        echo "=========================\n";
        foreach ($skippedDeletes as $skip) {
            echo sprintf("#%d | %s\n", $skip['variableID'], $skip['reason']);
        }
    }
}
