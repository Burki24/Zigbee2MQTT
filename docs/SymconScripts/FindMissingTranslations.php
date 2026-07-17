<?php

declare(strict_types=1);

/**
 * Sucht in allen Zigbee2MQTT-Geräte- und Gruppeninstanzen nach fehlenden Übersetzungen.
 *
 * Das Skript ist für die Ausführung als IP-Symcon-Skript vorgesehen. Es erzeugt
 * unterhalb des Skripts eine Stringvariable mit einem zusammengefassten JSON-Bericht
 * und gibt denselben Bericht zusätzlich im Ausgabefenster aus.
 */

// Bei true wird vor dem Auslesen für jede Instanz IPS_ApplyChanges() ausgeführt.
// Das kann Variablen neu auswerten und MQTT-Kommunikation auslösen.
$refreshInstancesBeforeScan = false;

// Bei true wird der Bericht in einer Stringvariable unterhalb dieses Skripts gespeichert.
$storeReportInVariable = true;
$reportVariableIdent = 'MissingTranslationsReport';
$reportVariableName = 'Fehlende Zigbee2MQTT-Übersetzungen';

$moduleTypes = [
    '{E5BB36C6-A70B-EB23-3716-9151A09AC8A2}' => 'Device',
    '{11BF3773-E940-469B-9DD7-FB9ACD7199A2}' => 'Group'
];

$instances = [];
foreach ($moduleTypes as $moduleID => $moduleType) {
    foreach (IPS_GetInstanceListByModuleID($moduleID) as $instanceID) {
        $instances[(int) $instanceID] = $moduleType;
    }
}
ksort($instances, SORT_NUMERIC);

$translations = [];
$errors = [];
$instancesWithMissingTranslations = [];
$missingOccurrences = 0;

foreach ($instances as $instanceID => $moduleType) {
    try {
        if ($refreshInstancesBeforeScan) {
            IPS_ApplyChanges($instanceID);
        }

        $export = Z2M_UIExportDebugData($instanceID);
        $data = z2mDecodeDebugExport($export);
        $missingTranslations = $data['missingTranslations'] ?? [];
        if (!is_array($missingTranslations)) {
            throw new RuntimeException('Der Debugexport enthält keine gültige Übersetzungsliste.');
        }

        $instance = [
            'id'         => $instanceID,
            'name'       => IPS_GetName($instanceID),
            'path'       => IPS_GetLocation($instanceID),
            'moduleType' => $moduleType
        ];
        if (isset($data['Model']) && is_string($data['Model']) && $data['Model'] !== '') {
            $instance['model'] = $data['Model'];
        }

        foreach ($missingTranslations as $translation) {
            if (!is_array($translation) || count($translation) !== 1) {
                continue;
            }

            $type = (string) array_key_first($translation);
            $value = (string) $translation[$type];
            if ($type === '' || $value === '') {
                continue;
            }

            $key = $type . "\0" . $value;
            if (!isset($translations[$key])) {
                $translations[$key] = [
                    'type'      => $type,
                    'value'     => $value,
                    'instances' => []
                ];
            }

            $translations[$key]['instances'][$instanceID] = $instance;
            $instancesWithMissingTranslations[$instanceID] = true;
            ++$missingOccurrences;
        }
    } catch (Throwable $exception) {
        $errors[] = [
            'instanceID' => $instanceID,
            'name'       => IPS_GetName($instanceID),
            'moduleType' => $moduleType,
            'error'      => $exception->getMessage()
        ];
    }
}

$translationList = array_values($translations);
foreach ($translationList as &$translation) {
    $translation['instances'] = array_values($translation['instances']);
}
unset($translation);

usort(
    $translationList,
    static fn (array $left, array $right): int => [$left['type'], $left['value']] <=> [$right['type'], $right['value']]
);

$report = [
    'generatedAt' => date(DATE_ATOM),
    'language'    => IPS_GetSystemLanguage(),
    'summary'     => [
        'instancesScanned'                 => count($instances),
        'instancesWithMissingTranslations' => count($instancesWithMissingTranslations),
        'missingOccurrences'               => $missingOccurrences,
        'uniqueMissingTranslations'        => count($translationList),
        'errors'                           => count($errors)
    ],
    'missingTranslations' => $translationList,
    'errors'              => $errors
];

$json = json_encode(
    $report,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
);
if (!is_string($json)) {
    throw new RuntimeException('Der Übersetzungsbericht konnte nicht als JSON erzeugt werden.');
}

if ($storeReportInVariable) {
    $scriptID = (int) ($_IPS['SELF'] ?? 0);
    if ($scriptID > 0) {
        $variableID = @IPS_GetObjectIDByIdent($reportVariableIdent, $scriptID);
        if ($variableID === false) {
            $variableID = IPS_CreateVariable(VARIABLETYPE_STRING);
            IPS_SetParent($variableID, $scriptID);
            IPS_SetIdent($variableID, $reportVariableIdent);
            IPS_SetName($variableID, $reportVariableName);
        }
        SetValueString($variableID, $json);
    }
}

echo $json;

/**
 * Dekodiert den von Z2M_UIExportDebugData() gelieferten Data-URI.
 *
 * @return array<string, mixed>
 */
function z2mDecodeDebugExport(string $export): array
{
    $prefix = 'data:application/json;base64,';
    if (str_starts_with($export, $prefix)) {
        $export = substr($export, strlen($prefix));
    }

    $decoded = base64_decode($export, true);
    if ($decoded === false) {
        throw new RuntimeException('Der Debugexport ist nicht gültig Base64-kodiert.');
    }

    $data = json_decode($decoded, true);
    if (!is_array($data)) {
        throw new RuntimeException('Der Debugexport enthält kein gültiges JSON.');
    }

    return $data;
}
